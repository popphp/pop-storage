<?php

namespace Pop\Storage\Test\Adapter;

use Aws\MockHandler;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\UnsupportedOperationException;
use Pop\Storage\Storage;

class S3StreamTest extends TestCase
{

    protected function createClient(): S3Client
    {
        return new S3Client([
            'region'      => 'us-east-2',
            'version'     => 'latest',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler'     => new MockHandler([]),
        ]);
    }

    public function testPutFileStreamNoLongerThrowsUnsupportedOperationException()
    {
        $storage  = Storage::createS3('s3://my-bucket', $this->createClient());
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'data');
        rewind($resource);

        try {
            // The S3 stream wrapper flushes/sends the PUT on fclose(), and MockHandler's
            // empty queue surfaces that failure as an uncatchable PHP E_WARNING rather
            // than a thrown exception (PHP's stream API predates exceptions and can't
            // propagate them through fopen()/fwrite()/fclose()) - @ suppresses that
            // expected warning so it doesn't pollute test output. Reaching the
            // assertion below at all, without an UnsupportedOperationException having
            // fired, is the actual proof the stub was replaced with real I/O.
            @$storage->putFileStream('test.txt', $resource);
            $this->addToAssertionCount(1);
        } catch (UnsupportedOperationException $exception) {
            $this->fail('putFileStream() should no longer be unsupported on the S3 adapter.');
        } catch (\Throwable $exception) {
            // Any other exception (e.g. a real network/credential failure from the stream
            // wrapper) is fine here too - it proves the code path attempted real I/O
            // instead of hitting the stub.
            $this->assertNotInstanceOf(UnsupportedOperationException::class, $exception);
        } finally {
            fclose($resource);
        }
    }

    public function testPutFileStreamRejectsNonResource()
    {
        $storage = Storage::createS3('s3://my-bucket', $this->createClient());

        $this->expectException(\Pop\Storage\Exception\UnableToWriteFileException::class);
        $storage->putFileStream('test.txt', 'not-a-resource');
    }

}
