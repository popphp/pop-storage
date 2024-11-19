<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter;

use Pop\Dir\Dir;
use Pop\Utils\File;

/**
 * Storage adapter local class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.0.0
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
        mkdir($this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory));
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
        $dir = new Dir($this->directory . DIRECTORY_SEPARATOR . $this->scrub($directory));
        $dir->emptyDir(true);
    }

    /**
     * List directories
     *
     * @param  ?string $search
     * @return array
     */
    public function listDirs(?string $search = null): array
    {
        $directory   = $this->directory;
        $directories =  array_map(function($value) {
            return $value. DIRECTORY_SEPARATOR;
        }, array_values(array_filter(scandir($directory), function($value) use ($directory) {
            return (($value != '.') && ($value != '..') && file_exists($directory . DIRECTORY_SEPARATOR . $value) &&
                is_dir($directory . DIRECTORY_SEPARATOR . $value));
        })));

        if ($search !== null) {
            $directories = $this->searchFilter($directories, $search);
        }

        return $directories;
    }

    /**
     * List files
     *
     * @param  ?string $search
     * @return array
     */
    public function listFiles(?string $search = null): array
    {
        $directory = $this->directory;
        $files     = array_values(array_filter(scandir($directory), function($value) use ($directory) {
            return (($value != '.') && ($value != '..') && file_exists($directory . DIRECTORY_SEPARATOR . $value) &&
                !is_dir($directory . DIRECTORY_SEPARATOR . $value) && is_file($directory . DIRECTORY_SEPARATOR . $value));
        }));

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
     * Upload file from server request $_FILES['files']
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

        if (is_uploaded_file($file['tmp_name'])) {
            move_uploaded_file($file['tmp_name'], $this->directory . DIRECTORY_SEPARATOR . $file['name']);
        } else {
            rename($file['tmp_name'], $this->directory . DIRECTORY_SEPARATOR . $file['name']);
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
        $sourceFile = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($sourceFile);
        if (file_exists($sourceFile)) {
            rename($sourceFile, $externalFile);
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
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        return (new File($filename))->toArray();
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
        $filename = $this->directory . DIRECTORY_SEPARATOR . $this->scrub($filename);
        return (file_exists($filename)) ? md5_file($filename) : false;
    }
    
}
