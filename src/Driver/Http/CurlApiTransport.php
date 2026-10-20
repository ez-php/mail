<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver\Http;

use EzPhp\Mail\MailException;

/**
 * Class CurlApiTransport
 *
 * ApiTransportInterface on PHP's cURL extension — the default transport of
 * MailgunDriver and SendGridDriver. No third-party HTTP library.
 *
 * @package EzPhp\Mail\Driver\Http
 */
final class CurlApiTransport implements ApiTransportInterface
{
    /**
     * @param non-empty-string                       $url
     * @param string|array<string, string|\CURLFile|\CURLStringFile> $body
     * @param list<string>                           $headers
     * @param string                                 $basicAuth
     * @param int                                    $timeout
     * @param int                                    $connectTimeout
     *
     * @throws MailException When the cURL handle cannot be created.
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
        $ch = curl_init();

        if ($ch === false) {
            throw new MailException('Failed to initialise cURL handle.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ];

        if ($basicAuth !== '') {
            $options[CURLOPT_USERPWD] = $basicAuth;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        return new ApiResponse(
            (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            is_string($response) ? $response : null,
            curl_error($ch),
        );
    }
}
