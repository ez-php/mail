# ez-php/mail

Transactional mail module for the ez-php framework. Delivers outgoing messages via a pluggable driver — SMTP (native PHP, no library required), Log (dev/CI), or Null (silent discard).

---

## Installation

```bash
composer require ez-php/mail
```

---

## Quick Start

Register the provider in `provider/modules.php`:

```php
use EzPhp\Mail\MailServiceProvider;

$app->register(MailServiceProvider::class);
```

Add config values to `config/mail.php`:

```php
return [
    'driver'       => env('MAIL_DRIVER', 'null'),
    'host'         => env('MAIL_HOST', '127.0.0.1'),
    'port'         => (int) env('MAIL_PORT', 587),
    'username'     => env('MAIL_USERNAME', ''),
    'password'     => env('MAIL_PASSWORD', ''),
    'encryption'   => env('MAIL_ENCRYPTION', 'tls'),
    'from_address' => env('MAIL_FROM_ADDRESS', ''),
    'from_name'    => env('MAIL_FROM_NAME', ''),
    'log_path'     => env('MAIL_LOG_PATH', ''),
];
```

Send a message:

```php
use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;

Mail::send(
    (new Mailable())
        ->to('alice@example.com', 'Alice')
        ->subject('Welcome!')
        ->text('Hello Alice, welcome aboard.')
        ->html('<p>Hello Alice, <strong>welcome aboard.</strong></p>')
);
```

---

## Drivers

| Driver | `MAIL_DRIVER` | Description |
|--------|--------------|-------------|
| SMTP   | `smtp`       | Delivers via a real SMTP server (RFC 5321, pure PHP) |
| Log    | `log`        | Writes a summary to a log file; safe for dev/CI |
| Null   | `null`       | Silently discards every message (default) |

### SMTP

```dotenv
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls        # tls | ssl | none
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="My App"
```

Supports TLS via STARTTLS (port 587), implicit SSL (port 465), and plain-text (port 25).
Authentication is AUTH LOGIN; omit `MAIL_USERNAME` to skip authentication.

### Log

```dotenv
MAIL_DRIVER=log
MAIL_LOG_PATH=/var/www/html/storage/logs/mail.log
```

When `MAIL_LOG_PATH` is empty, messages are written via `error_log()`.

---

## Mailable

`Mailable` is a fluent builder that can be used inline or extended per-message type:

```php
// Inline
$mail = (new Mailable())
    ->to('bob@example.com', 'Bob')
    ->from('noreply@example.com', 'My App')   // overrides driver default
    ->subject('Your Invoice')
    ->text('Please find your invoice attached.')
    ->html('<p>Please find your invoice <strong>attached</strong>.</p>')
    ->attach('/path/to/invoice.pdf', 'Invoice-2026-01.pdf');

// Extended
class InvoiceMail extends Mailable
{
    public function __construct(User $user, string $invoicePath)
    {
        $this->to($user->email, $user->name)
             ->subject('Your Invoice')
             ->text('Please find your invoice attached.')
             ->attach($invoicePath);
    }
}
```

### Methods

| Method | Description |
|--------|-------------|
| `to(string $address, string $name = '')` | Set recipient |
| `from(string $address, string $name = '')` | Override sender (uses driver default when not called) |
| `subject(string $subject)` | Set subject line |
| `text(string $body)` | Set plain-text body |
| `html(string $body)` | Set HTML body |
| `attach(string $path, string $name = '')` | Add file attachment |

Combining `text()` and `html()` produces a `multipart/alternative` message. Adding attachments wraps the content in `multipart/mixed`.

---

## Static Facade

`Mail::send(Mailable $mailable)` delegates to the driver bound by `MailServiceProvider`.

In tests, inject a spy driver directly:

```php
use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;

// Arrange
$spy = new class implements MailerInterface {
    public array $sent = [];
    public function send(Mailable $mailable): void { $this->sent[] = $mailable; }
};
Mail::setMailer($spy);

// Act
Mail::send((new Mailable())->to('a@b.com')->subject('Hi')->text('body'));

// Assert
assert(count($spy->sent) === 1);

// Teardown
Mail::resetMailer();
```

---

## MIME Support

`MimeBuilder` constructs RFC 2822 / MIME messages internally. It handles:

- `text/plain` (quoted-printable)
- `text/html` (quoted-printable)
- `multipart/alternative` (text + HTML)
- `multipart/mixed` (any of the above + attachments)
- RFC 2047 encoding for non-ASCII subject lines and display names
- Base64-encoded attachments with MIME type detection via `mime_content_type()`

---

## Queue Integration

Mail delivery is synchronous by default — `Mail::send()` blocks until the driver finishes. To dispatch mail asynchronously, wrap the call in a queue job:

```php
use EzPhp\Contracts\JobInterface;
use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;

final class SendMailJob implements JobInterface
{
    public function __construct(private readonly Mailable $mailable) {}

    public function handle(): void
    {
        Mail::send($this->mailable);
    }
}

// Dispatch from a controller or service
$queue->push(new SendMailJob(
    (new Mailable())
        ->to($user->email, $user->name)
        ->subject('Welcome!')
        ->text('Hello, welcome aboard.')
));
```

The `Mailable` is serialized with the job. `Mail::send()` inside `handle()` uses whatever driver is registered in the worker process — typically `SmtpDriver` in production and `NullDriver` or `LogDriver` in development.

> **Note:** Queue-backed delivery is an application-layer concern. The `ez-php/mail` package has no dependency on `ez-php/queue`.

---

## Local Development with Mailpit

[Mailpit](https://github.com/axllent/mailpit) is a local SMTP mail catcher with a web UI. All outgoing mail is captured and displayed without being delivered to real recipients.

### 1 — Add Mailpit to docker-compose.yml

```yaml
services:
  mailpit:
    image: axllent/mailpit
    container_name: my-app-mailpit
    ports:
      - "1025:1025"   # SMTP
      - "8025:8025"   # Web UI
    environment:
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1
```

### 2 — Configure the SMTP driver to point at Mailpit

```dotenv
MAIL_DRIVER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=none
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=dev@example.com
MAIL_FROM_NAME="My App (dev)"
```

### 3 — Open the Mailpit web UI

Navigate to `http://localhost:8025` in your browser. Every message sent via `Mail::send()` appears here instantly.

### 4 — SMTP integration tests

To run the Mailpit smoke tests in `ez-php/mail` itself:

```bash
MAILPIT_HOST=127.0.0.1 MAILPIT_SMTP_PORT=1025 MAILPIT_API_PORT=8025 \
  vendor/bin/phpunit --group mailpit
```

The three Mailpit tests are skipped automatically when `MAILPIT_HOST` is not set.
