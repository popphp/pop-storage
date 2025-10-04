<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter;

use Pop\Storage\Adapter\Azure\Auth;
use Pop\Http\Client;
use Pop\Http\Client\Request;
use Pop\Utils\File;

/**
 * Storage adapter Azure class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
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
     * @param Auth   $auth
     */
    public function __construct(string $location, Auth $auth)
    {
        parent::__construct($location);
        $this->setAuth($auth);
        $this->initClient();
    }

    /**
     * Create Azure client
     *
     * @param  string $accountName
     * @param  string $accountKey
     * @return Azure
     */
    public static function create(string $accountName, string $accountKey): Azure
    {
        return new self($accountName, new Azure\Auth($accountName, $accountKey));
    }

    /**
     * Initialize client
     *
     * @param  string $method
     * @param  array  $headers
     * @param  bool   $auto
     * @return Azure
     */
    public function initClient(string $method = 'GET', array $headers = [], bool $auto = true): Azure
    {
        $request = new Request('/', $method);
        $request->addHeader('Date', gmdate('D, d M Y H:i:s T'))
            ->addHeader('Host', $this->auth->getAccountName() . '.blob.core.windows.net')
            ->addHeader('Content-Type', Client\Request::URLENCODED)
            ->addHeader('User-Agent', 'pop-storage/2.1.0 (PHP ' . PHP_VERSION . ')/' . PHP_OS)
            ->addHeader('x-ms-client-request-id', uniqid())
            ->addHeader('x-ms-version', '2025-01-05');

        if (!empty($headers)) {
            foreach ($headers as $header => $value) {
                $request->addHeader($header, $value);
            }
        }

        $this->setClient(new Client(
            $request, [
                'base_uri' => $this->auth->getBaseUri(),
                'auto'     => $auto
            ]
        ));

        return $this;
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
     * Make directory
     *
     * @param  string $directory
     * @return void
     */
    public function mkdir(string $directory): void
    {
        /**
         * Azure storage doesn't allow the creation of empty "directories" (prefixes.)
         * A new "directory" (prefix) is automatically created with an uploaded file that utilizes a prefix
         */
    }

    /**
     * Remove a directory
     *
     * @param  string $directory
     * @return void
     */
    public function rmdir(string $directory): void
    {
        /**
         * Azure storage doesn't allow the direct removal of "directories" (prefixes.)
         * A "directory" (prefix) is automatically removed when the last file that utilizes the prefix is deleted.
         */
    }

    /**
     * List directories
     *
     * @param  ?string $search
     * @return array
     */
    public function listDirs(?string $search = null): array
    {
        $dirs = [];

        $uri = '/' . $this->baseDirectory;

        $params = ['restype' => 'container', 'comp' => 'list'];

        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $params['prefix'] = $directory;
        }

        $this->initClient();
        $this->client->getRequest()->setQuery($params);
        $this->client->getRequest()->setUri($uri);
        $this->auth->signRequest($this->client->getRequest());

        $response = $this->client->send();

        if (is_array($response) && !empty($response['Blobs']) && !empty($response['Blobs']['Blob'])) {
            $blobs = (!isset($response['Blobs']['Blob'][0])) ? [$response['Blobs']['Blob']] : $response['Blobs']['Blob'];
            foreach ($blobs as $blob) {
                if (isset($blob['Properties']) && isset($blob['Properties']['ResourceType']) &&
                    ($blob['Properties']['ResourceType'] == 'directory')) {
                    if ((!isset($params['prefix']) && !str_contains($blob['Name'], '/')) ||
                        (isset($params['prefix']) && str_contains($blob['Name'], '/'))) {
                        $dirs[] = $blob['Name'];
                    }
                }
            }
        }

        if ($search !== null) {
            $dirs = $this->searchFilter($dirs, $search);
        }

        return $dirs;
    }

    /**
     * List files
     *
     * @param  ?string $search
     * @return array
     */
    public function listFiles(?string $search = null): array
    {
        $files = [];

        $uri = '/' . $this->baseDirectory;

        $params = ['restype' => 'container', 'comp' => 'list'];

        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $params['prefix'] = $directory;
        }

        $this->initClient();
        $this->client->getRequest()->setQuery($params);
        $this->client->getRequest()->setUri($uri);
        $this->auth->signRequest($this->client->getRequest());

        $response = $this->client->send();

        if (is_array($response) && !empty($response['Blobs']) && !empty($response['Blobs']['Blob'])) {
            $blobs = (!isset($response['Blobs']['Blob'][0])) ? [$response['Blobs']['Blob']] : $response['Blobs']['Blob'];
            foreach ($blobs as $blob) {
                if (isset($blob['Properties']) && isset($blob['Properties']['ResourceType']) &&
                    ($blob['Properties']['ResourceType'] == 'file')) {
                    if ((!isset($params['prefix']) && !str_contains($blob['Name'], '/')) ||
                        (isset($params['prefix']) && str_contains($blob['Name'], '/'))) {
                        $files[] = $blob['Name'];
                    }
                }
            }
        }

        if ($search !== null) {
            $files = $this->searchFilter($files, $search);
        }

        return $files;
    }

    /**
     * Put file
     *
     * @param  string $fileFrom
     * @param  bool $copy
     * @throws Exception|Client\Handler\Exception|\Pop\Http\Exception|\Pop\Utils\Exception
     * @return void
     */
    public function putFile(string $fileFrom, bool $copy = true): void
    {
        if (file_exists($fileFrom)) {
            $uri = '/' . $this->baseDirectory . '/' . basename($fileFrom);
            if ($this->baseDirectory !== $this->directory) {
                $directory = str_replace($this->baseDirectory, '', $this->directory);
                if (str_ends_with($directory, '/')) {
                    $directory = substr($directory, 0, -1);
                }
                $uri = $directory . $uri;
            }

            $fileContents = file_get_contents($fileFrom);

            $this->initClient('PUT', [
                'content-length'         => strlen($fileContents),
                'x-ms-blob-type'         => 'BlockBlob',
                'x-ms-blob-content-type' => File::getFileMimeType($fileFrom)
            ]);
            $this->client->getRequest()->setUri($uri);
            $this->client->getRequest()->setBody($fileContents);
            $this->auth->signRequest($this->client->getRequest());
            $this->client->send();
        }
    }

    /**
     * Put file contents
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function putFileContents(string $filename, string $fileContents): void
    {
        $uri = '/' . $this->baseDirectory . '/' . $filename;
        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $uri = $directory . $uri;
        }

        $this->initClient('PUT', [
            'content-length'         => strlen($fileContents),
            'x-ms-blob-type'         => 'BlockBlob',
            'x-ms-blob-content-type' => File::getFileMimeType($filename)
        ]);
        $this->client->getRequest()->setUri($uri);
        $this->client->getRequest()->setBody($fileContents);
        $this->auth->signRequest($this->client->getRequest());
        $this->client->send();
    }

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @throws Exception
     * @return void
     */
    public function uploadFile(array $file): void
    {
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            throw new Exception('Error: The uploaded file array was not valid');
        }
        if (file_exists($file['tmp_name'])) {
            $uri = '/' . $this->baseDirectory . '/' . $file['name'];
            if ($this->baseDirectory !== $this->directory) {
                $directory = str_replace($this->baseDirectory, '', $this->directory);
                if (str_ends_with($directory, '/')) {
                    $directory = substr($directory, 0, -1);
                }
                $uri = $directory . $uri;
            }

            $fileContents = file_get_contents($file['tmp_name']);

            $this->initClient('PUT', [
                'content-length'         => strlen($fileContents),
                'x-ms-blob-type'         => 'BlockBlob',
                'x-ms-blob-content-type' => File::getFileMimeType($file['name'])
            ]);
            $this->client->getRequest()->setUri($uri);
            $this->client->getRequest()->setBody($fileContents);
            $this->auth->signRequest($this->client->getRequest());
            $this->client->send();
        }
    }

    /**
     * Copy file
     *
     * @param  string $sourceFile
     * @param  string $destFile
     * @return void
     */
    public function copyFile(string $sourceFile, string $destFile): void
    {
        $sourceFileInfo = $this->fetchFileInfo($sourceFile);

        if (is_array($sourceFileInfo) && isset($sourceFileInfo['headers']) &&
            isset($sourceFileInfo['headers']['Content-Type']) && (!$sourceFileInfo['isError'])) {
            $sourceUri = (!str_starts_with($sourceFile, '/')) ? '/' . $this->baseDirectory . '/' . $sourceFile : $sourceFile;
            $destUri   = (!str_starts_with($destFile, '/')) ? '/' . $this->baseDirectory . '/' . $destFile : $destFile;

            if ($this->baseDirectory !== $this->directory) {
                $directory = str_replace($this->baseDirectory, '', $this->directory);
                if (str_ends_with($directory, '/')) {
                    $directory = substr($directory, 0, -1);
                }
                $sourceUri = $directory . $sourceUri;
                $destUri   = $directory . $destUri;
            }

            $this->initClient('PUT', [
                'content-length'   => $sourceFileInfo['headers']['Content-Length'],
                'x-ms-copy-source' => $this->auth->getBaseUri() . $sourceUri,
            ]);
            $this->client->getRequest()->setUri($destUri);
            $this->auth->signRequest($this->client->getRequest());
            $this->client->send();
        }
    }

    /**
     * Copy file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function copyFileToExternal(string $sourceFile, string $externalFile): void
    {
        $sourceFileInfo = $this->fetchFileInfo($sourceFile);

        if (is_array($sourceFileInfo) && isset($sourceFileInfo['headers']) &&
            isset($sourceFileInfo['headers']['Content-Type']) && (!$sourceFileInfo['isError'])) {
            $sourceUri = (!str_starts_with($sourceFile, '/')) ? '/' . $this->baseDirectory . '/' . $sourceFile : $sourceFile;

            if ($this->baseDirectory !== $this->directory) {
                $directory = str_replace($this->baseDirectory, '', $this->directory);
                if (str_ends_with($directory, '/')) {
                    $directory = substr($directory, 0, -1);
                }
                $sourceUri = $directory . $sourceUri;
            }

            $this->initClient('PUT', [
                'content-length'   => $sourceFileInfo['headers']['Content-Length'],
                'x-ms-copy-source' => $this->auth->getBaseUri() . $sourceUri,
            ]);
            $this->client->getRequest()->setUri($externalFile);
            $this->auth->signRequest($this->client->getRequest());
            $this->client->send();
        }
    }

    /**
     * Copy file from a location external to the current location
     *
     * @param  string $externalFile
     * @param  string $destFile
     * @return void
     */
    public function copyFileFromExternal(string $externalFile, string $destFile): void
    {
        $this->initClient('HEAD', [], false);
        $this->client->getRequest()->setUri($externalFile);
        $this->auth->signRequest($this->client->getRequest());
        $response = $this->client->send();

        if (($response->isSuccess()) && ($response->hasHeader('Content-Length'))) {
            $destUri = (!str_starts_with($destFile, '/')) ? '/' . $this->baseDirectory . '/' . $destFile : $destFile;

            if ($this->baseDirectory !== $this->directory) {
                $directory = str_replace($this->baseDirectory, '', $this->directory);
                if (str_ends_with($directory, '/')) {
                    $directory = substr($directory, 0, -1);
                }
                $destUri   = $directory . $destUri;
            }

            $this->initClient('PUT', [
                'content-length'   => $response->getHeader('Content-Length')->getValueAsString(),
                'x-ms-copy-source' => $this->auth->getBaseUri() . $externalFile,
            ]);
            $this->client->getRequest()->setUri($destUri);
            $this->auth->signRequest($this->client->getRequest());
            $this->client->send();
        }
    }

    /**
     * Move file to a location external to the current location
     *
     * @param  string $sourceFile
     * @param  string $externalFile
     * @return void
     */
    public function moveFileToExternal(string $sourceFile, string $externalFile): void
    {
        $this->copyFileToExternal($sourceFile, $externalFile);
        $this->deleteFile($sourceFile);
    }

    /**
     * Move file from a location external to the current location
     *
     * @param  string  $externalFile
     * @param  string  $destFile
     * @param  ?string $snapshots ['include', 'only', null]
     * @return void
     */
    public function moveFileFromExternal(string $externalFile, string $destFile, ?string $snapshots = 'include'): void
    {
        $this->copyFileFromExternal($externalFile, $destFile);

        $headers = [];
        if ($snapshots !== null) {
            $headers['x-ms-delete-snapshots'] = ($snapshots == 'only') ? 'only' : 'include';
        }

        $this->initClient('DELETE', $headers);
        $this->client->getRequest()->setUri($externalFile);
        $this->auth->signRequest($this->client->getRequest());
        $this->client->send();
    }

    /**
     * Rename file
     *
     * @param  string $oldFile
     * @param  string $newFile
     * @return void
     */
    public function renameFile(string $oldFile, string $newFile): void
    {
        $this->copyFile($oldFile, $newFile);
        $this->deleteFile($oldFile);
    }

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $fileContents
     * @return void
     */
    public function replaceFileContents(string $filename, string $fileContents): void
    {
        $this->putFileContents($filename, $fileContents);
    }

    /**
     * Delete file
     *
     * @param  string  $filename
     * @param  ?string $snapshots ['include', 'only', null]
     * @return void
     */
    public function deleteFile(string $filename, ?string $snapshots = 'include'): void
    {
        $uri = '/' . $this->baseDirectory . '/' . $filename;
        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $uri = $directory . $uri;
        }

        $headers = [];
        if ($snapshots !== null) {
            $headers['x-ms-delete-snapshots'] = ($snapshots == 'only') ? 'only' : 'include';
        }

        $this->initClient('DELETE', $headers);
        $this->client->getRequest()->setUri($uri);
        $this->auth->signRequest($this->client->getRequest());
        $this->client->send();
    }

    /**
     * Fetch file
     *
     * @param  string $filename
     * @param  bool   $raw
     * @return mixed
     */
    public function fetchFile(string $filename, bool $raw = true): mixed
    {
        $filename = (!str_starts_with($filename, '/')) ? '/' . $this->baseDirectory . '/' . $filename : $filename;

        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $filename = $directory . $filename;
        }

        $this->initClient('GET', [], false);
        $this->client->getRequest()->setUri($filename);
        $this->auth->signRequest($this->client->getRequest());
        $response = $this->client->send();

        if ($response->isSuccess()) {
            return ($raw) ? $response->getBody()->getContent(): $response;
        } else {
            return null;
        }
    }

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array
    {
        $filename = (!str_starts_with($filename, '/')) ? '/' . $this->baseDirectory . '/' . $filename : $filename;

        if ($this->baseDirectory !== $this->directory) {
            $directory = str_replace($this->baseDirectory, '', $this->directory);
            if (str_ends_with($directory, '/')) {
                $directory = substr($directory, 0, -1);
            }
            $filename = $directory . $filename;
        }

        $this->initClient('HEAD', [], false);
        $this->client->getRequest()->setUri($filename);
        $this->auth->signRequest($this->client->getRequest());
        $response = $this->client->send();

        return [
            'code'    => $response->getCode(),
            'message' => $response->getMessage(),
            'headers' => $response->getHeadersAsArray(),
            'isError' => $response->isError()
        ];
    }

    /**
     * File exists
     *
     * @param  string $filename
     * @return bool
     */
    public function fileExists(string $filename): bool
    {
        $info = $this->fetchFileInfo($filename);
        return (isset($info['code']) && ((int)$info['code'] == 200));
    }

    /**
     * Check if is a dir
     *
     * @param  string $directory
     * @return bool
     */
    public function isDir(string $directory): bool
    {
        if (str_starts_with($directory, '/')) {
            $directory = substr($directory, 1);
        }
        if (str_ends_with($directory, '/')) {
            $directory = substr($directory, 0, -1);
        }
        $info = $this->fetchFileInfo($directory);
        return (isset($info['headers']) && isset($info['headers']['x-ms-resource-type']) &&
            $info['headers']['x-ms-resource-type'] == 'directory');
    }

    /**
     * Check if is a file
     *
     * @param  string $filename
     * @return bool
     */
    public function isFile(string $filename): bool
    {
        $info = $this->fetchFileInfo($filename);
        return (isset($info['headers']) && isset($info['headers']['x-ms-resource-type']) &&
            $info['headers']['x-ms-resource-type'] == 'file');
    }

    /**
     * Get file size
     *
     * @param  string $filename
     * @return int|bool
     */
    public function getFileSize(string $filename): int|bool
    {
        $info = $this->fetchFileInfo($filename);
        return $info['headers']['Content-Length'] ?? false;
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @return string|bool
     */
    public function getFileType(string $filename): string|bool
    {
        if ($this->isFile($filename)) {
            return 'file';
        } else if ($this->isDir($filename)) {
            return 'dir';
        } else {
            return false;
        }
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @return int|string|bool
     */
    public function getFileMTime(string $filename): int|string|bool
    {
        $info = $this->fetchFileInfo($filename);
        if (isset($info['headers']) && !empty($info['headers']['Last-Modified'])) {
            return $info['headers']['Last-Modified'];
        } else if (isset($info['headers']) && !empty($info['headers']['x-ms-creation-time'])) {
            return $info['headers']['x-ms-creation-time'];
        } else {
            return false;
        }
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @return string|bool
     */
    public function md5File(string $filename): string|bool
    {
        $info = $this->fetchFileInfo($filename);
        if (isset($info['headers']) && !empty($info['headers']['Content-MD5'])) {
            return $info['headers']['Content-MD5'];
        } else {
            return false;
        }
    }

}
