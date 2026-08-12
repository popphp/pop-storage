<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Exception\UnableToReadFileException;

/**
 * Test-only Azure subclass that overrides the walkBlobs() page cap to a small
 * number, so the runaway-NextMarker guard can be exercised without actually
 * looping thousands of times in the test suite.
 */
class AzureWithSmallPageCap extends Azure
{
    protected const int MAX_LIST_PAGES = 3;
}

class AzureListingTest extends TestCase
{

    protected function createAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    protected function blobsResponse(array $blobs, ?string $nextMarker = null): Response
    {
        $body = ['Blobs' => ['Blob' => $blobs]];
        if ($nextMarker !== null) {
            $body['NextMarker'] = $nextMarker;
        }
        return new Response([
            'code'    => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body)
        ]);
    }

    public function testListFilesWalksAllPagesViaNextMarker()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse(
            [['Name' => 'page1.txt', 'Properties' => ['ResourceType' => 'file']]], 'page1.txt'
        ));
        $handler->queue($this->blobsResponse(
            [['Name' => 'page2.txt', 'Properties' => ['ResourceType' => 'file']]]
        ));
        $adapter = $this->createAdapter($handler);

        $files = $adapter->listFiles();

        $this->assertEquals(['page1.txt', 'page2.txt'], $files);
        $this->assertCount(2, $handler->getRequests());
    }

    public function testListFilesThrowsWhenNextMarkerNeverResolves()
    {
        $handler = new Mock();
        // Each page's NextMarker always points to another page, simulating a
        // malformed/repeating continuation marker that never terminates.
        $handler->queue($this->blobsResponse(
            [['Name' => 'page1.txt', 'Properties' => ['ResourceType' => 'file']]], 'same-marker'
        ));
        $handler->queue($this->blobsResponse(
            [['Name' => 'page2.txt', 'Properties' => ['ResourceType' => 'file']]], 'same-marker'
        ));
        $handler->queue($this->blobsResponse(
            [['Name' => 'page3.txt', 'Properties' => ['ResourceType' => 'file']]], 'same-marker'
        ));

        $adapter = new AzureWithSmallPageCap('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);

        $this->expectException(UnableToReadFileException::class);
        $adapter->listFiles();
    }

    public function testRecursiveListingAfterChdirReturnsPathsRelativeToTheCurrentDirectory()
    {
        // Azure reports blob names relative to the container root ('sub/file.txt'), but
        // listFiles() must return them relative to the current directory ('file.txt') so the
        // results round-trip straight back into fetchFile()/deleteFile(), matching Local and S3.
        $handler = new Mock();
        $handler->queue($this->blobsResponse([
            ['Name' => 'sub/file.txt',           'Properties' => ['ResourceType' => 'file']],
            ['Name' => 'sub/deep/nested.txt',    'Properties' => ['ResourceType' => 'file']],
        ]));
        $adapter = $this->createAdapter($handler);
        $adapter->chdir('sub');

        $files = $adapter->listFiles(null, true);

        $this->assertEquals(['file.txt', 'deep/nested.txt'], $files);
        $this->assertStringContainsString('prefix=sub%2F', $handler->getRequests()[0]->getUriAsString());
    }

    public function testRecursiveListingResultsResolveBackToTheCorrectBlobUri()
    {
        // The round-trip half of the contract: feeding a listing result back into a
        // path-taking method must address the same blob the listing came from.
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'body' => 'contents']));
        $adapter = $this->createAdapter($handler);
        $adapter->chdir('sub');

        $adapter->fetchFile('deep/nested.txt');

        $this->assertStringEndsWith(
            '/my-container/sub/deep/nested.txt', $handler->getRequests()[0]->getUriAsString()
        );
    }

    public function testNonRecursiveListingAfterChdirReturnsDirectChildrenOnly()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse([
            ['Name' => 'sub/file.txt',        'Properties' => ['ResourceType' => 'file']],
            ['Name' => 'sub/deep/nested.txt', 'Properties' => ['ResourceType' => 'file']],
        ]));
        $adapter = $this->createAdapter($handler);
        $adapter->chdir('sub');

        $this->assertEquals(['file.txt'], $adapter->listFiles());
    }

    public function testListDirsReturnsOnlyDirectoryResourceTypeBlobs()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse([
            ['Name' => 'sub',        'Properties' => ['ResourceType' => 'directory']],
            ['Name' => 'top.txt',    'Properties' => ['ResourceType' => 'file']],
        ]));
        $adapter = $this->createAdapter($handler);

        $this->assertEquals(['sub'], $adapter->listDirs());
    }

    public function testListFilesAppliesSearchFilter()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse([
            ['Name' => 'keep.txt',   'Properties' => ['ResourceType' => 'file']],
            ['Name' => 'skip.log',   'Properties' => ['ResourceType' => 'file']],
        ]));
        $adapter = $this->createAdapter($handler);

        $this->assertEquals(['keep.txt'], $adapter->listFiles('*.txt'));
    }

    public function testListDirsAppliesSearchFilter()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse([
            ['Name' => 'keep',   'Properties' => ['ResourceType' => 'directory']],
            ['Name' => 'other',  'Properties' => ['ResourceType' => 'directory']],
        ]));
        $adapter = $this->createAdapter($handler);

        $this->assertEquals(['keep'], $adapter->listDirs('keep*'));
    }

    public function testListDirsRecursiveWalksNextMarkerJustLikeListFiles()
    {
        $handler = new Mock();
        $handler->queue($this->blobsResponse(
            [['Name' => 'sub1', 'Properties' => ['ResourceType' => 'directory']]], 'sub1'
        ));
        $handler->queue($this->blobsResponse(
            [['Name' => 'sub1/sub2', 'Properties' => ['ResourceType' => 'directory']]]
        ));
        $adapter = $this->createAdapter($handler);

        $this->assertEquals(['sub1', 'sub1/sub2'], $adapter->listDirs(null, true));
        $this->assertCount(2, $handler->getRequests());
    }

}
