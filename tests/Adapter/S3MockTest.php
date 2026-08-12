<?php

namespace Pop\Storage\Test\Adapter;

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;

class S3MockTest extends TestCase
{

    protected function createClient(MockHandler $handler): S3Client
    {
        return new S3Client([
            'region'      => 'us-east-2',
            'version'     => 'latest',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler'     => $handler,
        ]);
    }

    public function testListFilesDoesNotWarnWhenResponseHasNoContents()
    {
        $handler = new MockHandler([new Result([])]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals([], $storage->listFiles());
    }

    public function testListDirsDoesNotWarnWhenResponseHasNoCommonPrefixes()
    {
        $handler = new MockHandler([new Result([])]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals([], $storage->listDirs());
    }

    public function testListDirsReturnsSubdirectoryFromCommonPrefixes()
    {
        $handler = new MockHandler([
            new Result(['CommonPrefixes' => [['Prefix' => 'subdir/']]]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals(['subdir/'], $storage->listDirs());
    }

    public function testListDirsAtNestedPrefixStripsPrefixFromCommonPrefixes()
    {
        $handler = new MockHandler([
            new Result(['CommonPrefixes' => [['Prefix' => 'foo/bar/']]]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));
        $storage->chdir('foo');

        $this->assertEquals(['bar/'], $storage->listDirs());
    }

    public function testListFilesRecursiveReturnsNestedRelativePaths()
    {
        $handler = new MockHandler([
            new Result(['Contents' => [
                ['Key' => 'top.txt', 'Size' => 4],
                ['Key' => 'sub/inner.txt', 'Size' => 5],
            ]]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals(['top.txt', 'sub/inner.txt'], $storage->listFiles(null, true));
    }

    public function testListFilesWalksMultiplePagesWhenTruncated()
    {
        $handler = new MockHandler([
            new Result(['Contents' => [['Key' => 'page1.txt', 'Size' => 1]], 'IsTruncated' => true, 'NextMarker' => 'page1.txt']),
            new Result(['Contents' => [['Key' => 'page2.txt', 'Size' => 1]], 'IsTruncated' => false]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals(['page1.txt', 'page2.txt'], $storage->listFiles());
    }

    public function testListDirsRecursiveInfersDirectoriesWithoutExplicitMarkers()
    {
        // 'sub/' and 'sub/deeper/' hold real files but no zero-byte marker object -
        // recursive listDirs() still has to report them, matching how Local's recursive
        // walk reports any directory that contains something.
        $handler = new MockHandler([
            new Result(['Contents' => [
                ['Key' => 'top.txt', 'Size' => 4],
                ['Key' => 'sub/inner.txt', 'Size' => 5],
                ['Key' => 'sub/deeper/deepest.txt', 'Size' => 6],
            ]]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->assertEquals(['sub/', 'sub/deeper/'], $storage->listDirs(null, true));
    }

    public function testListFilesAtNestedPrefixStripsPrefixFromKeys()
    {
        $handler = new MockHandler([
            new Result(['Contents' => [['Key' => 'foo/bar.txt', 'Size' => 4]]]),
        ]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));
        $storage->chdir('foo');

        $this->assertEquals(['bar.txt'], $storage->listFiles());
    }

}
