<?php

namespace Pop\Storage\Test\Adapter;

use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToCreateDirectoryException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Storage;

class S3ExceptionsTest extends TestCase
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

    public function testFetchFileInfoThrowsFileNotFoundExceptionForMissingLocalPath()
    {
        $handler = new MockHandler([]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->expectException(FileNotFoundException::class);
        $storage->fetchFileInfo('does-not-exist.txt');
    }

    public function testBucketWithoutS3PrefixIsNormalizedTheSameAsWithIt()
    {
        $handler = new MockHandler();
        $storage = Storage::createS3('my-bucket', $this->createClient($handler));
        $handler->append(new Result([]));

        $storage->mkdir('new-folder');

        $lastCommand = $handler->getLastCommand();
        $this->assertEquals('my-bucket', $lastCommand['Bucket']);
        $this->assertEquals('s3://my-bucket', $storage->adapter()->getBaseDir());
    }

    public function testMkdirThrowsUnableToCreateDirectoryExceptionOnSdkFailure()
    {
        $handler = new MockHandler();
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));
        $command = $storage->adapter()->getClient()->getCommand('PutObject');
        $handler->append(new AwsException('Access Denied', $command, ['code' => 'AccessDenied']));

        $this->expectException(UnableToCreateDirectoryException::class);
        $storage->mkdir('new-folder');
    }

    public function testGetFileSizeThrowsFileNotFoundExceptionForMissingKey()
    {
        // The happy path (getFileSize() genuinely returning an int, not a bool) is covered by
        // S3FileOperationsTest::testGetFileSizeReturnsTheRealSizeForAnExistingKey, which mocks a
        // full HeadObject response via the dynamic command-name responder instead of only being
        // able to assert the not-found path, as this test predates that approach.
        $handler = new MockHandler([]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->expectException(FileNotFoundException::class);
        $storage->getFileSize('does-not-exist.txt');
    }

    public function testUploadFileThrowsUnableToWriteFileExceptionForUnreadableSource()
    {
        // uploadFile() reads the source via file_get_contents() before writing through the
        // s3:// stream wrapper - no SDK call happens when the source itself can't be read, so
        // no bucket needs to exist under test.
        $handler = new MockHandler([]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));

        $this->expectException(UnableToWriteFileException::class);
        $storage->uploadFile(['tmp_name' => '/does/not/exist/upload.tmp', 'name' => 'upload.tmp']);
    }

    public function testUploadFileRejectsPathTraversalInAttackerControlledFileName()
    {
        // $file['name'] comes straight from $_FILES and is fully attacker-controlled, so it must
        // be scrubbed before it is concatenated into the s3:// destination key. The traversal has
        // to be rejected before any write is attempted, with a real readable source file present
        // so the earlier unreadable-source guard cannot mask the missing check.
        $handler = new MockHandler([]);
        $storage = Storage::createS3('s3://my-bucket', $this->createClient($handler));
        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-upload-');
        file_put_contents($tmpFile, '<?php /* malicious */');

        try {
            $this->expectException(PathTraversalException::class);
            $storage->uploadFile(['tmp_name' => $tmpFile, 'name' => '../outside/shell.php']);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

}
