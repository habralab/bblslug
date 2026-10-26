<?php

/**
 * Tiny router for PHP's built-in web server used in HttpClient tests.
 * It returns deterministic responses so tests can assert behavior without
 * hitting the public internet.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/**
 * Send a response with status, headers and body.
 *
 * @param int                 $code
 * @param array<string,mixed> $headers Each value can be string or string[]
 * @param string              $body
 */
$send = static function (int $code, array $headers = [], string $body = ''): void {
    http_response_code($code);
    foreach ($headers as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $vv) {
                header($k . ': ' . $vv);
            }
        } else {
            header($k . ': ' . $v);
        }
    }
    echo $body;
};

// Delayed endpoint used to verify that the client enforces request timeouts.
if ($uri === '/slow') {
    usleep(1_500_000);
    $send(200, ['Content-Type' => 'text/plain'], 'slow response');
    return;
}

// Echo endpoint: returns method + raw body as JSON and emits a multi-valued header.
if ($uri === '/echo') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body   = file_get_contents('php://input') ?: '';
    $send(
        200,
        [
            'X-Foo'        => ['bar', 'baz'], // multi-value header to test header aggregation
            'Content-Type' => 'application/json',
        ],
        json_encode(['method' => $method, 'body' => $body], JSON_UNESCAPED_SLASHES)
    );
    return;
}

// Custom status endpoint to verify status propagation and header parsing.
if ($uri === '/status/201') {
    $send(201, ['Content-Type' => 'text/plain'], 'ok');
    return;
}

// Masking endpoint: emits a secret token in both headers and body,
// so tests can verify masking in debug logs.
if ($uri === '/mask') {
    $secret = $_GET['secret'] ?? 'SECRET';
    $send(200, ['X-Token' => $secret, 'Content-Type' => 'text/plain'], 'token=' . $secret);
    return;
}

// Masking endpoint that emits every common representation of a secret and a
// non-success status, allowing HttpClient tests to distinguish log masking
// from the raw response data returned to callers.
if ($uri === '/mask-encoded') {
    $secret = (string) ($_GET['secret'] ?? 'SECRET');
    $rawEncoded = rawurlencode($secret);
    $formEncoded = urlencode($secret);
    $lowercaseEncoded = preg_replace_callback(
        '/%[0-9A-F]{2}/',
        static fn (array $match): string => strtolower($match[0]),
        $rawEncoded
    ) ?? $rawEncoded;
    $jsonEscaped = json_encode($secret, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $nestedJson = json_encode(
        ['nested' => ['secret' => $secret, 'json' => $jsonEscaped]],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $send(
        429,
        [
            'X-Mask-Raw'       => $secret,
            'X-Mask-Raw-Url'   => $rawEncoded,
            'X-Mask-Form'      => $formEncoded,
            'X-Mask-Lowercase' => $lowercaseEncoded,
            'X-Mask-Json'      => $jsonEscaped,
            'Content-Type'     => 'text/plain; charset=utf-8',
        ],
        implode("\n", [$secret, $rawEncoded, $formEncoded, $lowercaseEncoded, $jsonEscaped, $nestedJson])
    );
    return;
}

// Default 404 for unknown paths.
$send(404, ['Content-Type' => 'text/plain'], 'not found');
