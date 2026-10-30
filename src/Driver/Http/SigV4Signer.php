<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver\Http;

/**
 * Class SigV4Signer
 *
 * AWS Signature Version 4 for a JSON POST without query parameters, signing only
 * `host` and `x-amz-date`. Just what SesDriver needs — kept in this module rather
 * than shared with ez-php/storage's S3 signer, which handles presigning, query
 * strings and streaming payloads.
 *
 * @package EzPhp\Mail\Driver\Http
 */
final readonly class SigV4Signer
{
    /**
     * @param string $accessKey AWS access key id.
     * @param string $secretKey AWS secret access key.
     * @param string $region    AWS region, e.g. 'eu-west-1'.
     * @param string $service   AWS service name, e.g. 'ses'.
     */
    public function __construct(
        private string $accessKey,
        private string $secretKey,
        private string $region,
        private string $service,
    ) {
    }

    /**
     * Build the Authorization header value.
     *
     * @param string $method  HTTP method.
     * @param string $host    Host header value.
     * @param string $path    Absolute request path.
     * @param string $amzDate X-Amz-Date header value (`YYYYMMDDTHHMMSSZ`).
     * @param string $body    Request body.
     *
     * @return string
     */
    public function authorization(string $method, string $host, string $path, string $amzDate, string $body): string
    {
        $date = substr($amzDate, 0, 8);
        $scope = "{$date}/{$this->region}/{$this->service}/aws4_request";
        $signedHeaders = 'host;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            '',
            "host:{$host}",
            "x-amz-date:{$amzDate}",
            '',
            $signedHeaders,
            hash('sha256', $body),
        ]);

        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $key = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', $this->service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);

        return sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $scope,
            $signedHeaders,
            hash_hmac('sha256', $stringToSign, $key),
        );
    }
}
