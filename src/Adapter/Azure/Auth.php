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
 * Azure storage auth class
 *
 * This class is ported over from the discontinued Azure Storage PHP library at
 * https://github.com/Azure/azure-storage-php (EOL: 3/17/2025)
 *
 * @category   Pop
 * @package    Pop\Storage
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
class Auth extends AbstractAuth
{

    /**
     * The included headers
     * @var array
     */
    protected array $includedHeaders = [
        'content-encoding', 'content-language', 'content-length', 'content-md5', 'content-type', 'date',
        'if-modified-since', 'if-match', 'if-none-match', 'if-unmodified-since', 'range',
    ];

    /**
     * Constructor.
     *
     * @param string $accountName
     * @param string $accountKey
     */
    public function __construct(string $accountName, string $accountKey)
    {
        $this->setAccountName($accountName);
        $this->setAccountKey($accountKey);
    }

    /**
     * Adds authentication header to the request headers.
     *
     * @param  Request $request
     * @return Request
     */
    public function signRequest(Request $request): Request
    {
        $queryParams = ($request->hasQuery()) ? $request->getQuery()->toArray() : [];

        $signedKey = $this->getAuthorizationHeader(
            self::formatHeaders($request->getHeadersAsArray()), $request->getUriAsString(),
            $queryParams, $request->getMethod()
        );

        return $request->addHeader('authorization', $signedKey);
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
    public function getAuthorizationHeader(array $headers, string $url, array $queryParams, string $httpMethod): string
    {
        $signature = $this->computeSignature($headers, $url, $queryParams, $httpMethod);

        return 'SharedKey ' . $this->accountName . ':' . base64_encode(
            hash_hmac('sha256', $signature, base64_decode($this->accountKey), true)
        );
    }

    /**
     * Returns the specified value of the $key passed from $array and in case that
     * this $key doesn't exist, the default value is returned. The key matching is
     * done in a case-insensitive manner.
     *
     * @param  string $key
     * @param  array  $haystack
     * @param  mixed  $default
     * @return mixed
     */
    public static function tryGetValueInsensitive(string $key, array $haystack, mixed $default = null): mixed
    {
        $array = array_change_key_case($haystack);
        return self::tryGetValue($array, strtolower($key), $default);
    }

    /**
     * Returns the specified value of the $key passed from $array and in case that
     * this $key doesn't exist, the default value is returned.
     *
     * @param  array $array
     * @param  mixed $key
     * @param  mixed $default
     * @return mixed
     */
    public static function tryGetValue(array $array, mixed $key, mixed $default = null): mixed
    {
        return (!empty($array) && array_key_exists($key, $array)) ? $array[$key] : $default;
    }

    /**
     * Checks if the passed $string starts with $prefix
     *
     * @param  string $string
     * @param  string $prefix
     * @param  bool   $ignoreCase
     * @return bool
     */
    public static function startsWith(string $string, string $prefix, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $string = strtolower($string);
            $prefix = strtolower($prefix);
        }
        return (str_starts_with($string, $prefix));
    }

