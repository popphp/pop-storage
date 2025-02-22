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

use Pop\Storage\StorageInterface;

/**
 * Storage adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
abstract class AbstractAdapter implements StorageInterface
{

    /**
     * Storage base directory
     * @var ?string
     */
    protected ?string $baseDirectory = null;

    /**
     * Current directory
     * @var ?string
     */
    protected ?string $directory = null;

    /**
     * Constructor
     *
     * @param string $directory
     */
    public function __construct(string $directory)
    {
        $this->setBaseDir($directory);
        $this->chdir();
    }

    /**
     * Set base directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function setBaseDir(?string $directory = null): void
    {
        $this->baseDirectory = $directory;
    }

    /**
     * Get base directory
     *
     * @return ?string
     */
    public function getBaseDir(): ?string
    {
        return $this->baseDirectory;
    }

    /**
     * Get current directory
     *
     * @return ?string
     */
    public function getCurrentDir(): ?string
    {
        return $this->directory;
    }

    /**
     * Change directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function chdir(?string $directory = null): void
    {
        if ($directory === null) {
            $this->directory = $this->baseDirectory;
        } else {
            $this->directory = $this->baseDirectory . DIRECTORY_SEPARATOR . $this->scrub($directory);
        }
    }

    /**
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    abstract public function mkdir(string $directory): void;

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @return void
     */
    abstract public function rmdir(string $directory): void;

    /**
     * List all
     *
     * @param  ?string $search
     * @return array
     */
    function listAll(?string $search = null): array
    {
        return array_merge($this->listDirs($search), $this->listFiles($search));
    }

    /**
     * List directories
     *
     * @param  ?string $search
     * @return array
     */
    abstract public function listDirs(?string $search = null): array;

    /**
     * List files
     *
     * @param  ?string $search
     * @return array
     */
    abstract public function listFiles(?string $search = null): array;

    /**
     * Put file
     *
     * @param  string $fileFrom
     * @param  bool   $copy
     * @return void
     */
    abstract public function putFile(string $fileFrom, bool $copy = true): void;

    /**
     * Put file contents
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    abstract public function putFileContents(string $filename, string $fileContents): void;

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @return void
     */
    abstract public function uploadFile(array $file): void;

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return void
     */
    abstract public function copyFile(string $sourceFile, string $destFile): void;


    /**
     * Copy file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    abstract public function copyFileToExternal(string $sourceFile, string $externalFile): void;

    /**
     * Copy file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    abstract public function copyFileFromExternal(string $externalFile, string $destFile): void;

    /**
     * Move file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    abstract public function moveFileToExternal(string $sourceFile, string $externalFile): void;

    /**
     * Move file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    abstract public function moveFileFromExternal(string $externalFile, string $destFile): void;

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return void
     */
    abstract public function renameFile(string $oldFile, string $newFile): void;

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    abstract public function replaceFileContents(string $filename, string $fileContents): void;

    /**
     * Delete file
     *
     * @param  string $filename
     * @return void
     */
    abstract public function deleteFile(string $filename): void;

    /**
     * Fetch file contents
     *
     * @param  string $filename
     * @return mixed
     */
    abstract public function fetchFile(string $filename): mixed;

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    abstract public function fetchFileInfo(string $filename): array;

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    abstract public function fileExists(string $filename): bool;

    /**
     * Check if is a dir
     *
     * @param  string $directory
     * @return bool
     */
    abstract public function isDir(string $directory): bool;

    /**
     * Check if is a file
     *
     * @param  string $filename
     * @return bool
     */
    abstract public function isFile(string $filename): bool;

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int|bool
     */
    abstract public function getFileSize(string $filename): int|bool;

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string|bool
     */
    abstract public function getFileType(string $filename): string|bool;

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|string|bool
     */
    abstract public function getFileMTime(string $filename): int|string|bool;

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string|bool
     */
    abstract public function md5File(string $filename): string|bool;

    /**
     * Scrub value of leading dots or slashes
     *
     * @param  string $value
     * @return string
     */
    protected function scrub(string $value): string
    {
        if (str_starts_with($value, '/') || str_starts_with($value, '\\')) {
            $value = substr($value, 1);
        } else if (str_starts_with($value, './') || str_starts_with($value, '.\\')) {
            $value = substr($value, 2);
        }

        return $value;
    }

    /**
     * Search and filter values
     *
     * @param  array  $objects
     * @param  string $search
     * @return array
     */
    protected function searchFilter(array $objects, string $search): array
    {
        if (str_starts_with($search, '*')) {
            $search  = substr($search, 1);
            $objects = array_filter($objects, function ($value) use ($search) {
                return str_ends_with($value, $search);
            });
        } else if (str_ends_with($search, '*')) {
            $search  = substr($search, 0, -1);
            $objects = array_filter($objects, function ($value) use ($search) {
                return str_starts_with($value, $search);
            });
        } else {
            $objects = array_filter($objects, function ($value) use ($search) {
                return ($value == $search);
            });
        }

        return $objects;
    }

}
