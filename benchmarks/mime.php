<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Mail\MimeBuilder.
 *
 * Measures the overhead of encoding a multipart MIME message including
 * plain-text part, HTML part, and a base64-encoded file attachment.
 *
 * Exits with code 1 if the per-encode time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/mime.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Mail\Attachment;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MimeBuilder;

const ITERATIONS = 2000;
const THRESHOLD_MS = 5.0; // per-encode upper bound in milliseconds

// ── Prepare a temporary attachment ───────────────────────────────────────────

$attachPath = sys_get_temp_dir() . '/bench-attachment-' . getmypid() . '.txt';
file_put_contents($attachPath, str_repeat("Attachment content line.\n", 50));

// ── Prepare the Mailable ─────────────────────────────────────────────────────

$mailable = (new Mailable())
    ->to('recipient@example.com', 'Jane Doe')
    ->from('sender@example.com', 'John Doe')
    ->subject('Benchmark: Multipart MIME with attachment')
    ->text("Hello Jane,\n\nThis is the plain-text part of the email.\n\nBest regards,\nJohn")
    ->html('<h1>Hello Jane</h1><p>This is the <strong>HTML</strong> part of the email.</p>')
    ->attach($attachPath, 'report.txt');

$builder = new MimeBuilder();

// Warm-up
$builder->build($mailable);

// ── Benchmark ─────────────────────────────────────────────────────────────────

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $builder->build($mailable);
}

$end = hrtime(true);

@unlink($attachPath);

$totalMs = ($end - $start) / 1_000_000;
$perEncode = $totalMs / ITERATIONS;

echo sprintf(
    "MimeBuilder Benchmark\n" .
    "  Parts                : text + html + 1 attachment\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per encode           : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    ITERATIONS,
    $totalMs,
    $perEncode,
    THRESHOLD_MS,
);

if ($perEncode > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perEncode,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
