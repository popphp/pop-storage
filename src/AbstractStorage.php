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

use Pop\Storage\Adapter\AbstractAdapter;

/**
 * Storage abstract class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
abstract class AbstractStorage implements StorageInterface
{

    /**
     * Storage adapter
     * @var ?AbstractAdapter
     */
    protected ?AbstractAdapter $adapter = null;

    /**
     * Constructor
     *
     * @param AbstractAdapter $adapter
     */
    public function __construct(AbstractAdapter $adapter)
    {
        $this->setAdapter($adapter);
    }

    /**
     * Set adapter
     *
     * @param  AbstractAdapter $adapter
     * @return AbstractStorage
     */
    public function setAdapter(AbstractAdapter $adapter): AbstractStorage
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get adapter
     *
     * @return ?AbstractAdapter
     */
    public function getAdapter(): ?AbstractAdapter
    {
        return $this->adapter;
    }

    /**
     * Get adapter (alias)
     *
     * @return ?AbstractAdapter
     */
    public function adapter(): ?AbstractAdapter
    {
        return $this->adapter;
    }

    /**
     * Set base directory
     *
     * @param  ?string $directory
     * @return void
     */
    abstract public function setBaseDir(?string $directory = null): void;

    /**
     * Get base directory
     *
     * @return ?string
     */
    abstract public function getBaseDir(): ?string;

    /**
     * Get current directory
     *
     * @return ?string
     */
    abstract public function getCurrentDir(): ?string;

    /**
     * Change directory
     *
     * @param  ?string $directory
     * @return void
     */
    abstract public function chdir(?string $directory = null): void;

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
     * Upload file from server request
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
     * @return int|bool
     */
    abstract public function getFileMTime(string $filename): int|bool;

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string|bool
     */
    abstract public function md5File(string $filename): string|bool;

}