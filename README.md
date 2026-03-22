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
