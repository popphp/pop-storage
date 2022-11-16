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

use Pop\Dir\Dir;
use Pop\Http\Server\Upload;

/**
 * Local storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.0.0
 */
class Local extends AbstractAdapter
{

    /**
     * Is local flag
     * @var boolean
     */
    protected $local = true;

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
        return $upload->upload($file, $to, $secure);
    }

    /**
     * Remove a directory
     *
     * @param  string $dir
     * @return void
     */
    public function rmdir($dir)
    {
        $dir = new Dir($this->location . $dir);
        $dir->emptyDir(true);
    }

    /**
     * Make a directory
     *
     * @param  string $dir
     * @return void
     */
    public function mkdir($dir)
    {
        mkdir($this->location . $dir);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string
     */
    public function md5File($filename)
    {
        return md5_file($this->checkFileLocation($filename));
    }

}