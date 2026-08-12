<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Storage;

class LocalRecursiveListingTest extends TestCase
{

    protected function setUp(): void
    {
        @mkdir(__DIR__ . '/../tmp/recursive-test/nested', 0777, true);
        file_put_contents(__DIR__ . '/../tmp/recursive-test/top.txt', 'top');
        file_put_contents(__DIR__ . '/../tmp/recursive-test/nested/inner.txt', 'inner');
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/../tmp/recursive-test/nested/inner.txt');
        @unlink(__DIR__ . '/../tmp/recursive-test/top.txt');
        @rmdir(__DIR__ . '/../tmp/recursive-test/nested');
        @rmdir(__DIR__ . '/../tmp/recursive-test');
    }

    public function testListFilesNonRecursiveOnlyReturnsTopLevel()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/recursive-test');

        $files = $storage->listFiles();

        $this->assertContains('top.txt', $files);
        $this->assertNotContains('nested/inner.txt', $files);
    }

    public function testListFilesRecursiveIncludesNestedRelativePaths()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/recursive-test');

        $files = $storage->listFiles(null, true);

        $this->assertContains('top.txt', $files);
        $this->assertContains('nested' . DIRECTORY_SEPARATOR . 'inner.txt', $files);
    }

    public function testListDirsNonRecursiveOnlyReturnsTopLevel()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/recursive-test');

        $dirs = $storage->listDirs();

        $this->assertContains('nested' . DIRECTORY_SEPARATOR, $dirs);
    }

    public function testListDirsRecursiveReturnsTrailingSeparatorPaths()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp/recursive-test');

        $dirs = $storage->listDirs(null, true);

        $this->assertContains('nested' . DIRECTORY_SEPARATOR, $dirs);
        $this->assertNotContains('top.txt', $dirs);
    }

}
