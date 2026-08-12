<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Exception\UnsupportedOperationException;
use Pop\Storage\Storage;

class LocalTemporaryUrlTest extends TestCase
{

    public function testGetTemporaryUrlThrowsUnsupportedOperationException()
    {
        $storage = Storage::createLocal(__DIR__ . '/../tmp');

        $this->expectException(UnsupportedOperationException::class);
        $storage->getTemporaryUrl('test.txt');
    }

}
