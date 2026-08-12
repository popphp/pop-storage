<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Storage;

class LocalStreamTest extends TestCase
{

    public function testPutFileStreamWritesResourceContents()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'streamed contents');
        rewind($resource);

        $storage->putFileStream('streamed.txt', $resource);
        fclose($resource);

        $this->assertEquals('streamed contents', $storage->fetchFile('streamed.txt'));
        $storage->deleteFile('streamed.txt');
    }

    public function testFetchFileStreamReturnsReadableResource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');
        $storage->putFileContents('to-stream.txt', 'read via stream');

        $resource = $storage->fetchFileStream('to-stream.txt');
        $this->assertIsResource($resource);
        $this->assertEquals('read via stream', stream_get_contents($resource));
        fclose($resource);

        $storage->deleteFile('to-stream.txt');
    }

    public function testFetchFileStreamThrowsFileNotFoundException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(FileNotFoundException::class);
        $storage->fetchFileStream('does-not-exist.txt');
    }

    public function testPutFileStreamRejectsNonResource()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(UnableToWriteFileException::class);
        $storage->putFileStream('test.txt', 'not-a-resource');
    }

}
