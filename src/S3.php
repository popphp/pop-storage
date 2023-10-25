<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage;

use Aws\S3\S3Client;
use Pop\Http\Server\Upload;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * AWS S3 storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class S3 extends AbstractAdapter
{

    /**
     * S3 client
     * @var ?S3Client
     */
    protected ?S3Client $client = null;

    /**
     * Constructor
     *
     * @param string   $location
     * @param S3Client $client
     */
    public function __construct(string $location, S3Client $client)
    {
        parent::__construct($location);
        $this->setClient($client);
    }

    /**
     * Set S3 client
     *
     * @param  S3Client $client
     * @return S3
     */
    public function setClient(S3Client $client): S3
    {
        $this->client = $client;
        $this->client->registerStreamWrapper();
        return $this;
    }

    /**
     * Get S3 client
     *
     * @return ?S3Client
     */
    public function getClient(): ?S3Client
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
        if (is_array($file) && isset($file['name']) && isset($file['tmp_name']) && ($upload !== null) && ($upload->test($file))) {
            $filename = $upload->checkFilename($file['name']);
            $location = $this->location . $dest . '/' . $filename;
            file_put_contents($location, file_get_contents($file['tmp_name']));
            return $filename;
        } else if (is_file($file)) {
            $location = $this->location . $dest . '/' . basename($file);
            file_put_contents($location, file_get_contents($file));
            return $file;
        } else {
            return '';
        }
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
        $location = $this->location . $folder . '/' . $filename;
        file_put_contents($location, $fileStream);
        return $filename;
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->location . $dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir((string)$fileInfo);
            } else {
                unlink((string)$fileInfo);
            }
        }

        rmdir($this->location . $dir);
    }

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir(string $dir): void
    {
        if (str_starts_with($dir, '/')) {
            $dir = substr($dir, 1);
        }
        $this->client->putObject([
            'Bucket' => str_replace('s3://', '', $this->location),
            'Key'    => $dir . '/',
            'Body'   => ''
        ]);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File(string $filename): string
    {
        if (str_starts_with($filename, '/')) {
            $filename = substr($filename, 1);
        }
        $fileObject = $this->client->getObject([
            'Bucket' => str_replace('s3://', '', $this->location),
            'Key'    => $filename,
        ]);

        return (isset($fileObject['ETag'])) ? str_replace('"', '', $fileObject['ETag']) : false;
    }

}