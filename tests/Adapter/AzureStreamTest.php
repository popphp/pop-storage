<?php

namespace Pop\Storage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use Pop\Http\Client\Handler\Mock;
use Pop\Http\Client\Response;
use Pop\Storage\Adapter\Azure;
use Pop\Storage\Adapter\Azure\Auth;

class AzureStreamTest extends TestCase
{

    protected function createAdapter(Mock $handler): Azure
    {
        $adapter = new Azure('my-container', new Auth('test-account', base64_encode('test-key')));
        $adapter->setHandler($handler);
        return $adapter;
    }

    public function testPutFileStreamSendsResourceContentsAsBody()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 201]));
        $adapter = $this->createAdapter($handler);

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'streamed azure contents');
        rewind($resource);

        $adapter->putFileStream('streamed.txt', $resource);
        fclose($resource);

        $sentBody = $handler->getLastRequest()->getBodyContent();
        $this->assertEquals('streamed azure contents', $sentBody);
    }

    public function testFetchFileStreamReturnsReadableResource()
    {
        $handler = new Mock();
        $handler->queue(new Response(['code' => 200, 'body' => 'downloaded contents']));
        $adapter = $this->createAdapter($handler);

        $resource = $adapter->fetchFileStream('remote.txt');

        $this->assertIsResource($resource);
        $this->assertEquals('downloaded contents', stream_get_contents($resource));
        fclose($resource);
    }

}
