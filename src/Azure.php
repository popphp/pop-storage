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

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
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
     * Azure Blob client
     * @var BlobRestProxy
     */
    protected $client = null;

    /**
     * Constructor
     *
     * @param string   $location
     * @param BlobRestProxy $client
     */
    public function __construct($location, BlobRestProxy $client)
    {
        parent::__construct($location);
        $this->setClient($client);
    }

    /**
     * Set Azure Blob client
     *
     * @param  BlobRestProxy $client
     * @return Azure
     */
    public function setClient(BlobRestProxy $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Get Azure Blob client
     *
     * @return BlobRestProxy
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Upload file
     *
     * @param  array   $file
     * @param  Upload  $upload
     * @param  string  $to
     * @param  boolean $secure
     * @return string
     */
    public function uploadFile(array $file, Upload $upload, $to = null, $secure = true)
    {
//        if ($upload->test($file)) {
//            $filename = $upload->checkFilename($file['name']);
//            $location = $this->location . $to . '/' . $filename;
//            file_put_contents($location, file_get_contents($file['tmp_name']));
//            return $filename;
//        } else {
//            return false;
//        }
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir($dir)
    {
//        $iterator = new RecursiveIteratorIterator(
//            new RecursiveDirectoryIterator($this->location . $dir, RecursiveDirectoryIterator::SKIP_DOTS),
//            RecursiveIteratorIterator::CHILD_FIRST
//        );
//
//        foreach ($iterator as $fileInfo) {
//            if ($fileInfo->isDir()) {
//                rmdir((string)$fileInfo);
//            } else {
//                unlink((string)$fileInfo);
//            }
//        }
//
//        rmdir($this->location . $dir);
    }

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir($dir)
    {
//        if (substr($dir, 0, 1) == '/') {
//            $dir = substr($dir, 1);
//        }
//        $this->client->putObject([
//            'Bucket' => str_replace('s3://', '', $this->location),
//            'Key'    => $dir . '/',
//            'Body'   => ''
//        ]);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File($filename)
    {
//        if (substr($filename, 0, 1) == '/') {
//            $filename = substr($filename, 1);
//        }
//        $fileObject = $this->client->getObject([
//            'Bucket' => str_replace('s3://', '', $this->location),
//            'Key'    => $filename,
//        ]);
//
//        return (isset($fileObject['ETag'])) ? str_replace('"', '', $fileObject['ETag']) : false;
    }

}