<?php

namespace Pop\Storage\Test\Adapter\Azure;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Adapter\Azure\Auth;

class AuthTest extends TestCase
{

    public function testAccountNameAndKeyAccessors()
    {
        $auth = new Auth('testaccount', base64_encode('test-account-key'));

        $this->assertTrue($auth->hasAccountName());
        $this->assertEquals('testaccount', $auth->getAccountName());
        $this->assertTrue($auth->hasAccountKey());
        $this->assertEquals(base64_encode('test-account-key'), $auth->getAccountKey());
    }

    public function testGetBaseUri()
    {
        $auth = new Auth('testaccount', base64_encode('test-account-key'));

        $this->assertEquals('https://testaccount.blob.core.windows.net', $auth->getBaseUri());
    }

}
