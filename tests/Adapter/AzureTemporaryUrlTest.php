<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;

class AzureTemporaryUrlTest extends TestCase
{

    public function testGetTemporaryUrlReturnsUrlWithSasToken()
    {
        $adapter = new Azure('my-container', new Auth('testaccount', base64_encode('test-key')));

        $url = $adapter->getTemporaryUrl('test.pdf', 300);

        $this->assertStringStartsWith('https://testaccount.blob.core.windows.net/my-container/test.pdf?', $url);
        $this->assertStringContainsString('sp=r', $url);
        $this->assertStringContainsString('sig=', $url);
    }

}
