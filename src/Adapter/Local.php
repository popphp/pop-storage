<?php
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

use Pop\Dir\Dir;
use Pop\Utils\File;
use Pop\Storage\Exception\DirectoryNotFoundException;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\UnableToCopyFileException;
use Pop\Storage\Exception\UnableToCreateDirectoryException;
use Pop\Storage\Exception\UnableToDeleteDirectoryException;
use Pop\Storage\Exception\UnableToDeleteFileException;
use Pop\Storage\Exception\UnableToMoveFileException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Exception\UnsupportedOperationException;

/**
 * Storage adapter local class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Local extends AbstractAdapter
{

    /**
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    public function mkdir(string $directory): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory);
        if (!@mkdir($path)) {
            throw new UnableToCreateDirectoryException('Error: Unable to create directory \'' . $path . '\'.');
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

        try {
            $dir = new Dir($path);
            $dir->emptyDir(true);
        } catch (\Pop\Dir\Exception $exception) {
            throw new UnableToDeleteDirectoryException(
                'Error: Unable to delete directory \'' . $path . '\'.', 0, $exception
            );
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
        $directories = $recursive ? $this->walkRecursive(true) : $this->listDirsFlat();

        if ($search !== null) {
            $directories = $this->searchFilter($directories, $search);
        }

        return $directories;
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
        $files = $recursive ? $this->walkRecursive(false) : $this->listFilesFlat();

        if ($search !== null) {
            $files = $this->searchFilter($files, $search);
        }

        return $files;
    }

    /**
     * List directories (flat, top-level only)
     *
     * @return array
     */
    private function listDirsFlat(): array
    {
        $directory = $this->directory;
        return array_map(function($value) {
            return $value. DIRECTORY_SEPARATOR;
        }, array_values(array_filter(scandir($directory), function($value) use ($directory) {
            return (($value != '.') && ($value != '..') && file_exists($directory . DIRECTORY_SEPARATOR . $value) &&
                is_dir($directory . DIRECTORY_SEPARATOR . $value));
        })));
    }

    /**
     * List files (flat, top-level only)
     *
     * @return array
     */
    private function listFilesFlat(): array
    {
        $directory = $this->directory;
        return array_values(array_filter(scandir($directory), function($value) use ($directory) {
            return (($value != '.') && ($value != '..') && file_exists($directory . DIRECTORY_SEPARATOR . $value) &&
                !is_dir($directory . DIRECTORY_SEPARATOR . $value) && is_file($directory . DIRECTORY_SEPARATOR . $value));
        }));
    }

    /**
     * Recursively walk the current directory, returning relative paths (with a trailing
     * separator for directories) for every entry below it
     *
     * @param  bool $directoriesOnly
     * @return array
     */
    private function walkRecursive(bool $directoriesOnly): array
    {
        $results  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $relativePath = substr((string) $fileInfo, strlen($this->directory) + 1);
            if ($fileInfo->isDir()) {
                if ($directoriesOnly) {
                    $results[] = $relativePath . DIRECTORY_SEPARATOR;
                }
            } elseif (!$directoriesOnly) {
                $results[] = $relativePath;
            }
        }

        return $results;
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

        if ($copy) {
            if (!@copy($fileFrom, $destination)) {
                throw new UnableToWriteFileException('Error: Unable to write file \'' . $fileFrom . '\'.');
            }
        } else {
            if (!@rename($fileFrom, $destination)) {
                throw new UnableToWriteFileException('Error: Unable to write file \'' . $fileFrom . '\'.');
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
        if (@file_put_contents($this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename), $fileContents) === false) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\'.');
        }
    }

    /**
     * Put file from a stream resource
     *
     * @param  string $filename
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
     * Upload file from server request $_FILES['files']
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

        $destination = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($file['name']);
        $moved = is_uploaded_file($file['tmp_name'])
            ? @move_uploaded_file($file['tmp_name'], $destination)
            : @rename($file['tmp_name'], $destination);

        if (!$moved) {
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
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return (new File($filename))->toArray();
    }

    /**
     * Get a temporary (presigned) URL for the file, valid for $expiresInSeconds
     *
     * @param  string $filename
     * @param  int    $expiresInSeconds
     * @throws UnsupportedOperationException
     * @return string
     */
    public function getTemporaryUrl(string $filename, int $expiresInSeconds = 900): string
    {
        throw new UnsupportedOperationException('Error: Temporary URLs are not supported by the local disk adapter.');
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
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        if (!file_exists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        return md5_file($filename);
    }
    
}