    /**
     * Convert a http headers array into a uniformed format for further process
     *
     * @param  array $headers
     * @return array
     */
    public static function formatHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result[strtolower($key)] = (is_array($value) && count($value) == 1) ? $value[0] : $value;
        }

        return $result;
    }


    /**
     * Computes the authorization signature for blob and queue shared key.
     *
     * @param  array  $headers
     * @param  string $url
     * @param  array  $queryParams
     * @param  string $httpMethod
     * @return string
     */
    protected function computeSignature(array $headers, string $url, array $queryParams, string $httpMethod): string
    {
        $canonicalizedHeaders  = $this->computeCanonicalizedHeaders($headers);
        $canonicalizedResource = $this->computeCanonicalizedResource($url, $queryParams);

        $stringToSign   = [];
        $stringToSign[] = strtoupper($httpMethod);

        foreach ($this->includedHeaders as $header) {
            $stringToSign[] = self::tryGetValueInsensitive($header, $headers);
        }

        if (count($canonicalizedHeaders) > 0) {
            $stringToSign[] = implode("\n", $canonicalizedHeaders);
        }

        $stringToSign[] = $canonicalizedResource;
        $string = implode("\n", $stringToSign);

        return $string;
    }

    /**
     * Computes canonicalized headers for headers array.
     *
     * @param  array $headers
     * @return array
     */
    protected function computeCanonicalizedHeaders(array $headers): array
    {
        $canonicalizedHeaders = [];
        $normalizedHeaders    = [];
        $validPrefix          = 'x-ms-';

        foreach ($headers as $header => $value) {
            // Convert header to lower case.
            $header = strtolower($header);

            // Retrieve all headers for the resource that begin with x-ms-,
            // including the x-ms-date header.
            if (self::startsWith($header, $validPrefix)) {
                // Unfold the string by replacing any breaking white space
                // (meaning what splits the headers, which is \r\n) with a single
                // space.
                $value = str_replace("\r\n", ' ', $value);

                // Trim any white space around the colon in the header.
                $value  = ltrim($value);
                $header = rtrim($header);

                $normalizedHeaders[$header] = $value;
            }
        }

        // Sort the headers lexicographically by header name, in ascending order.
        // Note that each header may appear only once in the string.
        ksort($normalizedHeaders);

        foreach ($normalizedHeaders as $key => $value) {
            $canonicalizedHeaders[] = $key . ':' . $value;
        }

        return $canonicalizedHeaders;
    }

    /**
     * Computes canonicalized resources from URL using Table formar
     *
     * @param  string $url
     * @param  array  $queryParams
     * @return string
     */
    protected function computeCanonicalizedResourceForTable(string $url, array $queryParams): string
    {
        $queryParams = array_change_key_case($queryParams);

        // 1. Beginning with an empty string (""), append a forward slash (/),
        //    followed by the name of the account that owns the accessed resource.
        $canonicalizedResource = '/' . $this->accountName;

        // 2. Append the resource's encoded URI path, without any query parameters.
        $canonicalizedResource .= parse_url($url, PHP_URL_PATH);

        // 3. The query string should include the question mark and the comp
        //    parameter (for example, ?comp=metadata). No other parameters should
        //    be included on the query string.
        if (array_key_exists('comp', $queryParams)) {
            $canonicalizedResource .= '?comp=';
            $canonicalizedResource .= $queryParams['comp'];
        }

        return $canonicalizedResource;
    }

    /**
     * Computes canonicalized resources from URL.
     *
     * @param  string $url
     * @param  array  $queryParams
     * @return string
     */
    protected function computeCanonicalizedResource(string $url, array $queryParams): string
    {
        $queryParams = array_change_key_case($queryParams);

        // 1. Beginning with an empty string (""), append a forward slash (/),
        //    followed by the name of the account that owns the accessed resource.
        $canonicalizedResource = '/' . $this->accountName;

        // 2. Append the resource's encoded URI path, without any query parameters.
        $canonicalizedResource .= parse_url($url, PHP_URL_PATH);

        // 3. Retrieve all query parameters on the resource URI, including the comp
        //    parameter if it exists.
        // 4. Sort the query parameters lexicographically by parameter name, in
        //    ascending order.
        if (count($queryParams) > 0) {
            ksort($queryParams);
        }

        // 5. Convert all parameter names to lowercase.
        // 6. URL-decode each query parameter name and value.
        // 7. Append each query parameter name and value to the string in the
        //    following format:
        //      parameter-name:parameter-value
        // 9. Group query parameters
        // 10. Append a new line character (\n) after each name-value pair.
        foreach ($queryParams as $key => $value) {
            // $value must already be ordered lexicographically
            // See: ServiceRestProxy::groupQueryValues
            $canonicalizedResource .= "\n" . $key . ':' . $value;
        }

        return $canonicalizedResource;
    }

}
