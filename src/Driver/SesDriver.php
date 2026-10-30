<?php

declare(strict_types=1);

namespace EzPhp\Mail\Driver;

use Closure;
use EzPhp\Mail\Driver\Http\ApiTransportInterface;
use EzPhp\Mail\Driver\Http\CurlApiTransport;
use EzPhp\Mail\Driver\Http\SigV4Signer;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Mail\MailException;
use EzPhp\Mail\MimeBuilder;

/**
 * Class SesDriver
 *
 * Delivers mail via the Amazon SES v2 API, without the AWS SDK. The message is
 * built by MimeBuilder (like SmtpDriver) and sent as `Content.Raw`, so attachments
 * need no separate handling.
 *
 * API endpoint: https://email.{region}.amazonaws.com/v2/email/outbound-emails
 *
 * Authentication: AWS Signature V4 with a static key pair; temporary credentials
 * (session tokens) are not supported.
 *
 * @package EzPhp\Mail\Driver
 */
final class SesDriver implements MailerInterface
{
    private const string PATH = '/v2/email/outbound-emails';

    private readonly ApiTransportInterface $transport;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param string      $key            AWS access key id.
     * @param string      $secret         AWS secret access key.
     * @param string      $region         AWS region, e.g. 'eu-west-1'.
     * @param MimeBuilder $mime           MIME message builder.
     * @param string      $fromAddress    Default sender address (overridden by Mailable::from()).
     * @param string      $fromName       Default sender display name (overridden by Mailable::from()).
     * @param int         $timeout        Total request timeout in seconds (`mail.timeout`).
     * @param int         $connectTimeout Connection-phase timeout in seconds.
     * @param ApiTransportInterface|null $transport HTTP transport; null = CurlApiTransport.
     * @param (Closure(): int)|null      $clock     Current Unix time; null = time().
     *
     * @throws MailException When the region cannot be part of a host name.
     */
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $region,
        private readonly MimeBuilder $mime,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        ?ApiTransportInterface $transport = null,
        ?Closure $clock = null,
    ) {
        // The region becomes the host the AWS credentials are signed for.
        if (preg_match('/\A[a-z0-9-]+\z/', $region) !== 1) {
            throw new MailException("Invalid SES region: {$region}");
        }

        $this->transport = $transport ?? new CurlApiTransport();
        $this->clock = $clock ?? time(...);
    }

    /**
     * Deliver the given mailable via the SES v2 API.
     *
     * @param Mailable $mailable
     *
     * @throws MailException On transport errors, unreadable attachments, or non-2xx responses.
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $body = json_encode([
            'Destination' => ['ToAddresses' => [$mailable->getToAddress()]],
            'Content' => ['Raw' => ['Data' => base64_encode($this->mime->build($mailable, $this->fromAddress, $this->fromName))]],
        ], JSON_THROW_ON_ERROR);

        $host = "email.{$this->region}.amazonaws.com";
        $amzDate = gmdate('Ymd\THis\Z', ($this->clock)());
        $signer = new SigV4Signer($this->key, $this->secret, $this->region, 'ses');

        $this->transport
            ->post(
                "https://{$host}" . self::PATH,
                $body,
                [
                    'Host: ' . $host,
                    'X-Amz-Date: ' . $amzDate,
                    'Authorization: ' . $signer->authorization('POST', $host, self::PATH, $amzDate, $body),
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                '',
                $this->timeout,
                $this->connectTimeout,
            )
            ->assertDelivered('SES');
    }
}
