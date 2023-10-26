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
 * Storage adapter abstract class
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
            if (str_starts_with($directory, '/') || str_starts_with($directory, '\\')) {
                $directory = substr($directory, 1);
            } else if (str_starts_with($directory, './') || str_starts_with($directory, '.\\')) {
                $directory = substr($directory, 2);
            }
            $this->directory = $this->baseDirectory . DIRECTORY_SEPARATOR . $directory;
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
     * @param  string $file
     * @return mixed
     */
    abstract public function fetchFile(string $file): mixed;

    /**
     * Put file
     *
     * @param  string $file
     * @return void
     */
    abstract public function putFile(string $file): void;

    /**
     * Put file contents
     *
     * @param  string $file
     * @param  string $fileContents
     * @return void
     */
    abstract public function putFileContents(string $file, string $fileContents): void;

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return mixed
     */
    abstract public function renameFile(string $oldFile, string $newFile): mixed;

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return mixed
     */
    abstract public function copyFile(string $sourceFile, string $destFile): mixed;

    /**
     * Replace file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return mixed
     */
    abstract public function replaceFile(string $oldFile, string $newFile): mixed;

    /**
     * Replace file
     *
     * @param  string $file
     * @param  string $fileContents
     * @return mixed
     */
    abstract public function replaceFileContents(string $file, string $fileContents): mixed;

    /**
     * Delete
     *
     * @param  string $filename
     * @return void
     */
    abstract public function deleteFile(string $filename): void;

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
    abstract public function loadFile(string $filename): array;

    /**
     * Get file contents
     *
     * @param  string $filename
     * @throws Exception
     * @return array
     */
    abstract public function getFileContents(string $filename): mixed;
    
}