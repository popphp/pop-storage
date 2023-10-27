<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;
use Pop\Storage\Adapter\Azure;

class AzureTest extends TestCase
{

    protected $storage = null;
    
    protected $storage2 = null;

    /**
     * @group skip
     */
    public function setUp(): void
    {
        $this->storage  = Storage::createAzure($_ENV['AZURE_NAME'], $_ENV['AZURE_KEY'], $_ENV['AZURE_CONTAINER']);
        $this->storage2 = Storage::createAzure($_ENV['AZURE_NAME'], $_ENV['AZURE_KEY'], $_ENV['AZURE_CONTAINER_ALT']);
    }

    /**
     * @group skip
     */
    public function testConstructor()
    {
        $this->assertInstanceOf('Pop\Storage\Storage', $this->storage);
    }

    /**
     * @group skip
     */
    public function testGetClient()
    {
        $this->assertTrue($this->storage->adapter()->hasClient());
        $this->assertInstanceOf('Pop\Http\Client', $this->storage->adapter()->getClient());
    }

    /**
     * @group skip
     */
    public function testGetAuth()
    {
        $this->assertTrue($this->storage->adapter()->hasAuth());
        $this->assertTrue($this->storage->adapter()->getAuth()->hasAccountName());
        $this->assertTrue($this->storage->adapter()->getAuth()->hasAccountKey());
        $this->assertInstanceOf('Pop\Storage\Adapter\Azure\Auth', $this->storage->adapter()->getAuth());
        $this->assertNotEmpty($this->storage->adapter()->getAuth()->getAccountName());
        $this->assertNotEmpty($this->storage->adapter()->getAuth()->getAccountKey());
    }

    /**
     * @group skip
     */
    public function testMkDir()
    {
        $this->storage->mkdir('bad');
        $this->assertInstanceOf('Pop\Storage\Storage', $this->storage);
    }

    /**
     * @group skip
     */
    public function testRmDir()
    {
        $this->storage->rmdir('bad');
        $this->assertInstanceOf('Pop\Storage\Storage', $this->storage);
    }

