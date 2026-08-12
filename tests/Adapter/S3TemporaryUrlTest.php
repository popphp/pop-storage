<?php

namespace Pop\Storage\Test\Adapter;

use Aws\MockHandler;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;

class S3TemporaryUrlTest extends TestCase
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

    public function testGetTemporaryUrlReturnsSignedS3Url()
    {
        $storage = Storage::createS3('s3://my-bucket', $this->createClient());

        $url = $storage->getTemporaryUrl('test.pdf', 300);

        $this->assertStringContainsString('my-bucket', $url);
        $this->assertStringContainsString('test.pdf', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringContainsString('X-Amz-Expires=300', $url);
    }

}
