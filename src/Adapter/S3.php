<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter;

use Aws\S3\S3Client;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Storage adapter S3 class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
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
        $this->client->putObject([
            'Bucket' => str_replace('s3://', '', $this->directory),
            'Key'    => $this->scrub($directory) . '/',
            'Body'   => ''
        ]);
    }

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @throws \Pop\Dir\Exception
     * @return void
     */
    public function rmdir(string $directory): void
    {
        $directory = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir((string)$fileInfo);
            } else {
                unlink((string)$fileInfo);
            }
        }

        rmdir($directory);
    }

    /**
     * List directories
     *
     * @param  ?string $search
     * @return array
     */
    public function listDirs(?string $search = null): array
    {
        $dirs   = [];
        $params = ['Bucket' => str_replace('s3://', '', $this->baseDirectory)];

        if ($this->baseDirectory != $this->directory) {
            $params['Prefix'] = str_replace($this->baseDirectory . '/', '', $this->directory . '/');
        }

        $objects = $this->client->listObjects($params);

        foreach ($objects['Contents'] as $object) {
            if ($object['Size'] == 0) {
                $key = (isset($params['Prefix']) && str_starts_with($object['Key'], $params['Prefix'])) ?
                    substr($object['Key'], strlen($params['Prefix'])) : $object['Key'];
                if (substr_count($key, '/') == 1) {
                    $dirs[] = $key;
                }
            }
        }

        if ($search !== null) {
            $dirs = $this->searchFilter($dirs, $search);
        }

        return $dirs;

    }

    /**
     * List files
     *
     * @param  ?string $search
     * @return array
     */
    public function listFiles(?string $search = null): array
    {
        $files   = [];
        $params  = ['Bucket' => str_replace('s3://', '', $this->baseDirectory), 'Delimiter' => '/'];

        if ($this->baseDirectory != $this->directory) {
            $params['Prefix'] = str_replace($this->baseDirectory . '/', '', $this->directory . '/');
        }

        $objects = $this->client->listObjects($params);

        foreach ($objects['Contents'] as $object) {
            if (!isset($params['Prefix']) || ($object['Key'] != $params['Prefix'])) {
                $files[] = (isset($params['Prefix']) && str_starts_with($object['Key'], $params['Prefix'])) ?
                    substr($object['Key'], strlen($params['Prefix'])) : $object['Key'];
            }
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
        if (file_exists($fileFrom)) {
            if ($copy) {
                copy($fileFrom, $this->directory . DIRECTORY_SEPARATOR . basename($fileFrom));
            } else {
                rename($fileFrom, $this->directory . DIRECTORY_SEPARATOR . basename($fileFrom));
            }
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
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename), $fileContents);
    }

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @throws Exception
     * @return void
     */
    public function uploadFile(array $file): void
    {
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            throw new Exception('Error: The uploaded file array was not valid');
        }

        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $file['name'], file_get_contents($file['tmp_name']));
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
        if (file_exists($sourceFile)) {
            copy($sourceFile, $destFile);
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
        if (file_exists($sourceFile)) {
            copy($sourceFile, $externalFile);
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
        if (file_exists($externalFile)) {
            copy($externalFile, $destFile);
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
        $oldFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($sourceFile);
        if (file_exists($oldFile)) {
            rename($oldFile, $externalFile);
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
        if (file_exists($externalFile)) {
            rename($externalFile, $destFile);
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
        if (file_exists($oldFile)) {
            rename($oldFile, $newFile);
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
        if (file_exists($filename)) {
            file_put_contents($filename, $fileContents);
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
        if (file_exists($filename)) {
            unlink($filename);
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
        return (file_exists($filename)) ? file_get_contents($filename) : false;
    }

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array
    {
        if (file_exists($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename))) {
            $fileObject = $this->client->headObject([
                'Bucket' => str_replace('s3://', '', $this->directory),
                'Key'    => $this->scrub($filename),
            ]);

            return $fileObject->toArray();
        } else {
            return [];
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
     * @return int|bool
     */
    public function getFileSize(string $filename): int|bool
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        return (file_exists($filename)) ? filesize($filename) : false;
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string|bool
     */
    public function getFileType(string $filename): string|bool
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        return (file_exists($filename)) ? filetype($filename) : false;
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|string|bool
     */
    public function getFileMTime(string $filename): int|string|bool
    {
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        return (file_exists($filename)) ? filemtime($filename) : false;
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string|bool
     */
    public function md5File(string $filename): string|bool
    {
        if (file_exists($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename))) {
            $fileObject = $this->client->getObject([
                'Bucket' => str_replace('s3://', '', $this->directory),
                'Key'    => $this->scrub($filename),
            ]);

            return (isset($fileObject['ETag'])) ? str_replace('"', '', $fileObject['ETag']) : false;
        } else {
            return false;
        }
    }

}