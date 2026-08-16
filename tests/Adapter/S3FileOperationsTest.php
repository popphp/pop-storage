<?php

namespace Pop\Storage\Test\Adapter;

use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Storage;

/**
 * Covers the S3 adapter's stream-wrapper-based methods (putFile, copyFile, deleteFile,
 * fetchFile, fileExists, etc.) - these operate on plain PHP filesystem functions against an
 * s3:// path, which the AWS SDK's registered stream wrapper turns into real S3 API commands
 * dispatched through the SAME S3Client/handler as everything else, so Aws\MockHandler can
 * intercept them exactly like the SDK-calling methods (mkdir, fetchFileInfo) already tested
 * in S3ExceptionsTest.php.
 *
 * Two things this exploits deliberately:
 *  - The stream wrapper issues a handful of internal API calls per operation (e.g. copyFile
 *    does a HeadObject existence check, then another HeadObject + GetObject to open a read
 *    stream, then a PutObject to write the destination) that are SDK implementation detail,
 *    not this adapter's own logic. Rather than hard-coding that exact sequence (and re-testing
 *    the AWS SDK instead of our code), happy-path tests queue a small dynamic dispatcher -
 *    respondingByCommandName() - that answers whatever command comes in based on its name, as
 *    many times as needed.
 *  - The stream wrapper caches url_stat() results (HeadObject) per path, and that cache is
 *    process-global (tied to the registered "s3://" wrapper, not to any one S3Client/test).
 *    Every test method below uses its own unique bucket name so a cached stat from one test
 *    can never leak into another.
 */
class S3FileOperationsTest extends TestCase
{

    protected static int $bucketCounter = 0;

    protected function uniqueBucket(): string
    {
        return 's3://test-bucket-' . (++static::$bucketCounter) . '-' . uniqid();
    }

    protected function createClient(MockHandler $handler): S3Client
    {
        return new S3Client([
            'region'      => 'us-east-2',
            'version'     => 'latest',
            'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
            'handler'     => $handler,
        ]);
    }

    /**
     * Queue a dynamic responder $count times, each answering whatever S3 command comes in
     * based on its name - a HeadObject/GetObject/PutObject/CopyObject/DeleteObject/ListObjects
     * all succeed with plausible canned data, so any sequence of internal SDK calls up to
     * $count long resolves without the test needing to know the exact sequence.
     */
    protected function queueHappyPathResponses(MockHandler $handler, int $count = 15): void
    {
        $responder = function ($command) {
            return match ($command->getName()) {
                'HeadObject' => new Result([
                    'ContentLength' => 8,
                    'ContentType'   => 'text/plain',
                    'ETag'          => '"abc123"',
                    'LastModified'  => 'Mon, 01 Jan 2024 00:00:00 GMT',
                ]),
                'GetObject' => new Result([
                    'Body'          => \GuzzleHttp\Psr7\Utils::streamFor('contents'),
                    'ContentLength' => 8,
                    'ContentType'   => 'text/plain',
                    'ETag'          => '"abc123"',
                ]),
                'PutObject'    => new Result(['ETag' => '"abc123"']),
                'DeleteObject' => new Result([]),
                'CopyObject'   => new Result(['CopyObjectResult' => ['ETag' => '"abc123"']]),
                'ListObjects'  => new Result(['Contents' => [], 'CommonPrefixes' => []]),
                default        => new Result([]),
            };
        };

        for ($i = 0; $i < $count; $i++) {
            $handler->append($responder);
        }
    }

    /**
     * Queue a not-found response for the very first existence check an operation makes
     * (HeadObject 404, falling back to an empty ListObjects, matching createStat()'s own
     * fallback behavior in the AWS SDK's stream wrapper).
     */
    protected function queueNotFoundResponse(MockHandler $handler, S3Client $client, string $bucket, string $key): void
    {
        $command = $client->getCommand('HeadObject', ['Bucket' => $bucket, 'Key' => $key]);
        $handler->append(new AwsException('Not Found', $command, [
            'code'     => 'NotFound',
            'response' => new \GuzzleHttp\Psr7\Response(404),
        ]));
        $handler->append(new Result(['Contents' => [], 'CommonPrefixes' => []]));
    }

