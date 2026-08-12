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
namespace Pop\Storage;

/**
 * Storage interface
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
interface StorageInterface
{

    /**
     * Set base directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function setBaseDir(?string $directory = null): void;

    /**
     * Get base directory
     *
     * @return ?string
     */
    public function getBaseDir(): ?string;

    /**
     * Get current directory
     *
     * @return ?string
     */
    public function getCurrentDir(): ?string;

    /**
     * Change directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function chdir(?string $directory = null): void;

    /**
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    public function mkdir(string $directory): void;

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @return void
     */
    public function rmdir(string $directory): void;

    /**
     * List all
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listAll(?string $search = null, bool $recursive = false): array;

    /**
     * List directories
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listDirs(?string $search = null, bool $recursive = false): array;

    /**
     * List files
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listFiles(?string $search = null, bool $recursive = false): array;

    /**
     * Put file
     *
     * @param  string $fileFrom
     * @param  bool   $copy
     * @return void
     */
    public function putFile(string $fileFrom, bool $copy = true): void;

    /**
     * Put file contents
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function putFileContents(string $filename, string $fileContents): void;

    /**
     * Put file from a stream resource
     *
     * @param  string $filename
     * @param  mixed  $resource
     * @return void
     */
    public function putFileStream(string $filename, mixed $resource): void;

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @return void
     */
    public function uploadFile(array $file): void;

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return void
     */
    public function copyFile(string $sourceFile, string $destFile): void;


    /**
     * Copy file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function copyFileToExternal(string $sourceFile, string $externalFile): void;


    /**
     * Copy file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    public function copyFileFromExternal(string $externalFile, string $destFile): void;

    /**
     * Move file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function moveFileToExternal(string $sourceFile, string $externalFile): void;

    /**
     * Move file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    public function moveFileFromExternal(string $externalFile, string $destFile): void;

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return void
     */
    public function renameFile(string $oldFile, string $newFile): void;

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function replaceFileContents(string $filename, string $fileContents): void;

    /**
     * Delete file
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile(string $filename): void;

    /**
     * Fetch file contents
     *
     * @param  string $filename
     * @return mixed
     */
    public function fetchFile(string $filename): mixed;

    /**
     * Fetch file as a stream resource
     *
     * @param  string $filename
     * @return mixed
     */
    public function fetchFileStream(string $filename): mixed;

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array;

    /**
     * Get a temporary (presigned) URL for the file, valid for $expiresInSeconds
     *
     * @param  string $filename
     * @param  int    $expiresInSeconds
     * @return string
     */
    public function getTemporaryUrl(string $filename, int $expiresInSeconds = 900): string;

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool;

    /**
     * Check if is a directory
     *
     * @param  string $directory
     * @return bool
     */
    public function isDir(string $directory): bool;

    /**
     * Check if is a file
     *
     * @param  string $filename
     * @return bool
     */
    public function isFile(string $filename): bool;

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int
     */
    public function getFileSize(string $filename): int;

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string
     */
    public function getFileType(string $filename): string;

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|string
     */
    public function getFileMTime(string $filename): int|string;

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File(string $filename): string;

}
