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

/**
 * Storage adapter interface
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface AdapterInterface
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
     * List directories
     *
     * @return array
     */
    public function listDirs(): array;

    /**
     * List files
     *
     * @return array
     */
    public function listFiles(): array;

    /**
     * Fetch file
     *
     * @param  string $file
     * @return mixed
     */
    public function fetchFile(string $file): mixed;

    /**
     * Put file
     *
     * @param  string $file
     * @return void
     */
    public function putFile(string $file): void;

    /**
     * Put file contents
     *
     * @param  string $file
     * @param  string $fileContents
     * @return void
     */
    public function putFileContents(string $file, string $fileContents): void;

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return mixed
     */
    public function renameFile(string $oldFile, string $newFile): mixed;

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return mixed
     */
    public function copyFile(string $sourceFile, string $destFile): mixed;

    /**
     * Replace file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return mixed
     */
    public function replaceFile(string $oldFile, string $newFile): mixed;

    /**
     * Replace file
     *
     * @param  string $file
     * @param  string $fileContents
     * @return mixed
     */
    public function replaceFileContents(string $file, string $fileContents): mixed;

    /**
     * Delete
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile(string $filename): void;

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
     * @return int|bool
     */
    public function getFileSize(string $filename): int|bool;

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string|bool
     */
    public function getFileType(string $filename): string|bool;

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|bool
     */
    public function getFileMTime(string $filename): int|bool;

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File(string $filename): string;

    /**
     * Load file lines into array
     *
     * @param  string $filename
     * @throws Exception
     * @return array
     */
    public function loadFile(string $filename): array;

    /**
     * Get file contents
     *
     * @param  string $filename
     * @throws Exception
     * @return array
     */
    public function getFileContents(string $filename): mixed;

}