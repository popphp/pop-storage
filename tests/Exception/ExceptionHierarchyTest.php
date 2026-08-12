<?php

namespace Pop\Storage\Test\Exception;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception as StorageException;
use Pop\Storage\Exception\DirectoryNotFoundException;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToCopyFileException;
use Pop\Storage\Exception\UnableToCreateDirectoryException;
use Pop\Storage\Exception\UnableToDeleteDirectoryException;
use Pop\Storage\Exception\UnableToDeleteFileException;
use Pop\Storage\Exception\UnableToGenerateTemporaryUrlException;
use Pop\Storage\Exception\UnableToMoveFileException;
use Pop\Storage\Exception\UnableToReadFileException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Storage\Exception\UnsupportedOperationException;

class ExceptionHierarchyTest extends TestCase
{

    public static function exceptionClassProvider(): array
    {
        return [
            [FileNotFoundException::class],
            [DirectoryNotFoundException::class],
            [UnableToWriteFileException::class],
            [UnableToReadFileException::class],
            [UnableToDeleteFileException::class],
            [UnableToCopyFileException::class],
            [UnableToMoveFileException::class],
            [UnableToCreateDirectoryException::class],
            [UnableToDeleteDirectoryException::class],
            [UnableToGenerateTemporaryUrlException::class],
            [UnsupportedOperationException::class],
            [PathTraversalException::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exceptionClassProvider')]
    public function testExtendsStorageException(string $class)
    {
        $exception = new $class('Test message');
        $this->assertInstanceOf(StorageException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testWrapsPreviousException()
    {
        $previous = new \RuntimeException('Underlying cause');
        $exception = new FileNotFoundException('File not found', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

}
