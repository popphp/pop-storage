<?php

namespace Pop\Storage\Test\Adapter\Azure;

use PHPUnit\Framework\TestCase;
use Pop\Storage\Adapter\Azure\Auth;

class AuthSasTest extends TestCase
{

    public function testGenerateSasTokenProducesExpectedFieldsAndSignature()
    {
        $accountKey = base64_encode('test-account-key');
        $auth = new Auth('testaccount', $accountKey);

        $expiresAt = new \DateTime('2026-08-09T15:30:00Z');
        $token = $auth->generateSasToken('/testcontainer/test.pdf', 900, 'r', $expiresAt);

        parse_str($token, $params);

        $this->assertEquals('r', $params['sp']);
        $this->assertEquals('b', $params['sr']);
        $this->assertEquals('2025-01-05', $params['sv']);
        $this->assertEquals('2026-08-09T15:30:00Z', $params['se']);
        $this->assertArrayHasKey('sig', $params);

        $stringToSign = implode("\n", [
            'r', '', '2026-08-09T15:30:00Z', '/blob/testaccount/testcontainer/test.pdf',
            '', '', 'https', '2025-01-05', 'b', '', '', '', '', '', '', '',
        ]);
        $expectedSignature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($accountKey), true));

        $this->assertEquals($expectedSignature, urldecode($params['sig']));
    }

    public function testGenerateSasTokenDefaultExpiryIsUtc()
    {
        $accountKey = base64_encode('test-account-key');
        $auth = new Auth('testaccount', $accountKey);

        $expiresInSeconds = 900;
        $before = new \DateTime('now', new \DateTimeZone('UTC'));
        $token = $auth->generateSasToken('/testcontainer/test.pdf', $expiresInSeconds, 'r');
        $after = new \DateTime('now', new \DateTimeZone('UTC'));

        parse_str($token, $params);

        $this->assertArrayHasKey('se', $params);

        $expectedEarliest = (clone $before)->modify('+' . $expiresInSeconds . ' seconds');
        $expectedLatest    = (clone $after)->modify('+' . $expiresInSeconds . ' seconds');

        $actual = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $params['se'], new \DateTimeZone('UTC'));

        $this->assertNotFalse($actual);
        $this->assertGreaterThanOrEqual($expectedEarliest->getTimestamp() - 2, $actual->getTimestamp());
        $this->assertLessThanOrEqual($expectedLatest->getTimestamp() + 2, $actual->getTimestamp());
    }

}
