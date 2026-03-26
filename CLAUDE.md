# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/mail

Transactional mail module for ez-php applications — pluggable drivers (SMTP, Log, Null), a fluent `Mailable` builder, RFC 2822 / MIME message construction, and a `Mail` static facade.

---

## Source Structure

```
src/
├── MailerInterface.php         — contract: send(Mailable): void
├── MailException.php           — base exception for all mail errors
├── Mailable.php                — fluent builder: to(), from(), subject(), text(), html(), attach()
├── Attachment.php              — immutable value object: file path + display name
├── MimeBuilder.php             — RFC 2822 / MIME message encoder (text, html, multipart, attachments)
├── Mail.php                    — static facade; delegates to injected MailerInterface singleton
├── MailServiceProvider.php     — binds MailerInterface (config-driven), wires Mail facade in boot()
└── Driver/
    ├── SmtpDriver.php          — native SMTP via stream_socket_client(); no external library
    ├── MailgunDriver.php       — Mailgun v3 REST API via cURL; no third-party SDK; supports US + EU regions
    ├── LogDriver.php           — writes human-readable summaries to a log file
    └── NullDriver.php          — silently discards all messages

tests/
├── TestCase.php                — base PHPUnit test case
├── AttachmentTest.php          — covers Attachment: getPath, getName fallback to basename
├── MailableTest.php            — covers Mailable: all setters, getters, attach accumulation, chaining
├── MimeBuilderTest.php         — covers MimeBuilder: text, HTML, multipart/alternative, multipart/mixed, encoding
├── MailTest.php                — covers Mail facade: delegation, uninitialized throw, reset, replacement
└── Driver/
    ├── NullDriverTest.php      — covers NullDriver: no exception, no output
    └── LogDriverTest.php       — covers LogDriver: file write, append, directory creation, field format
```

---

## Key Classes and Responsibilities

### MailerInterface (`src/MailerInterface.php`)

Single-method contract all drivers implement:

```php
public function send(Mailable $mailable): void;
```

Throw `MailException` on delivery failure.

---

### Mailable (`src/Mailable.php`)

Fluent builder for outgoing messages. All setter methods return `static` for inheritance support.

| Method | Description |
|--------|-------------|
| `to(string $address, string $name = '')` | Set recipient |
| `from(string $address, string $name = '')` | Override sender (uses driver default when not called) |
| `subject(string $subject)` | Set subject line |
| `text(string $body)` | Set plain-text body |
| `html(string $body)` | Set HTML body |
| `attach(string $path, string $name = '')` | Add a file attachment |

Combining `text()` and `html()` signals `MimeBuilder` to produce a `multipart/alternative` message. Adding attachments signals a `multipart/mixed` wrapper.

---

### MimeBuilder (`src/MimeBuilder.php`)

Encodes a `Mailable` into a full RFC 2822 / MIME message string (headers + blank line + body). Used internally by `SmtpDriver` and not required in application code.

MIME construction logic:

| Content combination | Resulting Content-Type |
|---------------------|------------------------|
| Text only           | `text/plain; charset=UTF-8` (quoted-printable) |
| HTML only           | `text/html; charset=UTF-8` (quoted-printable) |
| Text + HTML         | `multipart/alternative` |
| Any above + files   | `multipart/mixed` wrapping the inner type |

Non-ASCII subjects and display names are encoded with RFC 2047 (`=?UTF-8?B?...?=`).
Attachment bodies are base64-encoded with 76-character line wrapping.
MIME type detection uses PHP's `mime_content_type()` with `application/octet-stream` fallback.

---

### SmtpDriver (`src/Driver/SmtpDriver.php`)

Implements the SMTP protocol (RFC 5321) directly using PHP's `stream_socket_client()`.

| Encryption | Scheme | Typical Port |
|------------|--------|-------------|
| `ssl`      | `ssl://` from the start | 465 |
| `tls`      | `tcp://` + STARTTLS upgrade | 587 |
| `none`     | `tcp://` plain-text | 25 |

AUTH LOGIN is used when `username` is non-empty. RFC 5321 dot-stuffing is applied to the message body before sending.

This driver is not covered by automated unit tests (a live SMTP server would be required). Integration-test it against a local mail catcher such as Mailpit or MailHog.

---

### MailgunDriver (`src/Driver/MailgunDriver.php`)

Delivers mail via the Mailgun HTTP API (v3) using PHP's built-in cURL extension. No third-party library required.

| Region | API endpoint |
|--------|-------------|
| `us` (default) | `https://api.mailgun.net/v3/{domain}/messages` |
| `eu` | `https://api.eu.mailgun.net/v3/{domain}/messages` |

Authentication uses HTTP Basic auth with `api` as the username and the private API key as the password. Attachments are sent as `multipart/form-data` file fields (`attachment[0]`, `attachment[1]`, …).