    public function testHasClientIsTrueAfterConstruction()
    {
        $handler = new MockHandler([]);
        $storage = Storage::createS3($this->uniqueBucket(), $this->createClient($handler));

        $this->assertTrue($storage->adapter()->hasClient());
    }

    public function testPutFileWritesLocalFileToTheDestination()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueHappyPathResponses($handler);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-s3-put-');
        file_put_contents($tmpFile, 'local contents');

        try {
            $storage->putFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }

        $this->addToAssertionCount(1);
    }

    public function testPutFileThrowsFileNotFoundExceptionForMissingLocalSource()
    {
        // The source $fileFrom is a plain local path (the file being uploaded FROM), checked
        // via a real file_exists() before anything ever touches S3 - no mocking needed.
        $storage = Storage::createS3($this->uniqueBucket(), $this->createClient(new MockHandler([])));

        $this->expectException(FileNotFoundException::class);
        $storage->putFile('/does/not/exist/upload.tmp');
    }

    public function testPutFileMovesTheLocalSourceWhenCopyIsFalse()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $tmpFile = tempnam(sys_get_temp_dir(), 'pop-s3-put-move-');
        file_put_contents($tmpFile, 'local contents');

        $storage->putFile($tmpFile, false);

        $this->assertFileDoesNotExist($tmpFile);
    }

    public function testPutFileContentsWritesThroughTheStreamWrapper()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->putFileContents('test.txt', 'new contents');

        $this->addToAssertionCount(1);
    }

    public function testUploadFileThrowsUnableToWriteFileExceptionForInvalidArray()
    {
        $storage = Storage::createS3($this->uniqueBucket(), $this->createClient(new MockHandler([])));

        $this->expectException(UnableToWriteFileException::class);
        $storage->uploadFile(['not' => 'valid']);
    }

    public function testFetchFileInfoReturnsTheHeadObjectDataOnSuccess()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $info = $storage->fetchFileInfo('test.txt');

        $this->assertIsArray($info);
        $this->assertNotEmpty($info);
    }

    public function testFetchFileReturnsContentsOnSuccess()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertEquals('contents', $storage->fetchFile('test.txt'));
    }

    public function testFetchFileThrowsFileNotFoundExceptionForMissingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->fetchFile('missing.txt');
    }

    public function testFetchFileStreamReturnsAReadableResource()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $resource = $storage->fetchFileStream('test.txt');

        $this->assertIsResource($resource);
        $this->assertEquals('contents', stream_get_contents($resource));
        fclose($resource);
    }

    public function testFetchFileStreamThrowsFileNotFoundExceptionForMissingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->fetchFileStream('missing.txt');
    }

    public function testCopyFileSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->copyFile('source.txt', 'dest.txt');

        $this->addToAssertionCount(1);
    }

    public function testCopyFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFile('missing.txt', 'dest.txt');
    }

    public function testCopyFileToExternalSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->copyFileToExternal('source.txt', $bucket . '-other/dest.txt');

        $this->addToAssertionCount(1);
    }

    public function testCopyFileToExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFileToExternal('missing.txt', $bucket . '-other/dest.txt');
    }

    public function testCopyFileFromExternalSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->copyFileFromExternal($bucket . '-other/source.txt', 'dest.txt');

        $this->addToAssertionCount(1);
    }

    public function testCopyFileFromExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket    = $this->uniqueBucket();
        $otherName = str_replace('s3://', '', $bucket) . '-other';
        $handler   = new MockHandler([]);
        $client    = $this->createClient($handler);
        $storage   = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, $otherName, 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->copyFileFromExternal($bucket . '-other/missing.txt', 'dest.txt');
    }

    public function testMoveFileToExternalSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->moveFileToExternal('source.txt', $bucket . '-other/dest.txt');

        $this->addToAssertionCount(1);
    }

    public function testMoveFileToExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->moveFileToExternal('missing.txt', $bucket . '-other/dest.txt');
    }

    public function testMoveFileFromExternalSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->moveFileFromExternal($bucket . '-other/source.txt', 'dest.txt');

        $this->addToAssertionCount(1);
    }

    public function testMoveFileFromExternalThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket    = $this->uniqueBucket();
        $otherName = str_replace('s3://', '', $bucket) . '-other';
        $handler   = new MockHandler([]);
        $client    = $this->createClient($handler);
        $storage   = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, $otherName, 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->moveFileFromExternal($bucket . '-other/missing.txt', 'dest.txt');
    }

    public function testRenameFileSucceedsWhenSourceExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->renameFile('old.txt', 'new.txt');

        $this->addToAssertionCount(1);
    }

    public function testRenameFileThrowsFileNotFoundExceptionForMissingSource()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->renameFile('missing.txt', 'new.txt');
    }

    public function testReplaceFileContentsSucceedsWhenTargetExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->replaceFileContents('test.txt', 'replaced contents');

        $this->addToAssertionCount(1);
    }

    public function testReplaceFileContentsThrowsFileNotFoundExceptionForMissingTarget()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->replaceFileContents('missing.txt', 'replaced contents');
    }

    public function testDeleteFileSucceedsWhenTargetExists()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $storage->deleteFile('test.txt');

        $this->addToAssertionCount(1);
    }

    public function testDeleteFileThrowsFileNotFoundExceptionForMissingTarget()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->expectException(FileNotFoundException::class);
        $storage->deleteFile('missing.txt');
    }

    public function testFileExistsReturnsTrueForAnExistingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertTrue($storage->fileExists('test.txt'));
    }

    public function testFileExistsReturnsFalseForAMissingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing.txt');

        $this->assertFalse($storage->fileExists('missing.txt'));
    }

    public function testIsFileReturnsTrueForAnExistingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertTrue($storage->isFile('test.txt'));
    }

    public function testIsDirReturnsFalseForAMissingPath()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $this->queueNotFoundResponse($handler, $client, str_replace('s3://', '', $bucket), 'missing');

        $this->assertFalse($storage->isDir('missing'));
    }

    public function testGetFileTypeReturnsFileForAnExistingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertEquals('file', $storage->getFileType('test.txt'));
    }

    public function testGetFileTypeThrowsFileNotFoundExceptionForMissingKey()
    {
        $storage = Storage::createS3($this->uniqueBucket(), $this->createClient(new MockHandler([])));

        $this->expectException(FileNotFoundException::class);
        $storage->getFileType('missing.txt');
    }

    public function testGetFileMTimeReturnsAnIntForAnExistingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertIsInt($storage->getFileMTime('test.txt'));
    }

    public function testGetFileMTimeThrowsFileNotFoundExceptionForMissingKey()
    {
        $storage = Storage::createS3($this->uniqueBucket(), $this->createClient(new MockHandler([])));

        $this->expectException(FileNotFoundException::class);
        $storage->getFileMTime('missing.txt');
    }

    public function testGetFileSizeReturnsTheRealSizeForAnExistingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertSame(8, $storage->getFileSize('test.txt'));
    }

    public function testMd5FileReturnsTheEtagWithoutQuotes()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));
        $this->queueHappyPathResponses($handler);

        $this->assertEquals('abc123', $storage->md5File('test.txt'));
    }

    public function testMd5FileThrowsFileNotFoundExceptionForMissingKey()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $command = $client->getCommand('HeadObject', ['Bucket' => str_replace('s3://', '', $bucket), 'Key' => 'missing.txt']);
        $handler->append(new AwsException('Not Found', $command, [
            'code'     => 'NotFound',
            'response' => new \GuzzleHttp\Psr7\Response(404),
        ]));

        $this->expectException(FileNotFoundException::class);
        $storage->md5File('missing.txt');
    }

    public function testMd5FileThrowsUnableToReadFileExceptionWhenNoEtagIsReturned()
    {
        // md5File() now reads the ETag off a single HeadObject call - a success response
        // missing the ETag key entirely is a malformed-but-successful response.
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([
            new Result(['ContentLength' => 8, 'LastModified' => 'Mon, 01 Jan 2024 00:00:00 GMT']),
        ]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));

        $this->expectException(\Pop\Storage\Exception\UnableToReadFileException::class);
        $storage->md5File('test.txt');
    }

    public function testMkdirBuildsTheKeyRelativeToAnEmbeddedBucketSubfolder()
    {
        // When the base directory itself has an embedded prefix (s3://bucket/subfolder), mkdir()
        // has to split that prefix out of the bucket name and prepend it to the new key, rather
        // than sending the subfolder as part of the bucket name.
        $bucketName = 'bucket-' . uniqid();
        $handler    = new MockHandler([]);
        $client     = $this->createClient($handler);
        $storage    = Storage::createS3('s3://' . $bucketName . '/subfolder', $client);
        $handler->append(new Result([]));

        $storage->mkdir('new-folder');

        $lastCommand = $handler->getLastCommand();
        $this->assertEquals('PutObject', $lastCommand->getName());
        $this->assertEquals($bucketName, $lastCommand['Bucket']);
        $this->assertEquals('subfolder/new-folder/', $lastCommand['Key']);
    }

    public function testListDirsThrowsUnableToReadFileExceptionOnSdkFailure()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $command = $client->getCommand('ListObjects', ['Bucket' => str_replace('s3://', '', $bucket)]);
        $handler->append(new AwsException('Access Denied', $command, ['code' => 'AccessDenied']));

        $this->expectException(\Pop\Storage\Exception\UnableToReadFileException::class);
        $storage->listDirs();
    }

    public function testListFilesThrowsUnableToReadFileExceptionOnSdkFailure()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);
        $command = $client->getCommand('ListObjects', ['Bucket' => str_replace('s3://', '', $bucket)]);
        $handler->append(new AwsException('Access Denied', $command, ['code' => 'AccessDenied']));

        $this->expectException(\Pop\Storage\Exception\UnableToReadFileException::class);
        $storage->listFiles();
    }

    public function testFetchFileInfoThrowsUnableToReadFileExceptionWhenTheHeadObjectCallFails()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);

        // First HeadObject (the file_exists() pre-check via the stream wrapper) succeeds; the
        // second, explicit headObject() call this method makes for the real metadata fails.
        $this->queueHappyPathResponses($handler, 1);
        $command = $client->getCommand('HeadObject', ['Bucket' => str_replace('s3://', '', $bucket), 'Key' => 'test.txt']);
        $handler->append(new AwsException('Access Denied', $command, ['code' => 'AccessDenied']));

        $this->expectException(\Pop\Storage\Exception\UnableToReadFileException::class);
        $storage->fetchFileInfo('test.txt');
    }

    public function testMd5FileThrowsUnableToReadFileExceptionWhenTheHeadObjectCallFails()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $client  = $this->createClient($handler);
        $storage = Storage::createS3($bucket, $client);

        $command = $client->getCommand('HeadObject', ['Bucket' => str_replace('s3://', '', $bucket), 'Key' => 'test.txt']);
        $handler->append(new AwsException('Access Denied', $command, ['code' => 'AccessDenied']));

        $this->expectException(\Pop\Storage\Exception\UnableToReadFileException::class);
        $storage->md5File('test.txt');
    }

    public function testListFilesAppliesSearchFilter()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([
            new Result(['Contents' => [
                ['Key' => 'keep.txt', 'Size' => 4],
                ['Key' => 'skip.log', 'Size' => 4],
            ]]),
        ]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));

        $this->assertEquals(['keep.txt'], $storage->listFiles('*.txt'));
    }

    public function testListDirsAppliesSearchFilter()
    {
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([
            new Result(['CommonPrefixes' => [['Prefix' => 'keep/'], ['Prefix' => 'other/']]]),
        ]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));

        $this->assertEquals(['keep/'], $storage->listDirs('keep*'));
    }

    public function testRmdirDeletesAllObjectsUnderThePrefixInABatch()
    {
        $bucket    = $this->uniqueBucket();
        $handler   = new MockHandler([]);
        $client    = $this->createClient($handler);
        $storage   = Storage::createS3($bucket, $client);
        $rawBucket = str_replace('s3://', '', $bucket);

        // is_dir()'s stream-wrapper pre-check: HeadObject on the bare key 404s, so the
        // wrapper falls back to a prefix listing to confirm objects exist under it.
        $headCommand = $client->getCommand('HeadObject', ['Bucket' => $rawBucket, 'Key' => 'sub']);
        $handler->append(new S3Exception('Not Found', $headCommand, [
            'code'     => 'NotFound',
            'response' => new \GuzzleHttp\Psr7\Response(404),
        ]));
        $handler->append(new Result(['Contents' => [['Key' => 'sub/a.txt']], 'CommonPrefixes' => []]));

        // rmdir()'s own listing of everything under the prefix, then a single batched delete.
        $handler->append(new Result(['Contents' => [
            ['Key' => 'sub/a.txt', 'Size' => 4],
            ['Key' => 'sub/b.txt', 'Size' => 4],
        ]]));
        $handler->append(new Result(['Deleted' => [['Key' => 'sub/a.txt'], ['Key' => 'sub/b.txt']]]));

        $storage->rmdir('sub');

        $lastCommand = $handler->getLastCommand();
        $this->assertEquals('DeleteObjects', $lastCommand->getName());
        $this->assertEquals(
            [['Key' => 'sub/a.txt'], ['Key' => 'sub/b.txt']],
            $lastCommand['Delete']['Objects']
        );
    }

    public function testRmdirThrowsUnableToDeleteDirectoryExceptionWhenDeleteObjectsReturnsErrors()
    {
        $bucket    = $this->uniqueBucket();
        $handler   = new MockHandler([]);
        $client    = $this->createClient($handler);
        $storage   = Storage::createS3($bucket, $client);
        $rawBucket = str_replace('s3://', '', $bucket);

        $headCommand = $client->getCommand('HeadObject', ['Bucket' => $rawBucket, 'Key' => 'sub']);
        $handler->append(new S3Exception('Not Found', $headCommand, [
            'code'     => 'NotFound',
            'response' => new \GuzzleHttp\Psr7\Response(404),
        ]));
        $handler->append(new Result(['Contents' => [['Key' => 'sub/a.txt']], 'CommonPrefixes' => []]));
        $handler->append(new Result(['Contents' => [['Key' => 'sub/a.txt', 'Size' => 4]]]));
        $handler->append(new Result(['Errors' => [['Key' => 'sub/a.txt', 'Code' => 'AccessDenied']]]));

        $this->expectException(\Pop\Storage\Exception\UnableToDeleteDirectoryException::class);
        $storage->rmdir('sub');
    }

    public function testRmdirThrowsDirectoryNotFoundExceptionForMissingDirectory()
    {
        // rmdir()'s happy path depends on the S3 stream wrapper recognizing a HeadObject
        // response as directory-shaped (a zero-byte key ending in '/'), which is AWS SDK
        // internal stat-formatting detail rather than this adapter's own logic - the
        // not-found path below is the one that actually exercises our code (the is_dir()
        // guard in S3::rmdir()) without depending on that internal behavior.
        $bucket  = $this->uniqueBucket();
        $handler = new MockHandler([]);
        $storage = Storage::createS3($bucket, $this->createClient($handler));

        $this->expectException(\Pop\Storage\Exception\DirectoryNotFoundException::class);
        $storage->rmdir('does-not-exist');
    }

}
