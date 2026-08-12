<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Storage;

class AbstractAdapterScrubTest extends TestCase
{

    public function testEmbeddedDotDotThrowsPathTraversalException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(PathTraversalException::class);
        $storage->fileExists('../../../../etc/passwd');
    }

    public function testDotDotInMiddleOfPathThrowsPathTraversalException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(PathTraversalException::class);
        $storage->fileExists('foo/../../bar.txt');
    }

    public function testLeadingSlashIsStillNormalizedNotRejected()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        // A single leading slash is relativized (existing behavior), not a traversal attempt.
        $this->assertFalse($storage->fileExists('/does-not-exist.txt'));
    }

    public function testLeadingDotSlashIsStillNormalizedNotRejected()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->assertFalse($storage->fileExists('./does-not-exist.txt'));
    }

    protected function createAzureAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    public function testAzureFetchFileThrowsPathTraversalException()
    {
        // Every path-taking Azure method funnels through resolveUri(), which must scrub before
        // building the blob URI. No response is queued on the mock handler - the traversal has to
        // be rejected before any HTTP dispatch happens.
        $adapter = $this->createAzureAdapter(new Mock());

        $this->expectException(PathTraversalException::class);
        $adapter->fetchFile('../../other-container/secret.txt');
    }

    public function testAzureDeleteFileThrowsPathTraversalException()
    {
        $adapter = $this->createAzureAdapter(new Mock());

        $this->expectException(PathTraversalException::class);
        $adapter->deleteFile('sub/../../secret.txt');
    }

    public function testAzureLeadingSlashIsNormalizedIntoTheContainerNotUsedVerbatim()
    {
        // A leading slash used to make resolveUri() use the path verbatim, escaping the
        // container entirely. scrub() strips it, so the request stays container-scoped.
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'body' => 'contents']));
        $adapter = $this->createAzureAdapter($handler);

        $adapter->fetchFile('/absolute.txt');

        $this->assertStringContainsString('/my-container/absolute.txt', $handler->getRequests()[0]->getUriAsString());
    }

}
