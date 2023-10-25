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

use Pop\Dir\Dir;
use Pop\Http\Server\Upload;

/**
 * Local storage adapter class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Local extends AbstractAdapter
{

    /**
     * Is local flag
     * @var bool
     */
    protected bool $local = true;

    /**
     * Upload file
     *
     * @param  mixed   $file
     * @param  ?string $dest
     * @param  ?Upload $upload
     * @param  bool    $secure
     * @throws Exception
     * @return string
     */
    public function uploadFile(mixed $file, ?string $dest = null, ?Upload $upload = null, bool $secure = true): string
    {
        if (!is_array($file)) {
            throw new Exception('Error: The file parameter must be an array.');
        }
        return $upload->upload($file, $dest, $secure);
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
        if (!file_exists($this->location . $folder)) {
            $this->mkdir($folder);
        }
        $location = $this->location . $folder . '/' . $filename;
        file_put_contents($location, $fileStream);
        return $filename;
    }

    /**
     * Remove a directory
     *
     * @param string $dir
     * @throws \Pop\Dir\Exception
     * @return void
     */
    public function rmdir(string $dir): void
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
    public function mkdir(string $dir): void
    {
        mkdir($this->location . $dir);
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @throws Exception
     * @return string
     */
    public function md5File(string $filename): string
    {
        return md5_file($this->checkFileLocation($filename));
    }

}