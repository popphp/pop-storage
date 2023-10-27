<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;

class LocalTest extends TestCase
{

    public function testConstructor()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Storage\Storage', $storage);
    }

    public function testPutFileCopy()
    {
        file_put_contents(__DIR__ . '/test3.txt', 'test');

        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->putFile(__DIR__ . '/test3.txt');
        $this->assertTrue($storage->fileExists('test3.txt'));
        $this->assertFileExists(__DIR__ . '/test3.txt');
        $storage->deleteFile('test3.txt');
        unlink(__DIR__ . '/test3.txt');
    }

    public function testPutFileMove()
    {
        file_put_contents(__DIR__ . '/test4.txt', 'test');

        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->putFile(__DIR__ . '/test4.txt', false);
        $this->assertTrue($storage->fileExists('test4.txt'));
        $this->assertFileDoesNotExist(__DIR__ . '/test4.txt');
        $storage->deleteFile('test4.txt');
    }

    public function testFileExists()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertFalse($storage->fileExists('test.txt'));
        file_put_contents(__DIR__ . '/../tmp/test.txt', 'test');
        $this->assertTrue($storage->fileExists('test.txt'));
    }

    public function testFetchFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertEquals('test', $storage->fetchFile('test.txt'));
    }

    public function testCopyFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->copyFile('test.txt', 'test2.txt');
        $this->assertTrue($storage->fileExists('test2.txt'));
    }

    public function testRenameFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->renameFile('test.txt', 'test1.txt');
        $this->assertTrue($storage->fileExists('test1.txt'));
    }

    public function testIsFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertTrue($storage->isFile('test1.txt'));
    }

    public function testGetFileSize()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertGreaterThan(0, $storage->getFileSize('test1.txt'));
    }

    public function testGetFileMTime()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertGreaterThan(time() - 1000, $storage->getFileMTime('test1.txt'));
    }

    public function testGetFiletype()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertEquals('file', $storage->getFileType('test1.txt'));
    }

    public function testMd5File()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertEquals('098f6bcd4621d373cade4e832627b4f6', $storage->md5File('test1.txt'));
    }

    public function testMkDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->mkdir('test');
        $this->assertDirectoryExists(__DIR__ . '/../tmp/test');
    }

    public function testRmDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->rmdir('test');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../tmp/test');
    }

    public function testUploadFile()
    {
        file_put_contents(__DIR__ . '/../tmp/uploaded.txt', 'uploaded');

        $file = [
            'tmp_name' => __DIR__ . '/../tmp/uploaded.txt',
            'name'     => 'new-uploaded-file.txt',
            'size'     => 8,
            'error'    => 0
        ];

        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->uploadFile($file);
        $this->assertTrue($storage->fileExists('new-uploaded-file.txt'));
        $this->assertEquals('uploaded', $storage->fetchFile('new-uploaded-file.txt'));
        $storage->deleteFile('uploaded.txt');
        $storage->deleteFile('new-uploaded-file.txt');
    }

    public function testUploadFileException()
    {
        $this->expectException('Pop\Storage\Adapter\Exception');
        $file = [
            'size'     => 8,
            'error'    => 0
        ];

        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $storage->uploadFile($file);

    }

    public function testDeleteFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/');
        $this->assertTrue($storage->fileExists('test1.txt'));
        $this->assertTrue($storage->fileExists('test2.txt'));
        $storage->deleteFile('test1.txt');
        $storage->deleteFile('test2.txt');
        $this->assertFalse($storage->fileExists('test1.txt'));
        $this->assertFalse($storage->fileExists('test2.txt'));
    }

}
