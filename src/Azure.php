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

use Pop\Http\Client;
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
     * Azure HTTP client
     * @var ?Client
     */
    protected ?Client $client = null;

    /**
     * Constructor
     *
     * @param string $location
     * @param Client $client
     */
    public function __construct(string $location, Client $client)
    {
        parent::__construct($location);
        $this->setClient($client);
    }

    /**
     * Set Azure Blob client
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
     * Get Azure Blob client
     *
     * @return ?Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
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