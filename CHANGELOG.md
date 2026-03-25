# Changelog

All notable changes to `ez-php/mail` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Mailable` — base class for composable mail messages; define `to`, `subject`, `text`, `html`, and attachments in a single object
- `MailerInterface` — driver contract with a single `send(Mailable): void` method
- `SmtpDriver` — sends mail via SMTP using PHP's native socket layer; supports TLS/STARTTLS and authentication
- `MailgunDriver` — sends mail via the Mailgun HTTP API; zero extra dependencies
- `LogDriver` — writes the serialized mail message to the application log instead of sending; useful in development
- `NullDriver` — silently discards all outgoing mail; useful in testing
- `MimeBuilder` — constructs RFC-compliant MIME messages with plain-text parts, HTML parts, and mixed-content attachments
- `Attachment` — value object representing a file attachment with name, MIME type, and raw content
- `MailServiceProvider` — resolves the configured driver from environment and binds it as `MailerInterface`
- `MailException` for connection, authentication, and send failures
