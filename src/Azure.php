<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage;

use Pop\Storage\Azure\Auth;
use Pop\Http\Client;
use Pop\Http\Client\Request;
use Pop\Http\Server\Upload;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Azure storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.0.0
 */
class Azure extends AbstractAdapter
{

    /**
     * HTTP client
     * @var ?Client
     */
    protected ?Client $client = null;

    /**
     * Azure auth object
     * @var ?Auth
     */
    protected ?Auth $auth = null;

    /**
     * Constructor
     *
     * @param string $location
     * @param Client $client
     * @param Auth   $auth
     */
    public function __construct(string $location, Client $client, Auth $auth)
    {
        parent::__construct($location);
        $this->setClient($client);
        $this->setAuth($auth);
    }

    /**
     * Create Azure client
     *
     * @param  string $accountName
     * @param  string $accountKey
     * @throws \Pop\Http\Client\Exception
     * @return Azure
     */
    public static function create(string $accountName, string $accountKey): Azure
    {
        $auth    = new Azure\Auth($accountName, $accountKey);

        $request = new Request('https://' . $accountName . '.blob.core.windows.net');
        $request->addHeader('Date', gmdate('D, d M Y H:i:s T'))
            ->addHeader('Host', $accountName . '.blob.core.windows.net')
            ->addHeader('Content-Type', Client\Request::URLFORM)
            ->addHeader('User-Agent', 'pop-storage/2.0.0 (PHP ' . PHP_VERSION . ')/' . PHP_OS)
            ->addHeader('x-ms-client-request-id', uniqid())
            ->addHeader('x-ms-version', '2023-11-03');

        return new self($accountName, new Client($request), $auth);
    }

    /**
     * Set client
     *
     * @param  Client $client
     * @return Azure
     */
    public function setClient(Client $client): Azure
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Get client
     *
     * @return ?Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Has client
     *
     * @return bool
     */
    public function hasClient(): bool
    {
        return ($this->client !== null);
    }

    /**
     * Set auth
     *
     * @param  Auth $auth
     * @return Azure
     */
    public function setAuth(Auth $auth): Azure
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * Get auth
     *
     * @return ?Auth
     */
    public function getAuth(): ?Auth
    {
        return $this->auth;
    }

    /**
     * Has auth
     *
     * @return bool
     */
    public function hasAuth(): bool
    {
        return ($this->auth !== null);
    }

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  ?string $dest
     * @param  ?Upload $upload
     * @return string
     */
    public function uploadFile(mixed $file, ?string $dest = null, ?Upload $upload = null): string
    {
        return '';
    }

    /**
     * Upload file stream
     *
     * @param  string  $fileStream
     * @param  string  $filename
     * @param  ?string $folder
     * @return string
     */
    public function uploadFileStream(string $fileStream, string $filename, ?string $folder = null): string
    {
        return '';
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir(string $dir): void
    {

    }

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir(string $dir): void
    {

    }

    /**
     * List directories
     *
     * @return void
     */
    public function listDirs(): void
    {
        $this->client->getRequest()->setQuery(['comp' => 'list']);
        $this->auth->signRequest($this->client->getRequest());

        $response = $this->client->send();

        print_r($response->getParsedResponse());
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File(string $filename): string
    {
        return '';
    }

}