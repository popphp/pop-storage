<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToWriteFileException;

class AzureExceptionsTest extends TestCase
{

    protected function createAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    public function testFetchFileThrowsFileNotFoundExceptionOn404()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 404]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(FileNotFoundException::class);
        $adapter->fetchFile('missing.txt');
    }

    public function testPutFileContentsThrowsUnableToWriteFileExceptionOnFailure()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 500]));
        $adapter = $this->createAdapter($handler);

        $this->expectException(UnableToWriteFileException::class);
        $adapter->putFileContents('test.txt', 'contents');
    }

    public function testFetchFileReturnsBodyContentOnSuccess()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'body' => 'file contents']));
        $adapter = $this->createAdapter($handler);

        $this->assertEquals('file contents', $adapter->fetchFile('exists.txt'));
    }

    public function testUploadFileRejectsPathTraversalInAttackerControlledFileName()
    {
        // $file['name'] comes straight from $_FILES and is fully attacker-controlled. The
        // traversal must be rejected before any HTTP dispatch, so no response is queued on the
        // mock handler - if the check fired too late, the handler would be asked for a response
        // it does not have instead of the expected PathTraversalException.
        $handler = new Mock();
        $adapter = $this->createAdapter($handler);
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-upload-');
        file_put_contents($tmpFile, '<?php /* malicious */');

        try {
            $this->expectException(PathTraversalException::class);
            $adapter->uploadFile(['tmp_name' => $tmpFile, 'name' => '../outside/shell.php']);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

}
