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
namespace Pop\Storage;

use Pop\Http\Server\Upload;

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
     * Is storage local
     *
     * @return bool
     */
    public function isLocal(): bool;

    /**
     * Set storage location
     *
     * @param  string $location
     * @return AdapterInterface
     */
    public function setLocation(string $location): AdapterInterface;

    /**
     * Get storage location
     *
     * @return ?string
     */
    public function getLocation(): ?string;

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
     * @param  string $filename
     * @return string|bool
     */
    public function fetchFile(string $filename): string|bool;

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  ?string $dest
     * @param  ?Upload $upload
     * @return string
     */
    public function uploadFile(mixed $file, ?string $dest = null, ?Upload $upload = null): string;

    /**
     * Upload file
     *
     * @param  string  $fileStream
     * @param  string  $filename
     * @param  ?string $folder
     * @return string
     */
    public function uploadFileStream(string $fileStream, string $filename, ?string $folder = null): string;

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $contents
     * @return void
     */
    public function replaceFile(string $filename, string $contents): void;

    /**
     * Delete
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile(string $filename): void;

    /**
     * Change directory (location)
     *
     * @param  ?string $dir
     * @return void
     */
    public function chdir(?string $dir = null): void;

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir(string $dir): void;

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir(string $dir): void;

    /**
     * Copy file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function copyFile(string $filename, string $to): void;

    /**
     * Rename file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function renameFile(string $filename, string $to): void;

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool;

    /**
     * Check if file is a file
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
     * @return array
     */
    public function loadFile(string $filename): array;

}