    /**
     * @group skip
     */
    public function testFileExists()
    {
        $this->assertFalse($this->storage->fileExists('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testPutFile()
    {
        file_put_contents(__DIR__ . '/../tmp/uploaded.txt', 'uploaded');
        $this->storage->putFile(__DIR__ . '/../tmp/uploaded.txt');
        $this->assertTrue($this->storage->fileExists('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testCopyFile()
    {
        $this->storage->copyFile('uploaded.txt', 'uploaded-copy.txt');
        $this->assertTrue($this->storage->fileExists('uploaded-copy.txt'));
        $this->storage->deleteFile('uploaded-copy.txt');
        $this->assertFalse($this->storage->fileExists('uploaded-copy.txt'));
    }

    /**
     * @group skip
     */
    public function testCopyFileToExternal()
    {
        file_put_contents(__DIR__ . '/../tmp/foo.txt', 123);
        $this->storage->putFile(__DIR__ . '/../tmp/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage->fileExists('foo.txt'));
        $this->storage->copyFileToExternal('foo.txt', '/' . $_ENV['AZURE_CONTAINER_ALT'] . '/foo.txt');
        $this->assertTrue($this->storage2->fileExists('foo.txt'));
        $this->storage->deleteFile('foo.txt');
        $this->storage2->deleteFile('foo.txt');
        $this->assertFalse($this->storage->fileExists('foo.txt'));
        $this->assertFalse($this->storage2->fileExists('foo.txt'));
        unlink(__DIR__ . '/../tmp/foo.txt');
    }

    /**
     * @group skip
     */
    public function testMoveFileToExternal()
    {
        file_put_contents(__DIR__ . '/../tmp/foo.txt', 123);
        $this->storage->putFile(__DIR__ . '/../tmp/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage->fileExists('foo.txt'));
        $this->storage->moveFileToExternal('foo.txt', '/'. $_ENV['AZURE_CONTAINER_ALT'] . '/foo.txt');
        $this->assertTrue($this->storage2->fileExists('foo.txt'));
        $this->assertFalse($this->storage->fileExists('foo.txt'));
        $this->storage2->deleteFile('foo.txt');
        $this->assertFalse($this->storage->fileExists('foo.txt'));
        $this->assertFalse($this->storage2->fileExists('foo.txt'));
        unlink(__DIR__ . '/../tmp/foo.txt');
    }

    /**
     * @group skip
     */
    public function testCopyFileFromExternal()
    {
        file_put_contents(__DIR__ . '/../tmp/foo.txt', 123);
        $this->storage2->putFile(__DIR__ . '/../tmp/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage2->fileExists('foo.txt'));
        $this->storage->copyFileFromExternal('/'. $_ENV['AZURE_CONTAINER_ALT'] . '/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage->fileExists('foo.txt'));
        $this->storage->deleteFile('foo.txt');
        $this->storage2->deleteFile('foo.txt');
        $this->assertFalse($this->storage->fileExists('foo.txt'));
        $this->assertFalse($this->storage2->fileExists('foo.txt'));
        unlink(__DIR__ . '/../tmp/foo.txt');
    }

    /**
     * @group skip
     */
    public function testMoveFileFromExternal()
    {
        file_put_contents(__DIR__ . '/../tmp/foo.txt', 123);
        $this->storage2->putFile(__DIR__ . '/../tmp/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage2->fileExists('foo.txt'));
        $this->storage->moveFileFromExternal('/'. $_ENV['AZURE_CONTAINER_ALT'] . '/foo.txt', 'foo.txt');
        $this->assertTrue($this->storage->fileExists('foo.txt'));
        $this->assertFalse($this->storage2->fileExists('foo.txt'));
        $this->storage->deleteFile('foo.txt');
        $this->assertFalse($this->storage->fileExists('foo.txt'));
        $this->assertFalse($this->storage2->fileExists('foo.txt'));
        unlink(__DIR__ . '/../tmp/foo.txt');
    }

    /**
     * @group skip
     */
    public function testRenameFile()
    {
        $this->storage->renameFile('uploaded.txt', 'uploaded-moved.txt');
        $this->assertTrue($this->storage->fileExists('uploaded-moved.txt'));
        $this->assertFalse($this->storage->fileExists('uploaded.txt'));
        $this->storage->renameFile('uploaded-moved.txt', 'uploaded.txt');
        $this->assertFalse($this->storage->fileExists('uploaded-moved.txt'));
        $this->assertTrue($this->storage->fileExists('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testReplaceFileContents()
    {
        $this->assertEquals('uploaded', $this->storage->fetchFile('uploaded.txt'));
        $this->storage->replaceFileContents('uploaded.txt', 'uploaded-new');
        $this->assertEquals('uploaded-new', $this->storage->fetchFile('uploaded.txt'));
        $this->storage->replaceFileContents('uploaded.txt', 'uploaded');
        $this->assertEquals('uploaded', $this->storage->fetchFile('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testIsFile()
    {
        $this->assertTrue($this->storage->isFile('uploaded.txt'));
        $this->assertFalse($this->storage->isDir('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testFetchFile()
    {
        $this->assertEquals('uploaded', $this->storage->fetchFile('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testFileInfo()
    {
        $info = $this->storage->fetchFileInfo('uploaded.txt');
        $this->assertTrue(is_array($info));
        $this->assertEquals('file', $this->storage->getFileType('uploaded.txt'));
        $this->assertNotEmpty($this->storage->getFileSize('uploaded.txt'));
        $this->assertFalse($this->storage->getFileMTime('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testFileInfoNoFile()
    {
        $info = $this->storage->fetchFileInfo('bad.txt');
        $this->assertEquals(404, $info['code']);
    }

    /**
     * @group skip
     */
    public function testPutFileContents()
    {
        $this->storage->putFileContents('uploaded-2.txt', 'uploaded2');
        $this->assertTrue($this->storage->fileExists('uploaded-2.txt'));
        $this->storage->deleteFile('uploaded-2.txt');
        $this->assertFalse($this->storage->fileExists('uploaded-2.txt'));
    }

    /**
     * @group skip
     */
    public function testUploadFile()
    {
        file_put_contents(__DIR__ . '/uploaded-3.txt', 'uploaded');
        $file = [
            'tmp_name' => __DIR__ . '/uploaded-3.txt',
            'name'     => 'uploaded-3.txt',
            'size'     => 8,
            'error'    => 0
        ];
        $this->storage->uploadFile($file);
        $this->assertTrue($this->storage->fileExists('uploaded-3.txt'));
        $this->storage->deleteFile('uploaded-3.txt');
        $this->assertFalse($this->storage->fileExists('uploaded-3.txt'));
    }

    /**
     * @group skip
     */
    public function testUploadFileException()
    {
        $this->expectException('Pop\Storage\Adapter\Exception');
        $file = [
            'size'     => 8,
            'error'    => 0
        ];
        $this->storage->uploadFile($file);
    }

    /**
     * @group skip
     */
    public function testMd5File()
    {
        $this->assertTrue(is_string($this->storage->md5File('/uploaded.txt')));
    }

    /**
     * @group skip
     */
    public function testMd5FileNoFile()
    {
        $this->assertFalse($this->storage->md5File('/bad.txt'));
    }

    /**
     * @group skip
     */
    public function testListFiles()
    {
        $this->assertTrue(is_array($this->storage->listFiles()));
    }

    /**
     * @group skip
     */
    public function testDeleteFile()
    {
        $this->assertTrue($this->storage->fileExists('uploaded.txt'));
        $this->storage->deleteFile('uploaded.txt');
        $this->assertFalse($this->storage->fileExists('uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testIsDir()
    {
        $this->storage->putFileContents('foo/test.txt', 'Hello World');
        $this->assertTrue($this->storage->fileExists('foo/test.txt'));
        $this->assertTrue($this->storage->isDir('foo'));
        $this->assertFalse($this->storage->isFile('foo'));
    }

    /**
     * @group skip
     */
    public function testGetFileType()
    {
        $this->assertEquals('dir', $this->storage->getFileType('foo'));
        $this->assertFalse($this->storage->getFileType('bad'));
    }

    /**
     * @group skip
     */
    public function testListDirs()
    {
        $this->assertTrue(is_array($this->storage->listDirs()));
        $this->storage->deleteFile('foo/test.txt');
        $this->assertFalse($this->storage->fileExists('foo/test.txt'));
    }


}