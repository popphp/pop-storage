<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage;

use Pop\Storage\Exception;
use Pop\Http\Server\Upload;

/**
 * Abstract storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.0.0
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Is local flag
     * @var boolean
     */
    protected $local = false;

    /**
     * Storage location
     * @var string
     */
    protected $location = null;

    /**
     * Constructor
     *
     * @param string $location
     */
    public function __construct($location)
    {
        $this->setLocation($location);
    }

    /**
     * Is storage local
     *
     * @return boolean
     */
    public function isLocal()
    {
        return $this->local;
    }

    /**
     * Set storage location
     *
     * @param  string $location
     * @return AbstractAdapter
     */
    public function setLocation($location)
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Get storage location
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Fetch file
     *
     * @param  string $filename
     * @return string
     */
    public function fetchFile($filename)
    {
        return file_get_contents($this->checkFileLocation($filename));
    }

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  string  $folder
     * @param  Upload  $upload
     * @return string
     */
    abstract public function uploadFile($file, $folder = null, Upload $upload = null);

    /**
     * Upload file
     *
     * @param  string  $fileStream
     * @param  string  $filename
     * @param  string  $folder
     * @return string
     */
    abstract public function uploadFileStream($fileStream, $filename, $folder = null);

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $contents
     * @return void
     */
    public function replaceFile($filename, $contents)
    {
        file_put_contents($this->checkFileLocation($filename), $contents);
    }

    /**
     * Delete
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile($filename)
    {
        if ($this->fileExists($filename)) {
            unlink($this->checkFileLocation($filename));
        }
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    abstract public function rmdir($dir);

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    abstract public function mkdir($dir);

    /**
     * Copy file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function copyFile($filename, $to)
    {
        copy($this->checkFileLocation($filename), $this->location . $to);
    }

    /**
     * Rename file
     *
     * @param  string $filename
     * @param  string $to
     * @return void
     */
    public function renameFile($filename, $to)
    {
        rename($this->checkFileLocation($filename), $this->location . $to);
    }

    /**
     * File exists
     *
     * @param  string $filename
     * @return boolean
     */
    public function fileExists($filename)
    {
        if (!file_exists($filename)) {
            $filename = $this->location . $filename;
        }
        return file_exists($filename);
    }

    /**
     * Check if file is a file
     *
     * @param  string $filename
     * @return boolean
     */
    public function isFile($filename)
    {
        return is_file($this->checkFileLocation($filename));
    }

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int
     */
    public function getFileSize($filename)
    {
        return filesize($this->checkFileLocation($filename));
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string
     */
    public function getFileType($filename)
    {
        return filetype($this->checkFileLocation($filename));
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int
     */
    public function getFileMTime($filename)
    {
        return filemtime($this->checkFileLocation($filename));
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    abstract public function md5File($filename);

    /**
     * Load file lines into array
     *
     * @param  string $filename
     * @return array
     */
    public function loadFile($filename)
    {
        if (!file_exists($filename)) {
            $filename = $this->location . $filename;
        }
        if (!file_exists($filename)) {
            throw new Exception("Error: The file '" . $filename . "' does not exist.");
        }
        return file($this->checkFileLocation($filename));
    }

    /**
     * Load file lines into array
     *
     * @param  string $filename
     * @throws Exception
     * @return string
     */
    protected function checkFileLocation($filename)
    {
        if (!file_exists($filename)) {
            $filename = $this->location . $filename;
        }
        if (!file_exists($filename)) {
            throw new Exception("Error: The file '" . $filename . "' does not exist.");
        }
        return $filename;
    }

}