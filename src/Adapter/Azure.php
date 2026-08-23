<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter;

use Pop\Storage\Adapter\Azure\Auth;
use Pop\Http\Body;
use Pop\Http\Client;
use Pop\Http\Client\Handler\HandlerInterface;
use Pop\Http\Client\Request;
use Pop\Storage\Exception\FileNotFoundException;
use Pop\Storage\Exception\PathTraversalException;
use Pop\Storage\Exception\UnableToCopyFileException;
use Pop\Storage\Exception\UnableToDeleteFileException;
use Pop\Storage\Exception\UnableToMoveFileException;
use Pop\Storage\Exception\UnableToReadFileException;
use Pop\Storage\Exception\UnableToWriteFileException;
use Pop\Utils\File;

/**
 * Storage adapter Azure class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Azure extends AbstractAdapter
{

    /**
     * Maximum number of pages walkBlobs() will follow via NextMarker before giving up.
     * A generous-but-finite backstop against a malformed or repeating continuation marker
     * causing an infinite loop. Overridable in subclasses (e.g. for fast tests).
     * @var int
     */
    protected const int MAX_LIST_PAGES = 10000;

    /**
     * Fallback Content-Type sent when the file/blob name has no extension File::getFileMimeType()
     * can resolve (it returns null in that case) - required because the mime type feeds directly
     * into a signed request header, and a null header value breaks request signing with a raw
     * TypeError rather than a clean exception. 'application/octet-stream' is the standard
     * "unknown binary content" MIME type.
     * @var string
     */
    protected const string DEFAULT_CONTENT_TYPE = 'application/octet-stream';

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
     * HTTP client handler, injected for testing (defaults to the real Curl handler when null)
     * @var ?HandlerInterface
     */
    protected ?HandlerInterface $handler = null;

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
            ->addHeader('User-Agent', 'pop-storage/3.0.0 (PHP ' . PHP_VERSION . ')/' . PHP_OS)
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

        if ($this->handler !== null) {
            $this->client->setHandler($this->handler);
        }

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
     * Get the current client's request, narrowed to the concrete request type
     * initClient() always constructs (the client base class declares the wider
     * AbstractRequest return type)
     *
     * @throws \Pop\Storage\Exception
     * @return Request
     */
    protected function getClientRequest(): Request
    {
        $request = $this->client?->getRequest();

        if (!($request instanceof Request)) {
            throw new \Pop\Storage\Exception('Error: The client has not been initialized with a request.');
        }

        return $request;
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
     * Set handler
     *
     * @param  HandlerInterface $handler
     * @return Azure
     */
    public function setHandler(HandlerInterface $handler): Azure
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * Get handler
     *
     * @return ?HandlerInterface
     */
    public function getHandler(): ?HandlerInterface
    {
        return $this->handler;
    }

    /**
     * Has handler
     *
     * @return bool
     */
    public function hasHandler(): bool
    {
        return ($this->handler !== null);
    }

    /**
     * Get the current directory as a blob-name prefix relative to the base directory
     * (the container), i.e. what chdir() has descended into. Returns an empty string when
     * the current directory is the base directory itself.
     *
     * @return string
     */
    private function currentPrefix(): string
    {
        if ($this->baseDirectory === $this->directory) {
            return '';
        }

        $directory = substr($this->directory, strlen($this->baseDirectory));

        return trim(str_replace('\\', '/', $directory), '/');
    }

    /**
     * Resolve a filename/directory argument to its full Azure blob URI, accounting for
     * the current directory (chdir()) relative to the base directory
     *
     * The path is scrubbed first, so a caller-supplied filename can neither traverse out of
     * the storage directory via '..' nor escape the container entirely by looking like an
     * absolute path. This is only for internal, current-directory-scoped paths - the
     * *External() methods set their genuinely external URI on the request directly.
     *
     * @param  string $path
     * @throws PathTraversalException
     * @return string
     */
    private function resolveUri(string $path): string
    {
        $path   = $this->scrub($path);
        $prefix = $this->currentPrefix();

        // The blob URI is always /{container}/{current directory}/{path} - the container comes
        // first, so anything chdir() descended into belongs between it and the path.
        return '/' . $this->baseDirectory . (($prefix !== '') ? '/' . $prefix : '') . '/' . $path;
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
     * @param  bool    $recursive
     * @return array
     */
    public function listDirs(?string $search = null, bool $recursive = false): array
    {
        $dirs = $this->walkBlobs($recursive, 'directory');

        if ($search !== null) {
            $dirs = $this->searchFilter($dirs, $search);
        }

        return $dirs;
    }

    /**
     * List files
     *
     * @param  ?string $search
     * @param  bool    $recursive
     * @return array
     */
    public function listFiles(?string $search = null, bool $recursive = false): array
    {
        $files = $this->walkBlobs($recursive, 'file');

        if ($search !== null) {
            $files = $this->searchFilter($files, $search);
        }

        return $files;
    }

    /**
     * Walk every page of the blob listing for the current directory, returning blob
     * names matching $resourceType ('file' or 'directory')
     *
     * @param  bool   $recursive
     * @param  string $resourceType
     * @return array
     */
    private function walkBlobs(bool $recursive, string $resourceType): array
    {
        $results = [];
        $uri     = '/' . $this->baseDirectory;
        $params  = ['restype' => 'container', 'comp' => 'list'];
        $prefix  = $this->currentPrefix();

        if (!$recursive) {
            $params['delimiter'] = '/';
        }
        if ($prefix !== '') {
            $prefix          .= '/';
            $params['prefix'] = $prefix;
        }

        $pageCount = 0;
        $this->initClient();

        do {
            if (++$pageCount > static::MAX_LIST_PAGES) {
                throw new UnableToReadFileException(
                    'Error: Exceeded maximum page count while listing blobs - the continuation marker may be malformed.'
                );
            }

            // Reuse the same client/request across pages instead of rebuilding the whole
            // object graph via initClient() every iteration - only the Date header (part of
            // the signed string), query and signature need to be refreshed per page.
            $this->getClientRequest()->addHeader('Date', gmdate('D, d M Y H:i:s T'));
            $this->getClientRequest()->setQuery($params);
            $this->getClientRequest()->setUri($uri);
            $this->auth->signRequest($this->getClientRequest());
            $response = $this->client->send();

            if (is_array($response) && !empty($response['Blobs']) && !empty($response['Blobs']['Blob'])) {
                $blobs = (!isset($response['Blobs']['Blob'][0])) ? [$response['Blobs']['Blob']] : $response['Blobs']['Blob'];
                foreach ($blobs as $blob) {
                    if (isset($blob['Properties']) && isset($blob['Properties']['ResourceType']) &&
                        ($blob['Properties']['ResourceType'] == $resourceType)) {
                        // Azure names blobs relative to the container root; results have to be
                        // relative to the current directory so they round-trip straight back into
                        // fetchFile()/deleteFile(), the same as the Local and S3 adapters.
                        $name = ($prefix !== '' && str_starts_with($blob['Name'], $prefix)) ?
                            substr($blob['Name'], strlen($prefix)) : $blob['Name'];

                        if (($name !== '') && ($recursive || !str_contains(rtrim($name, '/'), '/'))) {
                            $results[] = $name;
                        }
                    }
                }
            }

            $nextMarker = (is_array($response) && !empty($response['NextMarker'])) ? $response['NextMarker'] : null;
            if ($nextMarker !== null) {
                $params['marker'] = $nextMarker;
            }
        } while ($nextMarker !== null);

        return $results;
    }

    /**
     * Put file
     *
     * @param  string $fileFrom
     * @param  bool $copy
     * @throws FileNotFoundException|UnableToWriteFileException
     * @return void
     */
    public function putFile(string $fileFrom, bool $copy = true): void
    {
        if (!file_exists($fileFrom)) {
            throw new FileNotFoundException('Error: The file \'' . $fileFrom . '\' was not found.');
        }

        $uri = $this->resolveUri(basename($fileFrom));

        $fileContents = file_get_contents($fileFrom);

        $this->initClient('PUT', [
            'content-length'         => (string)strlen($fileContents),
            'x-ms-blob-type'         => 'BlockBlob',
            'x-ms-blob-content-type' => File::getFileMimeType($fileFrom) ?? self::DEFAULT_CONTENT_TYPE
        ], false);
        $this->getClientRequest()->setUri($uri);
        $this->getClientRequest()->setBody($fileContents);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $fileFrom . '\' (HTTP ' . $response->getCode() . ').');
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
        $uri = $this->resolveUri($filename);

        $this->initClient('PUT', [
            'content-length'         => (string)strlen($fileContents),
            'x-ms-blob-type'         => 'BlockBlob',
            'x-ms-blob-content-type' => File::getFileMimeType($filename) ?? self::DEFAULT_CONTENT_TYPE
        ], false);
        $this->getClientRequest()->setUri($uri);
        $this->getClientRequest()->setBody($fileContents);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\' (HTTP ' . $response->getCode() . ').');
        }
    }

    /**
     * Put file from a stream resource
     *
     * @param  string $filename
     * @param  mixed  $resource
     * @throws UnableToWriteFileException
     * @return void
     */
    public function putFileStream(string $filename, mixed $resource): void
    {
        if (!is_resource($resource)) {
            throw new UnableToWriteFileException('Error: The provided resource is not a valid stream.');
        }

        $uri  = $this->resolveUri($filename);
        $stat = fstat($resource);

        $body = new Body();
        $body->setContentFromStream($resource);

        $this->initClient('PUT', [
            'content-length'         => (string)$stat['size'],
            'x-ms-blob-type'         => 'BlockBlob',
            'x-ms-blob-content-type' => File::getFileMimeType($filename) ?? self::DEFAULT_CONTENT_TYPE
        ], false);
        $this->getClientRequest()->setUri($uri);
        $this->getClientRequest()->setBody($body);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $filename . '\' (HTTP ' . $response->getCode() . ').');
        }
    }

    /**
     * Upload file from server request $_FILES['file']
     *
     * @param  array $file
     * @throws UnableToWriteFileException|PathTraversalException
     * @return void
     */
    public function uploadFile(array $file): void
    {
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            throw new UnableToWriteFileException('Error: The uploaded file array was not valid.');
        }
        if (!file_exists($file['tmp_name'])) {
            throw new UnableToWriteFileException('Error: The uploaded file array was not valid.');
        }

        $uri = $this->resolveUri($file['name']);

        $fileContents = file_get_contents($file['tmp_name']);

        $this->initClient('PUT', [
            'content-length'         => (string)strlen($fileContents),
            'x-ms-blob-type'         => 'BlockBlob',
            'x-ms-blob-content-type' => File::getFileMimeType($file['name']) ?? self::DEFAULT_CONTENT_TYPE
        ], false);
        $this->getClientRequest()->setUri($uri);
        $this->getClientRequest()->setBody($fileContents);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToWriteFileException('Error: Unable to write file \'' . $file['name'] . '\' (HTTP ' . $response->getCode() . ').');
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

        if ($sourceFileInfo['code'] === 404) {
            throw new FileNotFoundException('Error: The file \'' . $sourceFile . '\' was not found.');
        }

        $sourceUri = $this->resolveUri($sourceFile);
        $destUri   = $this->resolveUri($destFile);

        $this->initClient('PUT', [
            'content-length'   => $sourceFileInfo['headers']['Content-Length'],
            'x-ms-copy-source' => $this->auth->getBaseUri() . $sourceUri,
        ], false);
        $this->getClientRequest()->setUri($destUri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $sourceFile . '\' (HTTP ' . $response->getCode() . ').');
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

        if ($sourceFileInfo['code'] === 404) {
            throw new FileNotFoundException('Error: The file \'' . $sourceFile . '\' was not found.');
        }

        $sourceUri = $this->resolveUri($sourceFile);

        $this->initClient('PUT', [
            'content-length'   => $sourceFileInfo['headers']['Content-Length'],
            'x-ms-copy-source' => $this->auth->getBaseUri() . $sourceUri,
        ], false);
        $this->getClientRequest()->setUri($externalFile);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $sourceFile . '\' (HTTP ' . $response->getCode() . ').');
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
        $this->getClientRequest()->setUri($externalFile);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if ($response->getCode() === 404) {
            throw new FileNotFoundException('Error: The file \'' . $externalFile . '\' was not found.');
        }
        if (!$response->isSuccess()) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $externalFile . '\' (HTTP ' . $response->getCode() . ').');
        }

        $destUri = $this->resolveUri($destFile);

        $this->initClient('PUT', [
            'content-length'   => $response->getHeaderValueAsString('Content-Length'),
            'x-ms-copy-source' => $this->auth->getBaseUri() . $externalFile,
        ], false);
        $this->getClientRequest()->setUri($destUri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if (!$response->isSuccess()) {
            throw new UnableToCopyFileException('Error: Unable to copy file \'' . $externalFile . '\' (HTTP ' . $response->getCode() . ').');
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
        try {
            $this->deleteFile($sourceFile);
        } catch (UnableToDeleteFileException $exception) {
            throw new UnableToMoveFileException(
                'Error: Copied \'' . $sourceFile . '\' to \'' . $externalFile . '\' but failed to remove the source.', 0, $exception
            );
        }
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

        $this->initClient('DELETE', $headers, false);
        $this->getClientRequest()->setUri($externalFile);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if ($response->getCode() === 404) {
            throw new FileNotFoundException('Error: The file \'' . $externalFile . '\' was not found.');
        }
        if (!$response->isSuccess()) {
            throw new UnableToMoveFileException(
                'Error: Copied \'' . $externalFile . '\' to \'' . $destFile . '\' but failed to remove the source (HTTP ' . $response->getCode() . ').'
            );
        }
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
        try {
            $this->deleteFile($oldFile);
        } catch (UnableToDeleteFileException $exception) {
            throw new UnableToMoveFileException(
                'Error: Copied \'' . $oldFile . '\' to \'' . $newFile . '\' but failed to remove the source.', 0, $exception
            );
        }
    }

    /**
     * Replace file
     *
     * @param  string $filename
     * @param  string $fileContents
     * @throws FileNotFoundException
     * @return void
     */
    public function replaceFileContents(string $filename, string $fileContents): void
    {
        if (!$this->fileExists($filename)) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
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
        $uri = $this->resolveUri($filename);

        $headers = [];
        if ($snapshots !== null) {
            $headers['x-ms-delete-snapshots'] = ($snapshots == 'only') ? 'only' : 'include';
        }

        $this->initClient('DELETE', $headers, false);
        $this->getClientRequest()->setUri($uri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if ($response->getCode() === 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (!$response->isSuccess()) {
            throw new UnableToDeleteFileException('Error: Unable to delete file \'' . $filename . '\' (HTTP ' . $response->getCode() . ').');
        }
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
        $uri = $this->resolveUri($filename);

        $this->initClient('GET', [], false);
        $this->getClientRequest()->setUri($uri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if ($response->getCode() === 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (!$response->isSuccess()) {
            throw new UnableToReadFileException('Error: Unable to read file \'' . $filename . '\' (HTTP ' . $response->getCode() . ').');
        }

        return ($raw) ? $response->getBody()->getContent() : $response;
    }

    /**
     * Fetch file as a stream resource
     *
     * @param  string $filename
     * @throws FileNotFoundException|UnableToReadFileException
     * @return mixed
     */
    public function fetchFileStream(string $filename): mixed
    {
        $uri = $this->resolveUri($filename);

        $this->initClient('GET', [], false);
        $this->getClientRequest()->setUri($uri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        if ($response->getCode() === 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (!$response->isSuccess()) {
            throw new UnableToReadFileException('Error: Unable to read file \'' . $filename . '\' (HTTP ' . $response->getCode() . ').');
        }

        $stream = $response->getBody()->getStream();
        if (is_resource($stream)) {
            rewind($stream);
            return $stream;
        }

        // Body wasn't backed by a real stream (e.g. Mock handler content) - fall back to
        // a fresh in-memory stream over the buffered content so the return contract
        // (always a resource) holds regardless of what produced the response body.
        $memory = fopen('php://memory', 'r+');
        fwrite($memory, $response->getBody()->getContent());
        rewind($memory);
        return $memory;
    }

    /**
     * Fetch file info
     *
     * @param  string $filename
     * @return array
     */
    public function fetchFileInfo(string $filename): array
    {
        $uri = $this->resolveUri($filename);

        $this->initClient('HEAD', [], false);
        $this->getClientRequest()->setUri($uri);
        $this->auth->signRequest($this->getClientRequest());
        $response = $this->client->send();

        return [
            'code'    => $response->getCode(),
            'message' => $response->getMessage(),
            'headers' => $response->getHeadersAsArray(),
            'isError' => $response->isError()
        ];
    }

    /**
     * Get a temporary (presigned) URL for the file, valid for $expiresInSeconds
     *
     * @param  string $filename
     * @param  int    $expiresInSeconds
     * @return string
     */
    public function getTemporaryUrl(string $filename, int $expiresInSeconds = 900): string
    {
        $uri   = $this->resolveUri($filename);
        $token = $this->auth->generateSasToken($uri, $expiresInSeconds, 'r');

        return $this->auth->getBaseUri() . $uri . '?' . $token;
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
     * @throws FileNotFoundException|UnableToReadFileException
     * @return int
     */
    public function getFileSize(string $filename): int
    {
        $info = $this->fetchFileInfo($filename);
        if ($info['code'] == 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (!isset($info['headers']['Content-Length'])) {
            throw new UnableToReadFileException(
                'Error: No content length returned for \'' . $filename . '\'.'
            );
        }
        return (int)$info['headers']['Content-Length'];
    }

    /**
     * Get file type
     *
     * @param  string $filename
     * @throws FileNotFoundException|UnableToReadFileException
     * @return string
     */
    public function getFileType(string $filename): string
    {
        $info = $this->fetchFileInfo($filename);
        if ($info['code'] == 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (isset($info['headers']['x-ms-resource-type']) && ($info['headers']['x-ms-resource-type'] == 'file')) {
            return 'file';
        } else if (isset($info['headers']['x-ms-resource-type']) && ($info['headers']['x-ms-resource-type'] == 'directory')) {
            return 'dir';
        } else {
            throw new UnableToReadFileException(
                'Error: No resource type returned for \'' . $filename . '\'.'
            );
        }
    }

    /**
     * Get file modified time
     *
     * @param  string $filename
     * @throws FileNotFoundException|UnableToReadFileException
     * @return int|string
     */
    public function getFileMTime(string $filename): int|string
    {
        $info = $this->fetchFileInfo($filename);
        if ($info['code'] == 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (isset($info['headers']) && !empty($info['headers']['Last-Modified'])) {
            return $info['headers']['Last-Modified'];
        } else if (isset($info['headers']) && !empty($info['headers']['x-ms-creation-time'])) {
            return $info['headers']['x-ms-creation-time'];
        } else {
            throw new UnableToReadFileException(
                'Error: No modified time returned for \'' . $filename . '\'.'
            );
        }
    }

    /**
     * Create MD5 checksum of the file
     *
     * @param  string $filename
     * @throws FileNotFoundException|UnableToReadFileException
     * @return string
     */
    public function md5File(string $filename): string
    {
        $info = $this->fetchFileInfo($filename);
        if ($info['code'] == 404) {
            throw new FileNotFoundException('Error: The file \'' . $filename . '\' was not found.');
        }
        if (isset($info['headers']) && !empty($info['headers']['Content-MD5'])) {
            return $info['headers']['Content-MD5'];
        } else {
            throw new UnableToReadFileException(
                'Error: No MD5 checksum returned for \'' . $filename . '\'.'
            );
        }
    }

}
