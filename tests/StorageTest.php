<?php

namespace Pop\Storage\Test;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;

class StorageTest extends TestCase
{

    public function testConstructor()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertInstanceOf('Pop\Storage\Storage', $storage);
        $this->assertInstanceOf('Pop\Storage\Adapter\Local', $storage->getAdapter());
    }

    public function testSetBaseDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertEquals(__DIR__ . '/tmp/', $storage->getBaseDir());
        $storage->setBaseDir(__DIR__ . '/tmp');
        $this->assertEquals(__DIR__ . '/tmp', $storage->getBaseDir());
        $storage->chdir('foo');
        $this->assertEquals(__DIR__ . '/tmp/foo', $storage->getCurrentDir());
    }

    public function testFileExists()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertFalse($storage->fileExists('test.txt'));
        $storage->putFileContents('test.txt', 'test');
        $this->assertTrue($storage->fileExists('test.txt'));
    }

    public function testFetchFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertEquals('test', $storage->fetchFile('test.txt'));
    }

    public function testFetchFileInfo()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $info    = $storage->fetchFileInfo('test.txt');
        $this->assertTrue(is_array($info));
    }

    public function testListDirs()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $dirs    = $storage->listDirs();
        $this->assertTrue(is_array($dirs));
        $this->assertTrue(in_array('foo', $dirs));
    }

    public function testListFiles()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $files   = $storage->listFiles();
        $this->assertTrue(is_array($files));
        $this->assertTrue(in_array('test.txt', $files));
    }

    public function testUploadFiles()
    {
        file_put_contents(__DIR__ . '/file1.txt', 'file1');
        file_put_contents(__DIR__ . '/file2.txt', 'file2');
        $files = [
            'file1' => [
                'tmp_name' => __DIR__ . '/file1.txt',
                'name'     => 'file1.txt'
            ],
            'file2' => [
                'tmp_name' => __DIR__ . '/file2.txt',
                'name'     => 'file2.txt'
            ],
        ];
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->uploadFiles($files);
        $this->assertTrue($storage->fileExists('file1.txt'));
        $this->assertTrue($storage->fileExists('file2.txt'));
        $storage->deleteFile('file1.txt');
        $storage->deleteFile('file2.txt');
        $this->assertFalse($storage->fileExists('file1.txt'));
        $this->assertFalse($storage->fileExists('file2.txt'));
    }

    public function testCopyFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->copyFile('test.txt', 'test2.txt');
        $this->assertTrue($storage->fileExists('test2.txt'));
    }

    public function testCopyFileToExternal()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->copyFileToExternal('test.txt', __DIR__ . '/tmp2/test.txt');
        $this->assertFileExists(__DIR__ . '/tmp2/test.txt');
        unlink(__DIR__ . '/tmp2/test.txt');
    }

    public function testCopyFileFromExternal()
    {
        file_put_contents(__DIR__ .'/tmp2/foo.txt', 123);
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->copyFileFromExternal(__DIR__ . '/tmp2/foo.txt', 'foo.txt');
        $this->assertTrue($storage->fileExists('foo.txt'));
        $storage->deleteFile('foo.txt');
        unlink(__DIR__ . '/tmp2/foo.txt');
    }

    public function testMoveFileToExternal()
    {
        file_put_contents(__DIR__ .'/tmp/foo1.txt', 123);
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->moveFileToExternal('foo1.txt', __DIR__ . '/tmp2/foo1.txt');
        $this->assertFalse($storage->fileExists('foo1.txt'));
        $this->assertFileExists(__DIR__ . '/tmp2/foo1.txt');
        unlink(__DIR__ . '/tmp2/foo1.txt');
    }

    public function testMoveFileFromExternal()
    {
        file_put_contents(__DIR__ .'/tmp2/foo.txt', 123);
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->moveFileFromExternal(__DIR__ . '/tmp2/foo.txt', 'foo.txt');
        $this->assertFileDoesNotExist(__DIR__ . '/tmp2/foo.txt');
        $this->assertTrue($storage->fileExists('foo.txt'));
        $storage->deleteFile('foo.txt');
    }

    public function testRenameFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->renameFile('test.txt', 'test1.txt');
        $this->assertTrue($storage->fileExists('test1.txt'));
    }

    public function testReplaceFileContents()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertEquals('test', $storage->fetchFile('test1.txt'));
        $storage->replaceFileContents('test1.txt', 'testing');
        $this->assertEquals('testing', $storage->fetchFile('test1.txt'));
    }

    public function testIsFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertTrue($storage->isFile('test1.txt'));
        $this->assertFalse($storage->isDir('test1.txt'));
    }

    public function testIsDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertTrue($storage->isDir('foo'));
        $this->assertFalse($storage->isFile('foo'));
    }

    public function testGetFileSize()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertGreaterThan(0, $storage->getFileSize('test1.txt'));
    }

    public function testGetFileMTime()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertGreaterThan(time() - 1000, $storage->getFileMTime('test1.txt'));
    }

    public function testGetFiletype()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertEquals('file', $storage->getFileType('test1.txt'));
    }

    public function testMd5File()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertEquals('ae2b1fca515949e5d54fb22b8ed95575', $storage->md5File('test1.txt'));
    }

    public function testMkDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->mkdir('test');
        $this->assertDirectoryExists(__DIR__ . '/tmp/test');
    }

    public function testRmDir()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->rmdir('test');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/tmp/test');
    }

    public function testUploadFile()
    {
        file_put_contents(__DIR__ . '/tmp/uploaded.txt', 'uploaded');

        $file = [
            'tmp_name' => __DIR__ . '/tmp/uploaded.txt',
            'name'     => 'new-uploaded-file.txt',
            'size'     => 8,
            'error'    => 0
        ];

        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $storage->uploadFile($file);
        $this->assertTrue($storage->fileExists('new-uploaded-file.txt'));
        $this->assertEquals('uploaded', $storage->fetchFile('new-uploaded-file.txt'));
        $storage->deleteFile('uploaded.txt');
        $storage->deleteFile('new-uploaded-file.txt');
    }

    public function testDeleteFile()
    {
        $storage = Storage::createLocal(__DIR__ . '/tmp/');
        $this->assertTrue($storage->fileExists('test1.txt'));
        $this->assertTrue($storage->fileExists('test2.txt'));
        $storage->deleteFile('test1.txt');
        $storage->deleteFile('test2.txt');
        $this->assertFalse($storage->fileExists('test1.txt'));
        $this->assertFalse($storage->fileExists('test2.txt'));
    }

}
