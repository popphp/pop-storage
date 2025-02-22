<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Storage\Adapter\Azure;

use Pop\Http\Client\Request;

/**
 * Azure storage abstract auth class
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
abstract class AbstractAuth implements AuthInterface
{

    /**
     * Account name
     * @var ?string
     */
    protected ?string $accountName = null;

    /**
     * Account key
     * @var ?string
     */
    protected ?string $accountKey = null;

    /**
     * Set account name
     *
     * @param  string $accountName
     * @return AbstractAuth
     */
    public function setAccountName(string $accountName): AbstractAuth
    {
        $this->accountName = $accountName;
        return $this;
    }

    /**
     * Get account name
     *
     * @return ?string
     */
    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    /**
     * Has account name
     *
     * @return bool
     */
    public function hasAccountName(): bool
    {
        return ($this->accountName !== null);
    }

    /**
     * Set account key
     *
     * @param  string $accountKey
     * @return AbstractAuth
     */
    public function setAccountKey(string $accountKey): AbstractAuth
    {
        $this->accountKey = $accountKey;
        return $this;
    }

    /**
     * Get account key
     *
     * @return ?string
     */
    public function getAccountKey(): ?string
    {
        return $this->accountKey;
    }

    /**
     * Has account key
     *
     * @return bool
     */
    public function hasAccountKey(): bool
    {
        return ($this->accountKey !== null);
    }

    /**
     * Get account key
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return 'https://' . $this->accountName . '.blob.core.windows.net';
    }

    /**
     * Returns authorization header to be included in the request.
     *
     * @param  array  $headers
     * @param  string $url
     * @param  array  $queryParams
     * @param  string $httpMethod
     * @return string
     */
    abstract public function getAuthorizationHeader(array $headers, string $url, array $queryParams, string $httpMethod): string;

    /**
     * Adds authentication header to the request headers.
     *
     * @param  Request $request
     * @return Request
     */
    abstract public function signRequest(Request $request): Request;

}
