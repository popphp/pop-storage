<?php

namespace Pop\Storage\Test;

use PHPUnit\Framework\TestCase;
use Pop\Storage;
use Pop\Http\Server\Upload;
use Aws\S3;

class StorageS3Test extends TestCase
{

    protected $s3 = null;

    /**
     * @group skip
     */
    public function setUp(): void
    {
        $this->s3 = new Storage\S3($_ENV['AWS_BUCKET'], new S3\S3Client([
            'credentials' => [
                'key'    => $_ENV['AWS_KEY'],
                'secret' => $_ENV['AWS_SECRET'],
            ],
            'region'  => $_ENV['AWS_REGION'],
            'version' => $_ENV['AWS_VERSION']
        ]));
    }

    /**
     * @group skip
     */
    public function testConstructor()
    {
        $this->assertInstanceOf('Pop\Storage\S3', $this->s3);
    }

    /**
     * @group skip
     */
    public function testGetClient()
    {
        $this->assertInstanceOf('Aws\S3\S3Client', $this->s3->getClient());
    }

    /**
     * @group skip
     */
    public function testIsLocal()
    {
        $this->assertFalse($this->s3->isLocal());
    }

    /**
     * @group skip
     */
    public function testSetAndGetLocation()
    {
        $s3 = new Storage\S3('popphptestbucket2', new S3\S3Client([
            'credentials' => [
                'key'    => $_ENV['AWS_KEY'],
                'secret' => $_ENV['AWS_SECRET'],
            ],
            'region'  => $_ENV['AWS_REGION'],
            'version' => $_ENV['AWS_VERSION']
        ]));
        $this->assertEquals('popphptestbucket2', $s3->getLocation());
        $s3->setLocation($_ENV['AWS_BUCKET']);
        $this->assertEquals($_ENV['AWS_BUCKET'], $s3->getLocation());
    }

    /**
     * @group skip
     */
    public function testFileExists()
    {
        $this->assertFalse($this->s3->fileExists('test.txt'));
    }

    /**
     * @group skip
     */
    public function testUploadFile1()
    {
        file_put_contents(__DIR__ . '/tmp/uploaded.txt', 'uploaded');
        $this->s3->uploadFile(__DIR__ . '/tmp/uploaded.txt');
        $this->assertTrue($this->s3->fileExists('/uploaded.txt'));
        unlink(__DIR__ . '/tmp/uploaded.txt');
    }

    /**
     * @group skip
     */
    public function testMd5File()
    {
        $this->assertEquals('bf5f3461956c8f4ab9d7c0877c1505ad', $this->s3->md5File('/uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testDeleteFile1()
    {
        $this->assertTrue($this->s3->fileExists('/uploaded.txt'));
        $this->s3->deleteFile('/uploaded.txt');
        $this->assertFalse($this->s3->fileExists('/uploaded.txt'));
    }

    /**
     * @group skip
     */
    public function testUploadFile2()
    {
        file_put_contents(__DIR__ . '/tmp/uploaded2.txt', 'uploaded');
        $file = [
            'tmp_name' => __DIR__ . '/tmp/uploaded2.txt',
            'name'     => 'uploaded2.txt',
            'size'     => 8,
            'error'    => 0
        ];
        $this->s3->uploadFile($file, null, new Upload(__DIR__));
        $this->assertTrue($this->s3->fileExists('/uploaded2.txt'));
        unlink(__DIR__ . '/tmp/uploaded2.txt');
    }

    /**
     * @group skip
     */
    public function testDeleteFile2()
    {
        $this->assertTrue($this->s3->fileExists('/uploaded2.txt'));
        $this->s3->deleteFile('/uploaded2.txt');
        $this->assertFalse($this->s3->fileExists('/uploaded2.txt'));
    }

    /**
     * @group skip
     */
    public function testBadUpload()
    {
        $this->assertEmpty($this->s3->uploadFile(__DIR__ . '/tmp/bad-upload.txt'));
    }

    /**
     * @group skip
     */
    public function testUploadFileStream()
    {
        $this->s3->uploadFileStream('0123456789', 'uploaded3.txt');
        $this->assertTrue($this->s3->fileExists('/uploaded3.txt'));
    }

    /**
     * @group skip
     */
    public function testDeleteFile3()
    {
        $this->assertTrue($this->s3->fileExists('/uploaded3.txt'));
        $this->s3->deleteFile('/uploaded3.txt');
        $this->assertFalse($this->s3->fileExists('/uploaded3.txt'));
    }

    /**
     * @group skip
     */
    public function testMkDir()
    {
        $this->s3->mkdir('/test');
        $this->assertTrue($this->s3->fileExists('/test'));
    }

    /**
     * @group skip
     */
    public function testRmDir()
    {
        $this->assertTrue($this->s3->fileExists('/test'));
        $this->s3->rmdir('/test');
        $this->assertFalse($this->s3->fileExists('/test'));
    }

}
