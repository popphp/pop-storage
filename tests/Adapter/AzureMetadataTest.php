<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\UnableToReadFileException;

/**
 * Azure's metadata getters used to fall through to a silent sentinel (0 or '') when the
 * header they need was missing from an otherwise successful response - exactly the
 * silent-failure pattern the v3.0 exception model exists to eliminate. A 404 is a genuine
 * not-found (FileNotFoundException); a 200 without the expected header means the metadata
 * could not be read (UnableToReadFileException).
 */
class AzureMetadataTest extends TestCase
{

    protected function createAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    protected function adapterRespondingWith(array $headers): Azure
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => $headers]));
        return $this->createAdapter($handler);
    }

    public function testGetFileSizeThrowsWhenContentLengthHeaderIsMissing()
    {
        $adapter = $this->adapterRespondingWith(['x-ms-resource-type' => 'file']);

        $this->expectException(UnableToReadFileException::class);
        $adapter->getFileSize('file.txt');
    }

    public function testGetFileTypeThrowsWhenResourceTypeHeaderIsMissing()
    {
        $adapter = $this->adapterRespondingWith(['Content-Length' => '4']);

        $this->expectException(UnableToReadFileException::class);
        $adapter->getFileType('file.txt');
    }

    public function testGetFileMTimeThrowsWhenNoModifiedOrCreationHeaderIsPresent()
    {
        $adapter = $this->adapterRespondingWith(['Content-Length' => '4']);

        $this->expectException(UnableToReadFileException::class);
        $adapter->getFileMTime('file.txt');
    }

    public function testMd5FileThrowsWhenContentMd5HeaderIsMissing()
    {
        $adapter = $this->adapterRespondingWith(['Content-Length' => '4']);

        $this->expectException(UnableToReadFileException::class);
        $adapter->md5File('file.txt');
    }

    public function testGetFileSizeReturnsContentLengthWhenPresent()
    {
        $adapter = $this->adapterRespondingWith(['Content-Length' => '42']);

        $this->assertSame(42, $adapter->getFileSize('file.txt'));
    }

    public function testGetFileTypeReturnsFileWhenResourceTypeIsFile()
    {
        $adapter = $this->adapterRespondingWith(['x-ms-resource-type' => 'file']);

        $this->assertSame('file', $adapter->getFileType('file.txt'));
    }

    public function testGetFileTypeReturnsDirWhenResourceTypeIsDirectory()
    {
        $adapter = $this->adapterRespondingWith(['x-ms-resource-type' => 'directory']);

        $this->assertSame('dir', $adapter->getFileType('sub'));
    }

    public function testGetFileMTimeReturnsLastModifiedWhenPresent()
    {
        $adapter = $this->adapterRespondingWith(['Last-Modified' => 'Wed, 05 Aug 2026 12:00:00 GMT']);

        $this->assertSame('Wed, 05 Aug 2026 12:00:00 GMT', $adapter->getFileMTime('file.txt'));
    }

    public function testGetFileMTimeFallsBackToCreationTimeWhenLastModifiedIsAbsent()
    {
        $adapter = $this->adapterRespondingWith(['x-ms-creation-time' => 'Wed, 05 Aug 2026 09:00:00 GMT']);

        $this->assertSame('Wed, 05 Aug 2026 09:00:00 GMT', $adapter->getFileMTime('file.txt'));
    }

    public function testMd5FileReturnsContentMd5WhenPresent()
    {
        $adapter = $this->adapterRespondingWith(['Content-MD5' => 'q2dgB5fkR7wKrsvOhFbNVw==']);

        $this->assertSame('q2dgB5fkR7wKrsvOhFbNVw==', $adapter->md5File('file.txt'));
    }

    public function testMetadataGettersStillThrowFileNotFoundOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->getFileSize('missing.txt');
    }

    public function testGetFileTypeThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->getFileType('missing.txt');
    }

    public function testGetFileMTimeThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->getFileMTime('missing.txt');
    }

    public function testMd5FileThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->md5File('missing.txt');
    }

}
