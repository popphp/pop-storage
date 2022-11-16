<?php

namespace Pop\Storage\Test;

use PHPUnit\Framework\TestCase;
use Pop\Storage;
use Pop\Http\Server\Upload;

class StorageLocalTest extends TestCase
{

    public function testConstructor()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertInstanceOf('Pop\Storage\Local', $local);
    }

    public function testIsLocal()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertTrue($local->isLocal());
    }

    public function testSetAndGetLocation()
    {
        $local = new Storage\Local(__DIR__ . '/tmp2/');
        $this->assertEquals(__DIR__ . '/tmp2/', $local->getLocation());
        $local->setLocation(__DIR__ . '/tmp/');
        $this->assertEquals(__DIR__ . '/tmp/', $local->getLocation());
    }

    public function testFileExists()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertFalse($local->fileExists('test.txt'));
        file_put_contents(__DIR__ . '/tmp/test.txt', 'test');
        $this->assertTrue($local->fileExists('test.txt'));
    }

    public function testFetchFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertEquals('test', $local->fetchFile('test.txt'));
    }

    public function testCopyFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->copyFile('test.txt', 'test2.txt');
        $this->assertTrue($local->fileExists('test2.txt'));
    }

    public function testRenameFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->renameFile('test.txt', 'test1.txt');
        $this->assertTrue($local->fileExists('test1.txt'));
    }

    public function testReplaceFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->replaceFile('test1.txt', '123456');
        $this->assertEquals('123456', $local->fetchFile('test1.txt'));
    }

    public function testIsFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertTrue($local->isFile('test1.txt'));
    }

    public function testGetFileSize()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertGreaterThan(0, $local->getFileSize('test1.txt'));
    }

    public function testGetFileMTime()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertGreaterThan(time() - 1000, $local->getFileMTime('test1.txt'));
    }

    public function testGetFiletype()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertEquals('file', $local->getFileType('test1.txt'));
    }

    public function testLoadFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertEquals(['123456'], $local->loadFile('test1.txt'));
    }

    public function testLoadFileException()
    {
        $this->expectException('Pop\Storage\Exception');
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertEquals(['123456'], $local->loadFile('bad_test.txt'));
    }

    public function testMd5File()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertEquals('e10adc3949ba59abbe56e057f20f883e', $local->md5File('test1.txt'));
    }

    public function testMkDir()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->mkdir('test');
        $this->assertDirectoryExists(__DIR__ . '/tmp/test');
    }

    public function testRmDir()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->rmdir('test');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/tmp/test');
    }

    public function testUploadFile()
    {
        file_put_contents(__DIR__ . '/tmp/uploaded.txt', 'uploaded');

        $file = [
            'tmp_name' => __DIR__ . '/tmp/uploaded.txt',
            'name'     => 'uploaded.txt',
            'size'     => 8,
            'error'    => 0
        ];

        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->uploadFile($file, 'new-uploaded-file.txt', new Upload(__DIR__ . '/tmp'), false);
        $this->assertTrue($local->fileExists('new-uploaded-file.txt'));
        $this->assertEquals('uploaded', $local->fetchFile('new-uploaded-file.txt'));
        $local->deleteFile('uploaded.txt');
        $local->deleteFile('new-uploaded-file.txt');
    }

    public function testUploadFileException()
    {
        $this->expectException('Pop\Storage\Exception');
        $file  = 'bad.txt';
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->uploadFile($file, 'new-uploaded-file.txt', new Upload(__DIR__ . '/tmp'), false);
    }

    public function testUploadFileStream()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $local->uploadFileStream('0123456789', 'new-file.txt', 'foo');
        $this->assertTrue($local->fileExists('foo/new-file.txt'));
        $this->assertEquals('0123456789', $local->fetchFile('foo/new-file.txt'));
        $local->rmDir('foo');
    }

    public function testDeleteFile()
    {
        $local = new Storage\Local(__DIR__ . '/tmp/');
        $this->assertTrue($local->fileExists('test1.txt'));
        $this->assertTrue($local->fileExists('test2.txt'));
        $local->deleteFile('test1.txt');
        $local->deleteFile('test2.txt');
        $this->assertFalse($local->fileExists('test1.txt'));
        $this->assertFalse($local->fileExists('test2.txt'));
    }

}
