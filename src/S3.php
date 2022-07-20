<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1
 */
class S3 extends AbstractAdapter
{

    /**
     * S3 client
     * @var S3Client
     */
    protected $client = null;

    /**
     * Constructor
     *
     * @param string   $location
     * @param S3Client $client
     */
    public function __construct($location, S3Client $client)
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
    public function setClient(S3Client $client)
    {
        $this->client = $client;
        $this->client->registerStreamWrapper();
        return $this;
    }

    /**
     * Get S3 client
     *
     * @return S3Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  string  $folder
     * @param  Upload  $upload
     * @return string
     */
    public function uploadFile($file, $folder = null, Upload $upload = null)
    {
        if (is_array($file) && isset($file['name']) && isset($file['tmp_name']) && (null !== $upload) && ($upload->test($file))) {
            $filename = $upload->checkFilename($file['name']);
            $location = $this->location . $folder . '/' . $filename;
            file_put_contents($location, file_get_contents($file['tmp_name']));
            return $filename;
        } else if (is_file($file)) {
            $location = $this->location . $folder . '/' . $file;
            file_put_contents($location, file_get_contents($file));
            return $file;
        } else {
            return false;
        }
    }

    /**
     * Upload file
     *
     * @param  string  $fileStream
     * @param  string  $filename
     * @param  string  $folder
     * @return string
     */
    public function uploadFileStream($fileStream, $filename, $folder = null)
    {
        $location = $this->location . $folder . '/' . $filename;
        file_put_contents($location, file_get_contents($filename));
        return $filename;
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir($dir)
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
    public function mkdir($dir)
    {
        if (substr($dir, 0, 1) == '/') {
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
    public function md5File($filename)
    {
        if (substr($filename, 0, 1) == '/') {
            $filename = substr($filename, 1);
        }
        $fileObject = $this->client->getObject([
            'Bucket' => str_replace('s3://', '', $this->location),
            'Key'    => $filename,
        ]);

        return (isset($fileObject['ETag'])) ? str_replace('"', '', $fileObject['ETag']) : false;
    }

}