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

use Aws\S3\S3Client;

/**
 * Storage  class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Storage extends AbstractStorage
{

    /**
     * Create storage object with local adapter
     *
     * @param  string $directory
     * @return Storage
     */
    public static function createLocal(string $directory): Storage
    {
        return new self(new Adapter\Local($directory));
    }

    /**
     * Create storage object with S3 adapter
     *
     * @param  string   $directory
     * @param  S3Client $client
     * @return Storage
     */
    public static function createS3(string $directory, S3Client $client): Storage
    {
        return new self(new Adapter\S3($directory, $client));
    }

    /**
     * Create storage object with S3 adapter
     *
     * @param  string $accountName
     * @param  string $accountKey
     * @param  ?string $container
     * @throws \Pop\Http\Client\Exception
     * @return Storage
     */
    public static function createAzure(string $accountName, string $accountKey, ?string $container = null): Storage
    {
        $azure = new self(Adapter\Azure::create($accountName, $accountKey));
        if ($container != null) {
            $azure->chdir($container);
        }
        return $azure;
    }

    /**
     * Set base directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function setBaseDir(?string $directory = null): void
    {
        $this->adapter->setBaseDir($directory);
    }

    /**
     * Get base directory
     *
     * @return ?string
     */
    public function getBaseDir(): ?string
    {
        return $this->adapter->getBaseDir();
    }

    /**
     * Get current directory
     *
     * @return ?string
     */
    public function getCurrentDir(): ?string
    {
        return $this->adapter->getCurrentDir();
    }

    /**
     * Change directory
     *
     * @param  ?string $directory
     * @return void
     */
    public function chdir(?string $directory = null): void
    {
        $this->adapter->chdir($directory);
    }

    /**
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    public function mkdir(string $directory): void
    {
        $this->adapter->mkdir($directory);
    }

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @return void
     */
    public function rmdir(string $directory): void
    {
        $this->adapter->rmdir($directory);
    }

    /**
     * List directories
     *
     * @return array
     */
    public function listDirs(): array
    {
        return $this->adapter->listDirs();
    }

    /**
     * List files
     *
     * @return array
     */
    public function listFiles(): array
    {
        return $this->adapter->listFiles();
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
        $this->adapter->putFile($fileFrom, $copy);
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
        $this->adapter->putFileContents($filename, $fileContents);
    }

    /**
     * Upload files from server request $_FILES
     *
     * @param  array $files
     * @return void
     */
    public function uploadFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->adapter->uploadFile($file);
        }
    }

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @return void
     */
    public function uploadFile(array $file): void
    {
        $this->adapter->uploadFile($file);
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
        $this->adapter->copyFile($sourceFile, $destFile);
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
        $this->adapter->renameFile($oldFile, $newFile);
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
        $this->adapter->replaceFileContents($filename, $fileContents);
    }

    /**
     * Delete file
     *
     * @param  string $filename
     * @return void
     */
    public function deleteFile(string $filename): void
    {
        $this->adapter->deleteFile($filename);
    }

    /**
     * Fetch file contents
     *
     * @param  string $filename
     * @return mixed
     */
    public function fetchFile(string $filename): mixed
    {
        return $this->adapter->fetchFile($filename);
    }

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array
    {
        return $this->adapter->fetchFileInfo($filename);
    }

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool
    {
        return $this->adapter->fileExists($filename);
    }

    /**
     * Check if is a dir
     *
     * @param  string $directory
     * @return bool
     */
    public function isDir(string $directory): bool
    {
        return $this->adapter->isDir($directory);
    }

    /**
     * Check if is a file
     *
     * @param  string $filename
     * @return bool
     */
    public function isFile(string $filename): bool
    {
        return $this->adapter->isFile($filename);
    }

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int|bool
     */
    public function getFileSize(string $filename): int|bool
    {
        return $this->adapter->getFileSize($filename);
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string|bool
     */
    public function getFileType(string $filename): string|bool
    {
        return $this->adapter->getFileType($filename);
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|string|bool
     */
    public function getFileMTime(string $filename): int|string|bool
    {
        return $this->adapter->getFileMTime($filename);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string|bool
     */
    public function md5File(string $filename): string|bool
    {
        return $this->adapter->md5File($filename);
    }
    
}