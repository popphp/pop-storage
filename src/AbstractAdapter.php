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
 * Abstract storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Is local flag
     * @var bool
     */
    protected bool $local = false;

    /**
     * Storage location
     * @var ?string
     */
    protected ?string $location = null;

    /**
     * Storage base location
     * @var ?string
     */
    protected ?string $baseLocation = null;

    /**
     * Constructor
     *
     * @param string $location
     */
    public function __construct(string $location)
    {
        $this->setLocation($location);
    }

    /**
     * Is storage local
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * Set storage location
     *
     * @param  string $location
     * @return AbstractAdapter
     */
    public function setLocation(string $location): AbstractAdapter
    {
        $this->baseLocation = $location;
        $this->location     = $location;
        return $this;
    }

    /**
     * Get storage location
     *
     * @return ?string
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * List directories
     *
     * @return array
     */
    abstract public function listDirs(): array;

    /**
     * List files
     *
     * @return array
     */
    abstract public function listFiles(): array;

    /**
     * Fetch file
     *
     * @param string $filename
     * @throws Exception
     * @return string|bool
     */
    public function fetchFile(string $filename): string|bool
    {
        return file_get_contents($this->checkFileLocation($filename));
    }

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  ?string $dest
     * @param  ?Upload $upload
     * @return string
     */
    abstract public function uploadFile(mixed $file, ?string $dest = null, ?Upload $upload = null): string;

    /**
     * Upload file stream
     *
     * @param  string  $fileStream
     * @param  string  $filename
     * @param  ?string $folder
     * @return string
     */
    abstract public function uploadFileStream(string $fileStream, string $filename, ?string $folder = null): string;

    /**
     * Put file
     *
     * @param  string $filename
     * @return void
     */
    abstract public function putFile(string $filename): void;

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $contents
     * @throws Exception
     * @return void
     */
    public function replaceFile(string $filename, string $contents): void
    {
        file_put_contents($this->checkFileLocation($filename), $contents);
    }

    /**
     * Delete
     *
     * @param  string $filename
     * @throws Exception
     * @return void
     */
    public function deleteFile(string $filename): void
    {
        if ($this->fileExists($filename)) {
            unlink($this->checkFileLocation($filename));
        }
    }

    /**
     * Change directory (location)
     *
     * @param  ?string $dir
     * @return void
     */
    abstract public function chdir(?string $dir = null): void;

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    abstract public function rmdir(string $dir): void;

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    abstract public function mkdir(string $dir): void;

    /**
     * Copy file
     *
     * @param  string $filename
     * @param  string $to
     * @throws Exception
     * @return void
     */
    public function copyFile(string $filename, string $to): void
    {
        copy($this->checkFileLocation($filename), $this->location . $to);
    }

    /**
     * Rename file
     *
     * @param  string $filename
     * @param  string $to
     * @throws Exception
     * @return void
     */
    public function renameFile(string $filename, string $to): void
    {
        rename($this->checkFileLocation($filename), $this->location . $to);
    }

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool
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
     * @throws Exception
     * @return bool
     */
    public function isFile(string $filename): bool
    {
        return is_file($this->checkFileLocation($filename));
    }

    /**
     * Get file size
     *
     * @param  string $filename
     * @throws Exception
     * @return int|bool
     */
    public function getFileSize(string $filename): int|bool
    {
        return filesize($this->checkFileLocation($filename));
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @throws Exception
     * @return string|bool
     */
    public function getFileType(string $filename): string|bool
    {
        return filetype($this->checkFileLocation($filename));
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @throws Exception
     * @return int|bool
     */
    public function getFileMTime(string $filename): int|bool
    {
        return filemtime($this->checkFileLocation($filename));
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    abstract public function md5File(string $filename): string;

    /**
     * Load file lines into array
     *
     * @param  string $filename
     * @throws Exception
     * @return array
     */
    public function loadFile(string $filename): array
    {
        if (!file_exists($filename)) {
            $filename = $this->location . $filename;
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
    protected function checkFileLocation(string $filename): string
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