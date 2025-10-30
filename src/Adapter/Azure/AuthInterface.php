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
namespace Pop\Storage\Adapter\Azure;

use Pop\Http\Client\Request;

/**
 * Azure storage auth interface
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
interface AuthInterface
{

    /**
     * Set account name
     *
     * @param  string $accountName
     * @return AuthInterface
     */
    public function setAccountName(string $accountName): AuthInterface;

    /**
     * Get account name
     *
     * @return ?string
     */
    public function getAccountName(): ?string;

    /**
     * Has account name
     *
     * @return bool
     */
    public function hasAccountName(): bool;

    /**
     * Set account key
     *
     * @param  string $accountKey
     * @return AuthInterface
     */
    public function setAccountKey(string $accountKey): AuthInterface;

    /**
     * Get account key
     *
     * @return ?string
     */
    public function getAccountKey(): ?string;

    /**
     * Has account key
     *
     * @return bool
     */
    public function hasAccountKey(): bool;

    /**
     * Get account key
     *
     * @return string
     */
    public function getBaseUri(): string;

    /**
     * Returns authorization header to be included in the request.
     *
     * @param  array  $headers
     * @param  string $url
     * @param  array  $queryParams
     * @param  string $httpMethod
     * @return string
     */
    public function getAuthorizationHeader(array $headers, string $url, array $queryParams, string $httpMethod): string;

    /**
     * Adds authentication header to the request headers.
     *
     * @param  Request $request
     * @return Request
     */
    public function signRequest(Request $request): Request;

}
