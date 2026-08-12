<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\DirectoryNotFoundException;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Storage;

class LocalExceptionsTest extends TestCase
{

    public function testFetchFileThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->fetchFile('does-not-exist.txt');
    }

    public function testDeleteFileThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->deleteFile('does-not-exist.txt');
    }

    public function testGetFileSizeThrowsFileNotFoundExceptionAndReturnsInt()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');
        file_put_contents(__DIR__ . '/../tmp/sized.txt', 'four');
        $this->assertSame(4, $storage->getFileSize('sized.txt'));
        unlink(__DIR__ . '/../tmp/sized.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->getFileSize('does-not-exist.txt');
    }

    public function testRmdirThrowsDirectoryNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(DirectoryNotFoundException::class);
        $storage->rmdir('does-not-exist-dir');
    }

    public function testUploadFileWithInvalidArrayThrowsUnableToWriteFileException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(UnableToWriteFileException::class);
        $storage->uploadFile(['not' => 'valid']);
    }

    public function testUploadFileRejectsPathTraversalInAttackerControlledFileName()
    {
        // $file['name'] comes straight from $_FILES and is fully attacker-controlled, so it
        // must be scrubbed like every other path argument. tmp2/ is a sibling of the storage
        // directory - a successful traversal would land a file there, outside of tmp/.
        $storage = Storage::createLocal(__DIR__ . '/../tmp');
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-upload-');
        file_put_contents($tmpFile, '<?php /* malicious */');
        $escapedFile = __DIR__ . '/../tmp2/shell.php';

        $thrown = null;

        try {
            $storage->uploadFile(['tmp_name' => $tmpFile, 'name' => '../tmp2/shell.php']);
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        $escaped = file_exists($escapedFile);

        if ($escaped) {
            unlink($escapedFile);
        }
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }

        $this->assertFalse($escaped, 'uploadFile() wrote outside of the storage directory.');
        $this->assertInstanceOf(PathTraversalException::class, $thrown);
    }

    public function testCopyFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFile('does-not-exist.txt', 'dest.txt');
    }

    public function testCopyFileToExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFileToExternal('does-not-exist.txt', __DIR__ . '/../tmp2/dest.txt');
    }

    public function testCopyFileFromExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFileFromExternal(__DIR__ . '/../tmp2/does-not-exist.txt', 'dest.txt');
    }

    public function testMoveFileToExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->moveFileToExternal('does-not-exist.txt', __DIR__ . '/../tmp2/dest.txt');
    }

    public function testMoveFileFromExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->moveFileFromExternal(__DIR__ . '/../tmp2/does-not-exist.txt', 'dest.txt');
    }

    public function testRenameFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->renameFile('does-not-exist.txt', 'dest.txt');
    }

    public function testReplaceFileContentsThrowsFileNotFoundExceptionForMissingTarget()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->replaceFileContents('does-not-exist.txt', 'new contents');
    }

    public function testGetFileTypeThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->getFileType('does-not-exist.txt');
    }

    public function testGetFileMTimeThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->getFileMTime('does-not-exist.txt');
    }

    public function testMd5FileThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->md5File('does-not-exist.txt');
    }

    public function testPutFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->putFile(__DIR__ . '/../tmp2/does-not-exist.txt');
    }

}
