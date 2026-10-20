<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver\Http;

/**
 * Interface ApiTransportInterface
 *
 * The HTTP seam of the API mail drivers (MailgunDriver, SendGridDriver): one
 * POST request in, the raw outcome out. The drivers build the request and
 * interpret the response; the transport only moves bytes. CurlApiTransport is
 * the production implementation; tests pass a fake to cover error paths
 * (cURL failure, non-2xx status) without a live account.
 *
 * @package EzPhp\Mail\Driver\Http
 */
interface ApiTransportInterface
{
    /**
     * Send a POST request.
     *
     * @param non-empty-string                          $url            Absolute request URL.
     * @param string|array<string, string|\CURLFile|\CURLStringFile>    $body           Raw body (JSON) or multipart form fields.
     * @param list<string>                              $headers        Header lines, e.g. 'Accept: application/json'.
     * @param string                                    $basicAuth      'user:password' for HTTP Basic auth; '' = none.
     * @param int                                       $timeout        Total request timeout in seconds.
     * @param int                                       $connectTimeout Connection-phase timeout in seconds.
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
    ): ApiResponse;
}
