<?php

declare(strict_types=1);

/**
 * Router for the `php -S` loopback server started by MailCurlApiTransportTest.
 *
 * `/echo` answers with a JSON description of the request it received;
 * `/status/<code>` answers with that status; `/slow` outlasts a 1 s timeout.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($requestUri) ? $requestUri : '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if ($path === '/slow') {
    sleep(3);
    echo 'too late';

    return;
}

if (preg_match('~^/status/(\d{3})$~', $path, $m) === 1) {
    http_response_code((int) $m[1]);
    echo 'status ' . $m[1];

    return;
}

header('Content-Type: application/json');

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    'x_test' => $_SERVER['HTTP_X_TEST'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'raw' => file_get_contents('php://input'),
    'post' => $_POST,
    'files' => array_map(static fn (mixed $file): mixed => is_array($file) ? ($file['name'] ?? null) : null, $_FILES),
], JSON_THROW_ON_ERROR);
