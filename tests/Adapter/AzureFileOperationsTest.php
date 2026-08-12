<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\UnableToCopyFileException;
use Pop\Storage\Exception\UnableToDeleteFileException;
use Pop\Storage\Exception\UnableToMoveFileException;
use Pop\Storage\Exception\UnableToReadFileException;
use Pop\Storage\Exception\UnableToWriteFileException;

class AzureFileOperationsTest extends TestCase
{

    protected function createAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    public function testPutFileUploadsLocalFileContents()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-put-');
        file_put_contents($tmpFile, 'local contents');

        $handler = new Mock();
        $handler->queue(new Response(['code' => 201]));
        $adapter = $this->createAdapter($handler);

        try {
            $adapter->putFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }

        $this->assertEquals('local contents', $handler->getLastRequest()->getBodyContent());
    }

    public function testPutFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $handler = new Mock();
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->putFile('/does/not/exist.txt');
    }

    public function testCopyFileSendsCopySourceHeaderToDestination()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $adapter = $this->createAdapter($handler);

        $adapter->copyFile('source.txt', 'dest.txt');

        $this->assertStringEndsWith('/dest.txt', $handler->getLastRequest()->getUriAsString());
        $this->assertStringContainsString(
            '/my-container/source.txt', $handler->getLastRequest()->getHeaderValueAsString('x-ms-copy-source')
        );
    }

    public function testCopyFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->copyFile('missing.txt', 'dest.txt');
    }

    public function testCopyFileThrowsUnableToCopyFileExceptionOnFailedCopy()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToCopyFileException::class);
        $adapter->copyFile('source.txt', 'dest.txt');
    }

    public function testCopyFileToExternalSendsCopySourceHeaderToTheExternalUri()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $adapter = $this->createAdapter($handler);

        $adapter->copyFileToExternal('source.txt', '/other-container/dest.txt');

        $this->assertStringEndsWith('/other-container/dest.txt', $handler->getLastRequest()->getUriAsString());
    }

    public function testCopyFileToExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->copyFileToExternal('missing.txt', '/other-container/dest.txt');
    }

    public function testCopyFileFromExternalCopiesFromTheExternalUri()
    {
        // This is the exact code path that pop-http's getHeader() rename broke (getHeader()
        // used to return an object; now returns an array), verified for real here via the
        // handler seam instead of pinning the pop-http API in isolation.
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $adapter = $this->createAdapter($handler);

        $adapter->copyFileFromExternal('/other-container/source.txt', 'dest.txt');

        $this->assertStringContainsString(
            '/other-container/source.txt', $handler->getLastRequest()->getHeaderValueAsString('x-ms-copy-source')
        );
    }

    public function testCopyFileFromExternalThrowsUnableToCopyFileExceptionWhenTheSourceHeadFails()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToCopyFileException::class);
        $adapter->copyFileFromExternal('/other-container/source.txt', 'dest.txt');
    }

    public function testCopyFileFromExternalThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->copyFileFromExternal('/other-container/missing.txt', 'dest.txt');
    }

    public function testMoveFileToExternalCopiesThenDeletesTheSource()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 202]));
        $adapter = $this->createAdapter($handler);

        $adapter->moveFileToExternal('source.txt', '/other-container/dest.txt');

        $this->assertCount(3, $handler->getRequests());
        $this->assertEquals('DELETE', $handler->getLastRequest()->getMethod());
        $this->assertStringEndsWith('/source.txt', $handler->getLastRequest()->getUriAsString());
    }

    public function testMoveFileFromExternalCopiesThenDeletesTheExternalSource()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 202]));
        $adapter = $this->createAdapter($handler);

        $adapter->moveFileFromExternal('/other-container/source.txt', 'dest.txt');

        $this->assertCount(3, $handler->getRequests());
        $this->assertEquals('DELETE', $handler->getLastRequest()->getMethod());
        $this->assertStringEndsWith('/other-container/source.txt', $handler->getLastRequest()->getUriAsString());
    }

    public function testRenameFileCopiesThenDeletesTheOldName()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 202]));
        $adapter = $this->createAdapter($handler);

        $adapter->renameFile('old.txt', 'new.txt');

        $this->assertCount(3, $handler->getRequests());
        $this->assertEquals('DELETE', $handler->getLastRequest()->getMethod());
        $this->assertStringEndsWith('/old.txt', $handler->getLastRequest()->getUriAsString());
    }

    public function testReplaceFileContentsWritesNewContentsToTheSameName()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200])); // fileExists() pre-check
        $handler->queue(new Response(['code' => 201])); // the actual write
        $adapter = $this->createAdapter($handler);

        $adapter->replaceFileContents('test.txt', 'replaced');

        $this->assertEquals('replaced', $handler->getLastRequest()->getBodyContent());
    }

    public function testReplaceFileContentsThrowsFileNotFoundExceptionForMissingTarget()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->replaceFileContents('missing.txt', 'replaced');
    }

    public function testFileExistsReturnsTrueOn200()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200]));
        $adapter = $this->createAdapter($handler);

        $this->assertTrue($adapter->fileExists('exists.txt'));
    }

    public function testFileExistsReturnsFalseOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->assertFalse($adapter->fileExists('missing.txt'));
    }

    public function testIsFileReturnsTrueForFileResourceType()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['x-ms-resource-type' => 'file']]));
        $adapter = $this->createAdapter($handler);

        $this->assertTrue($adapter->isFile('test.txt'));
    }

    public function testIsFileReturnsFalseForDirectoryResourceType()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['x-ms-resource-type' => 'directory']]));
        $adapter = $this->createAdapter($handler);

        $this->assertFalse($adapter->isFile('sub'));
    }

    public function testIsDirReturnsTrueForDirectoryResourceType()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['x-ms-resource-type' => 'directory']]));
        $adapter = $this->createAdapter($handler);

        $this->assertTrue($adapter->isDir('sub'));
    }

    public function testIsDirReturnsFalseForFileResourceType()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['x-ms-resource-type' => 'file']]));
        $adapter = $this->createAdapter($handler);

        $this->assertFalse($adapter->isDir('test.txt'));
    }

    public function testIsDirStripsLeadingAndTrailingSlashesBeforeChecking()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['x-ms-resource-type' => 'directory']]));
        $adapter = $this->createAdapter($handler);

        $this->assertTrue($adapter->isDir('/sub/'));
    }

    public function testPutFileThrowsUnableToWriteFileExceptionOnFailure()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-put-');
        file_put_contents($tmpFile, 'contents');

        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        try {
            $this->expectException(UnableToWriteFileException::class);
            $adapter->putFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testPutFileStreamRejectsNonResource()
    {
        $handler = new Mock();
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToWriteFileException::class);
        $adapter->putFileStream('test.txt', 'not-a-resource');
    }

    public function testPutFileStreamThrowsUnableToWriteFileExceptionOnFailure()
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'contents');
        rewind($resource);

        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        try {
            $this->expectException(UnableToWriteFileException::class);
            $adapter->putFileStream('test.txt', $resource);
        } finally {
            fclose($resource);
        }
    }

    public function testUploadFileThrowsUnableToWriteFileExceptionForInvalidArray()
    {
        $handler = new Mock();
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToWriteFileException::class);
        $adapter->uploadFile(['not' => 'valid']);
    }

    public function testUploadFileThrowsUnableToWriteFileExceptionForMissingTmpFile()
    {
        $handler = new Mock();
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToWriteFileException::class);
        $adapter->uploadFile(['tmp_name' => '/does/not/exist.tmp', 'name' => 'upload.txt']);
    }

    public function testUploadFileThrowsUnableToWriteFileExceptionOnFailure()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-upload-');
        file_put_contents($tmpFile, 'contents');

        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        try {
            $this->expectException(UnableToWriteFileException::class);
            $adapter->uploadFile(['tmp_name' => $tmpFile, 'name' => 'upload.txt']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testCopyFileToExternalThrowsUnableToCopyFileExceptionOnFailedCopy()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToCopyFileException::class);
        $adapter->copyFileToExternal('source.txt', '/other-container/dest.txt');
    }

    public function testCopyFileFromExternalThrowsUnableToCopyFileExceptionWhenTheFinalWriteFails()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToCopyFileException::class);
        $adapter->copyFileFromExternal('/other-container/source.txt', 'dest.txt');
    }

    public function testMoveFileToExternalThrowsUnableToMoveFileExceptionWhenTheSourceDeleteFails()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToMoveFileException::class);
        $adapter->moveFileToExternal('source.txt', '/other-container/dest.txt');
    }

    public function testMoveFileFromExternalThrowsFileNotFoundExceptionWhenTheExternalSourceIsGoneOnDelete()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->moveFileFromExternal('/other-container/source.txt', 'dest.txt');
    }

    public function testMoveFileFromExternalThrowsUnableToMoveFileExceptionWhenTheSourceDeleteFails()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToMoveFileException::class);
        $adapter->moveFileFromExternal('/other-container/source.txt', 'dest.txt');
    }

    public function testRenameFileThrowsUnableToMoveFileExceptionWhenTheOldNameDeleteFails()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '8']]));
        $handler->queue(new Response(['code' => 201]));
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToMoveFileException::class);
        $adapter->renameFile('old.txt', 'new.txt');
    }

    public function testDeleteFileThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->deleteFile('missing.txt');
    }

    public function testDeleteFileThrowsUnableToDeleteFileExceptionOnFailure()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToDeleteFileException::class);
        $adapter->deleteFile('test.txt');
    }

    public function testFetchFileStreamThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->fetchFileStream('missing.txt');
    }

    public function testFetchFileStreamThrowsUnableToReadFileExceptionOnFailure()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToReadFileException::class);
        $adapter->fetchFileStream('test.txt');
    }

    public function testFetchFileThrowsUnableToReadFileExceptionOnFailure()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToReadFileException::class);
        $adapter->fetchFile('test.txt');
    }

}
