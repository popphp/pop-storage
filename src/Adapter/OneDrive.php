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

use Aws\S3\S3Client;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Storage adapter S3 class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
class OneDrive extends AbstractAdapter
{

    public function mkdir(string $directory): void
    {
        // TODO: Implement mkdir() method.
    }

    public function rmdir(string $directory): void
    {
        // TODO: Implement rmdir() method.
    }

    public function listDirs(?string $search = null): array
    {
        // TODO: Implement listDirs() method.
    }

    public function listFiles(?string $search = null): array
    {
        // TODO: Implement listFiles() method.
    }

    public function putFile(string $fileFrom, bool $copy = true): void
    {
        // TODO: Implement putFile() method.
    }

    public function putFileContents(string $filename, string $fileContents): void
    {
        // TODO: Implement putFileContents() method.
    }

    public function uploadFile(array $file): void
    {
        // TODO: Implement uploadFile() method.
    }

    public function copyFile(string $sourceFile, string $destFile): void
    {
        // TODO: Implement copyFile() method.
    }

    public function copyFileToExternal(string $sourceFile, string $externalFile): void
    {
        // TODO: Implement copyFileToExternal() method.
    }

    public function copyFileFromExternal(string $externalFile, string $destFile): void
    {
        // TODO: Implement copyFileFromExternal() method.
    }

    public function moveFileToExternal(string $sourceFile, string $externalFile): void
    {
        // TODO: Implement moveFileToExternal() method.
    }

    public function moveFileFromExternal(string $externalFile, string $destFile): void
    {
        // TODO: Implement moveFileFromExternal() method.
    }

    public function renameFile(string $oldFile, string $newFile): void
    {
        // TODO: Implement renameFile() method.
    }

    public function replaceFileContents(string $filename, string $fileContents): void
    {
        // TODO: Implement replaceFileContents() method.
    }

    public function deleteFile(string $filename): void
    {
        // TODO: Implement deleteFile() method.
    }

    public function fetchFile(string $filename): mixed
    {
        // TODO: Implement fetchFile() method.
    }

    public function fetchFileInfo(string $filename): array
    {
        // TODO: Implement fetchFileInfo() method.
    }

    public function fileExists(string $filename): bool
    {
        // TODO: Implement fileExists() method.
    }

    public function isDir(string $directory): bool
    {
        // TODO: Implement isDir() method.
    }

    public function isFile(string $filename): bool
    {
        // TODO: Implement isFile() method.
    }

    public function getFileSize(string $filename): int|bool
    {
        // TODO: Implement getFileSize() method.
    }

    public function getFileType(string $filename): string|bool
    {
        // TODO: Implement getFileType() method.
    }

    public function getFileMTime(string $filename): int|string|bool
    {
        // TODO: Implement getFileMTime() method.
    }

    public function md5File(string $filename): string|bool
    {
        // TODO: Implement md5File() method.
    }
}
