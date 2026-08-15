<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Pop\Storage\Exception\DirectoryNotFoundException;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToCopyFileException;
use Pop\Storage\Exception\UnableToCreateDirectoryException;
use Pop\Storage\Exception\UnableToDeleteDirectoryException;
use Pop\Storage\Exception\UnableToDeleteFileException;
use Pop\Storage\Exception\UnableToGenerateTemporaryUrlException;
use Pop\Storage\Exception\UnableToMoveFileException;
use Pop\Storage\Exception\UnableToReadFileException;
use Pop\Storage\Exception\UnableToWriteFileException;

/**
 * Storage adapter S3 class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class S3 extends AbstractAdapter
{

    /**
     * S3 client
     * @var ?S3Client
     */
    protected ?S3Client $client = null;

    /**
     * Constructor
     *
     * @param string   $directory
     * @param S3Client $client
     */
    public function __construct(string $directory, S3Client $client)
    {
        parent::__construct($directory);
        $this->setClient($client);
    }

    /**
     * Set S3 client
     *
     * @param  S3Client $client
     * @return S3
     */
    public function setClient(S3Client $client): S3
    {
        $this->client = $client;
        $this->client->registerStreamWrapper();
        return $this;
    }

    /**
     * Get S3 client
     *
     * @return ?S3Client
     */
    public function getClient(): ?S3Client
    {
        return $this->client;
    }

    /**
     * Has S3 client
     *
     * @return bool
     */
    public function hasClient(): bool
    {
        return ($this->client !== null);
    }

    /**
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    public function mkdir(string $directory): void
    {
        $key    = $this->scrub($directory) . '/';
        $bucket = str_replace('s3://', '', $this->directory);
        if (str_contains($bucket, '/')) {
            $subfolder = substr($bucket, (strpos($bucket, '/') + 1));
            $key       = $subfolder . '/' . $key;
            $bucket    = substr($bucket, 0, strpos($bucket, '/'));
        }

        try {
            $this->client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => '']);
        } catch (AwsException $exception) {
            throw new UnableToCreateDirectoryException(
                'Error: Unable to create directory \'' . $directory . '\'.', 0, $exception
            );
        }
    }

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @throws DirectoryNotFoundException
     * @throws UnableToDeleteDirectoryException
     * @return void
     */
    public function rmdir(string $directory): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory);
        if (!is_dir($path)) {
            throw new DirectoryNotFoundException('Error: The directory \'' . $path . '\' was not found.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $ok = $fileInfo->isDir() ? @rmdir((string) $fileInfo) : @unlink((string) $fileInfo);
            if (!$ok) {
                throw new UnableToDeleteDirectoryException('Error: Unable to delete directory \'' . $path . '\'.');
            }
        }

        if (!@rmdir($path)) {
            throw new UnableToDeleteDirectoryException('Error: Unable to delete directory \'' . $path . '\'.');
        }
    }

    /**
     * List directories
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listDirs(?string $search = null, bool $recursive = false): array
    {
        $dirs   = [];
        $params = ['Bucket' => str_replace('s3://', '', $this->baseDirectory)];
        if (!$recursive) {
            $params['Delimiter'] = '/';
        }
        if ($this->baseDirectory != $this->directory) {
            $params['Prefix'] = str_replace($this->baseDirectory . '/', '', $this->directory . '/');
        }

        try {
            foreach ($this->client->getPaginator('ListObjects', $params) as $page) {
                foreach ($page['CommonPrefixes'] ?? [] as $commonPrefix) {
                    $dirs[] = (isset($params['Prefix']) && str_starts_with($commonPrefix['Prefix'], $params['Prefix'])) ?
                        substr($commonPrefix['Prefix'], strlen($params['Prefix'])) : $commonPrefix['Prefix'];
                }
                if ($recursive) {
                    // Without Delimiter, S3 never returns CommonPrefixes at all - every
                    // intermediate directory (whether it holds an explicit zero-byte marker
                    // or only real files) has to be derived from each object key's own path
                    // segments instead, the same way Local's recursive walk reports a
                    // directory just because something lives in it.
                    foreach ($page['Contents'] ?? [] as $object) {
                        $relativeKey = (isset($params['Prefix']) && str_starts_with($object['Key'], $params['Prefix'])) ?
                            substr($object['Key'], strlen($params['Prefix'])) : $object['Key'];
                        $segments = explode('/', rtrim($relativeKey, '/'));
                        array_pop($segments); // the object's own name/marker, not a parent directory
                        $path = '';
                        foreach ($segments as $segment) {
                            if ($segment === '') {
                                continue;
                            }
                            $path  .= $segment . '/';
                            $dirs[] = $path;
                        }
                    }
                }
            }
        } catch (AwsException $exception) {
            throw new UnableToReadFileException('Error: Unable to list directories.', 0, $exception);
        }

        $dirs = array_values(array_unique($dirs));

        if ($search !== null) {
            $dirs = $this->searchFilter($dirs, $search);
        }

        return $dirs;
    }

    /**
     * List files
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listFiles(?string $search = null, bool $recursive = false): array
    {
        $files  = [];
        $params = ['Bucket' => str_replace('s3://', '', $this->baseDirectory)];
        if (!$recursive) {
            $params['Delimiter'] = '/';
        }
        if ($this->baseDirectory != $this->directory) {
            $params['Prefix'] = str_replace($this->baseDirectory . '/', '', $this->directory . '/');
        }

        try {
            foreach ($this->client->getPaginator('ListObjects', $params) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $isDirectoryMarker = str_ends_with($object['Key'], '/') && (int) $object['Size'] === 0;
                    $isCurrentPrefix   = isset($params['Prefix']) && ($object['Key'] === $params['Prefix']);
                    if ($isDirectoryMarker || $isCurrentPrefix) {
                        continue;
                    }
                    $files[] = (isset($params['Prefix']) && str_starts_with($object['Key'], $params['Prefix'])) ?
                        substr($object['Key'], strlen($params['Prefix'])) : $object['Key'];
                }
            }
        } catch (AwsException $exception) {
            throw new UnableToReadFileException('Error: Unable to list files.', 0, $exception);
        }

        if ($search !== null) {
            $files = $this->searchFilter($files, $search);
        }

        return $files;
    }

    /**
     * Put file
     *
     * @param  string $fileFrom
     * @param  bool   $copy
     * @return void
     */
    public function putFile(string $fileFrom, bool $copy = true): void
    {
        if (!file_exists($fileFrom)) {
            throw new FileNotFoundException('Error: The file \'' . $fileFrom . '\' was not found.');
        }

        $destination = $this->directory . DIRECTORY_SEPARATOR . basename($fileFrom);

        if (!@copy($fileFrom, $destination)) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $fileFrom . '\'.');
        }

        // $copy = false means "move": PHP's rename() cannot bridge different stream wrapper
        // protocols (a plain local path has none, the destination is s3://), so it can never
        // succeed here the way it does for Local's same-wrapper rename() - copy the object,
        // then remove the local source, to get the same move semantics.
        if (!$copy && !@unlink($fileFrom)) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $fileFrom . '\'.');
        }
    }

    /**
     * Put file contents
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function putFileContents(string $filename, string $fileContents): void
    {
        if (@file_put_contents($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename), $fileContents) === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\'.');
        }
    }

    /**
     * Put file from a stream resource
     *
     * @param  string $filename
     * @param  mixed  $resource
     * @throws UnableToWriteFileException
     * @return void
     */
    public function putFileStream(string $filename, mixed $resource): void
    {
        if (!is_resource($resource)) {
            throw new UnableToWriteFileException('Error: The provided resource is not a valid stream.');
        }

        $destination = @fopen($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename), 'w');
        if ($destination === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\'.');
        }

        stream_copy_to_stream($resource, $destination);
        fclose($destination);
    }

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @throws UnableToWriteFileException|PathTraversalException
     * @return void
     */
    public function uploadFile(array $file): void
    {
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            throw new UnableToWriteFileException('Error: The uploaded file array was not valid.');
        }

        $contents = @file_get_contents($file['tmp_name']);
        if ($contents === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $file['name'] . '\'.');
        }

        $destination = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($file['name']);
        if (@file_put_contents($destination, $contents) === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $file['name'] . '\'.');
        }
    }

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return void
     */
    public function copyFile(string $sourceFile, string $destFile): void
    {
        $sourceFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($sourceFile);
        $destFile   = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($destFile);
        if (!file_exists($sourceFile)) {
            throw new FileNotFoundException('Error: The file \'' . $sourceFile . '\' was not found.');
        }
        if (!@copy($sourceFile, $destFile)) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $sourceFile . '\'.');
        }
    }

    /**
     * Copy file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function copyFileToExternal(string $sourceFile, string $externalFile): void
    {
        $sourceFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($sourceFile);
        if (!file_exists($sourceFile)) {
            throw new FileNotFoundException('Error: The file \'' . $sourceFile . '\' was not found.');
        }
        if (!@copy($sourceFile, $externalFile)) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $sourceFile . '\'.');
        }
    }

    /**
     * Copy file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    public function copyFileFromExternal(string $externalFile, string $destFile): void
    {
        $destFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($destFile);
        if (!file_exists($externalFile)) {
            throw new FileNotFoundException('Error: The file \'' . $externalFile . '\' was not found.');
        }
        if (!@copy($externalFile, $destFile)) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $externalFile . '\'.');
        }
    }

    /**
     * Move file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function moveFileToExternal(string $sourceFile, string $externalFile): void
    {
        $sourceFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($sourceFile);
        if (!file_exists($sourceFile)) {
            throw new FileNotFoundException('Error: The file \'' . $sourceFile . '\' was not found.');
        }
        if (!@rename($sourceFile, $externalFile)) {
            throw new UnableToMoveFileException('Error: Unable to move file \'' . $sourceFile . '\'.');
        }
    }

    /**
     * Move file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    public function moveFileFromExternal(string $externalFile, string $destFile): void
    {
        $destFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($destFile);
        if (!file_exists($externalFile)) {
            throw new FileNotFoundException('Error: The file \'' . $externalFile . '\' was not found.');
        }
        if (!@rename($externalFile, $destFile)) {
            throw new UnableToMoveFileException('Error: Unable to move file \'' . $externalFile . '\'.');
        }
    }

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return void
     */
    public function renameFile(string $oldFile, string $newFile): void
    {
        $oldFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($oldFile);
        $newFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($newFile);
        if (!file_exists($oldFile)) {
            throw new FileNotFoundException('Error: The file \'' . $oldFile . '\' was not found.');
        }
        if (!@rename($oldFile, $newFile)) {
            throw new UnableToMoveFileException('Error: Unable to move file \'' . $oldFile . '\'.');
        }
    }

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function replaceFileContents(string $filename, string $fileContents): void
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (@file_put_contents($filename, $fileContents) === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\'.');
        }
    }

    /**
     * Delete file
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile(string $filename): void
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (!@unlink($filename)) {
            throw new UnableToDeleteFileException('Error: Unable to delete file \'' . $filename . '\'.');
        }
    }

    /**
     * Fetch file
     *
     * @param  string $filename
     * @return mixed
     */
    public function fetchFile(string $filename): mixed
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return file_get_contents($filename);
    }

    /**
     * Fetch file as a stream resource
     *
     * @param  string $filename
     * @throws FileNotFoundException
     * @return mixed
     */
    public function fetchFileStream(string $filename): mixed
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($path)) {
            throw new FileNotFoundException('Error: The file \'' . $path . '\' was not found.');
        }

        return fopen($path, 'r');
    }

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($path)) {
            throw new FileNotFoundException('Error: The file \'' . $path . '\' was not found.');
        }

        try {
            $fileObject = $this->client->headObject([
                'Bucket' => str_replace('s3://', '', $this->directory),
                'Key'    => $this->scrub($filename),
            ]);
            return $fileObject->toArray();
        } catch (AwsException $exception) {
            throw new UnableToReadFileException(
                'Error: Unable to read file info for \'' . $filename . '\'.', 0, $exception
            );
        }
    }

    /**
     * Get a temporary (presigned) URL for the file, valid for $expiresInSeconds
     *
     * @param  string $filename
     * @param  int    $expiresInSeconds
     * @throws UnableToGenerateTemporaryUrlException
     * @return string
     */
    public function getTemporaryUrl(string $filename, int $expiresInSeconds = 900): string
    {
        $bucket = str_replace('s3://', '', $this->baseDirectory);
        $key    = str_replace($this->baseDirectory . '/', '', $this->directory . '/') . $this->scrub($filename);

        try {
            $command = $this->client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
            $request = $this->client->createPresignedRequest($command, '+' . $expiresInSeconds . ' seconds');
            return (string) $request->getUri();
        } catch (AwsException $exception) {
            throw new UnableToGenerateTemporaryUrlException(
                'Error: Unable to generate a temporary URL for \'' . $filename . '\'.', 0, $exception
            );
        }
    }

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool
    {
        return file_exists($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename));
    }

    /**
     * Check if is a dir
     *
     * @param  string $directory
     * @return bool
     */
    public function isDir(string $directory): bool
    {
        return is_dir($this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory));
    }

    /**
     * Check if is a file
     *
     * @param  string $filename
     * @return bool
     */
    public function isFile(string $filename): bool
    {
        return is_file($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename));
    }

    /**
     * Get file size
     *
     * @param  string $filename
     * @throws FileNotFoundException
     * @return int
     */
    public function getFileSize(string $filename): int
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return filesize($filename);
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @throws FileNotFoundException
     * @return string
     */
    public function getFileType(string $filename): string
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return filetype($filename);
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @throws FileNotFoundException
     * @return int|string
     */
    public function getFileMTime(string $filename): int|string
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return filemtime($filename);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @throws FileNotFoundException
     * @return string
     */
    public function md5File(string $filename): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($path)) {
            throw new FileNotFoundException('Error: The file \'' . $path . '\' was not found.');
        }

        try {
            $fileObject = $this->client->getObject([
                'Bucket' => str_replace('s3://', '', $this->baseDirectory),
                'Key'    => str_replace($this->baseDirectory . '/', '', $this->directory . '/') . $this->scrub($filename),
            ]);
        } catch (AwsException $exception) {
            throw new UnableToReadFileException(
                'Error: Unable to read file \'' . $filename . '\'.', 0, $exception
            );
        }

        if (!isset($fileObject['ETag'])) {
            throw new UnableToReadFileException('Error: No ETag/checksum returned for \'' . $filename . '\'.');
        }

        return str_replace('"', '', $fileObject['ETag']);
    }

}
