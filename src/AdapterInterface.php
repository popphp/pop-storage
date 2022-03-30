<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1
 */
interface AdapterInterface
{

    /**
     * Is storage local
     *
     * @return boolean
     */
    public function isLocal();

    /**
     * Set storage location
     *
     * @param  string $location
     * @return AbstractAdapter
     */
    public function setLocation($location);

    /**
     * Get storage location
     *
     * @return string
     */
    public function getLocation();

    /**
     * Fetch file
     *
     * @param  string $filename
     * @return void
     */
    public function fetchFile($filename);

    /**
     * Upload file
     *
     * @param  array   $file
     * @param  Upload  $upload
     * @param  string  $to
     * @param  boolean $secure
     * @return string
     */
    public function uploadFile(array $file, Upload $upload, $to = null, $secure = true);

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $contents
     * @return void
     */
    public function replaceFile($filename, $contents);

    /**
     * Delete
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile($filename);

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir($dir);

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir($dir);

    /**
     * Copy file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function copyFile($filename, $to);

    /**
     * Rename file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function renameFile($filename, $to);

    /**
     * File exists
     *
     * @param  string $filename
     * @return boolean
     */
    public function fileExists($filename);

    /**
     * Check if file is a file
     *
     * @param  string $filename
     * @return boolean
     */
    public function isFile($filename);

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int
     */
    public function getFileSize($filename);

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string
     */
    public function getFileType($filename);

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int
     */
    public function getFileMTime($filename);

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File($filename);

    /**
     * Load file lines into array
     *
     * @param  string $filename
     * @return array
     */
    public function loadFile($filename);

}