Constructor parameters: `$domain`, `$apiKey`, `$fromAddress`, `$fromName`, `$region = 'us'`.

This driver is **not covered by automated unit tests** — a live Mailgun account (or Mailgun Sandbox domain) is required. Integration-test it against a Sandbox domain or use the `LogDriver` during development.

---

### LogDriver (`src/Driver/LogDriver.php`)

Writes a one-line human-readable summary per message to a file path. The log directory is created on demand. When `logPath` is an empty string, output goes via `error_log()`. Designed for local development and CI.

---

### NullDriver (`src/Driver/NullDriver.php`)

All calls to `send()` are no-ops. Default driver when `mail.driver` is unset or unknown.

---

### Mail (`src/Mail.php`)

Static facade. Mirrors `Log` from `ez-php/logging`: `setMailer()` / `resetMailer()` / `send()`. Throws `RuntimeException` when called before `setMailer()` — fail-fast prevents silent discards when the provider is missing.

---

### MailServiceProvider (`src/MailServiceProvider.php`)

**`register()`:**
- Binds `MimeBuilder` (new instance each resolution)
- Binds `MailerInterface` lazily; reads `mail.driver` from `Config`

**`boot()`:**
- Calls `Mail::setMailer($app->make(MailerInterface::class))` to wire the facade

---

## Design Decisions and Constraints

- **No third-party library** — SMTP is implemented with `stream_socket_client()` and raw protocol strings. This keeps the dependency tree minimal and the code transparent.
- **`SmtpDriver` uses `stream_socket_client()` instead of `fsockopen()`** — `stream_socket_client()` supports SSL wrapping natively (the `ssl://` scheme) and is PHP-stream-compatible (`fgets`, `fwrite`, `fclose`), which keeps all I/O uniform.
- **`MimeBuilder` is a separate class** — Isolating MIME construction from the transport allows `LogDriver` to format output without building MIME, and allows `MimeBuilder` to be tested without a network connection.
- **`Mailable` is non-abstract and mutable** — Mutable fluent builder is the natural fit for mail composition and matches the usage pattern across the ecosystem. Immutability (clone-based withers) would complicate subclassing without meaningful benefit for this domain.
- **`LogDriver` does not use `MimeBuilder`** — Dev/CI logging wants human-readable field summaries, not full MIME output. Keeping them decoupled avoids encoding overhead in development.
- **`NullDriver` as the default** — Fail-open is correct for mail: missing config should cause messages to be silently dropped rather than throwing at boot time. Developers opt into real delivery explicitly.
- **`Mail::send()` throws when uninitialised** — Fail-fast at runtime is preferable to silent discards. A missing `MailServiceProvider` registration becomes immediately visible in development.
- **No `Mail::to()` factory method** — Keeping `Mailable` construction out of the facade makes the entry point unambiguous: `new Mailable()` or a subclass. The facade's only responsibility is delegation.
- **`is_readable()` before `file_get_contents()`** — Avoids the PHP `E_WARNING` emitted when `file_get_contents()` fails on a missing or unreadable file. The explicit `is_readable()` check throws a typed `MailException` without triggering engine-level warnings.

---

## Testing Approach

- **No external infrastructure** — All tests run in-process. `LogDriverTest` writes to a temp file (created inline, deleted in `tearDown`).
- **`SmtpDriver` not unit-tested** — Requires a live SMTP server. Use a local mail catcher (Mailpit, MailHog) for integration testing.
- **`SpyMailer` named class** — `MailTest` uses a file-scope named class `SpyMailer implements MailerInterface` with a `getSent()` getter. Anonymous classes with reference-backed private properties confuse PHPStan's `property.onlyWritten` check.
- **`Mail::resetMailer()` in setUp/tearDown** — Required in any test touching the `Mail` facade to prevent state leaking between test classes.
- **`addToAssertionCount(1)` instead of `assertTrue(true)`** — PHPStan flags `assertTrue(true)` as always-true. `addToAssertionCount(1)` satisfies the "at least one assertion" requirement without a PHPStan error.
- **`#[UsesClass]` required** — `beStrictAboutCoverageMetadata=true` is set in `phpunit.xml`. Declare all indirectly used classes. Do not add `#[UsesClass(MailerInterface::class)]` — interfaces are not valid coverage targets.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| Template rendering (Blade, Twig, PHP views) | `ez-php/view` module |
| Queue-backed async delivery | Application layer: push a job that calls `Mail::send()` |
| Bounce / delivery receipt handling | Application layer or a dedicated webhook handler |
| Email validation rules | `ez-php/validation` (`email` rule) |
| Bulk / newsletter sending | Application layer or a dedicated SDK |
| HTML email CSS inlining | Application layer (use a dedicated library) |
| DKIM / SPF signing | SMTP server configuration, not the client |
