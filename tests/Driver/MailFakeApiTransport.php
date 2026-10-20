<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\Http\ApiResponse;
use EzPhp\Mail\Driver\Http\ApiTransportInterface;

/**
 * Recording ApiTransportInterface for the API driver tests: returns a canned
 * response and keeps every request it was given.
 *
 * @package Tests\Driver
 */
final class MailFakeApiTransport implements ApiTransportInterface
{
    /**
     * @var list<array{url: string, body: string|array<string, string|\CURLFile|\CURLStringFile>, headers: list<string>, basicAuth: string, timeout: int, connectTimeout: int}>
     */
    public array $requests = [];

    /**
     * @param ApiResponse $response Response returned for every request.
     */
    public function __construct(private readonly ApiResponse $response = new ApiResponse(200, '{}'))
    {
    }

    /**
     * @param non-empty-string                                       $url
     * @param string|array<string, string|\CURLFile|\CURLStringFile> $body
     * @param list<string>                                           $headers
     * @param string                                                 $basicAuth
     * @param int                                                    $timeout
     * @param int                                                    $connectTimeout
     *
     * @return ApiResponse
     */
    public function post(
        string $url,
        string|array $body,
        array $headers,
        string $basicAuth,
        int $timeout,
        int $connectTimeout,
    ): ApiResponse {
        $this->requests[] = compact('url', 'body', 'headers', 'basicAuth', 'timeout', 'connectTimeout');

        return $this->response;
    }
}
