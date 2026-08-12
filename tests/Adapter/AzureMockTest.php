<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;
use Pop\Storage\Storage;

class AzureMockTest extends TestCase
{

    public function testSetHandlerIsUsedByInitClient()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'headers' => ['Content-Length' => '4']]));

        $azure = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $azure->setHandler($handler);
        $azure->initClient('GET', [], false);

        $this->assertTrue($azure->hasHandler());
        $this->assertSame($handler, $azure->getHandler());

        $response = $azure->getClient()->send();

        $this->assertEquals(200, $response->getCode());
        $this->assertCount(1, $handler->getRequests());
    }

    public function testHasClientAndHasAuthAreTrueAfterConstruction()
    {
        $azure = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));

        $this->assertTrue($azure->hasClient());
        $this->assertTrue($azure->hasAuth());
        $this->assertInstanceOf(Auth::class, $azure->getAuth());
    }

    public function testMkdirAndRmdirAreIntentionalNoOps()
    {
        // Azure has no create/delete-empty-directory operation - a "directory" (prefix) is
        // implied by whatever files exist under it, so these are documented no-ops (no HTTP
        // dispatch at all) rather than silent failures. No handler is set, which would surface
        // a real network attempt as a loud failure if either method dispatched anything.
        $azure = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));

        $azure->mkdir('anything');
        $azure->rmdir('anything');

        $this->addToAssertionCount(1);
    }

    public function testAzureCreateFactoryBuildsAWorkingAdapter()
    {
        $azure = Azure::create('test-account', base64_encode('test-key'));

        $this->assertInstanceOf(Azure::class, $azure);
        $this->assertTrue($azure->hasAuth());
        $this->assertEquals('test-account', $azure->getAuth()->getAccountName());
    }

    public function testStorageCreateAzureFactoryAppliesTheContainerAsBaseDir()
    {
        $storage = Storage::createAzure('test-account', base64_encode('test-key'), 'my-container');

        $this->assertInstanceOf(Storage::class, $storage);
        $this->assertEquals('my-container', $storage->getBaseDir());
    }

}
