<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

try {
    \Bblslug\HttpClient::request(method: 'GET', url: $argv[1], maxResponseBytes: 1048576);
} catch (\RuntimeException $exception) {
    if (
        $exception->getCode() === CURLE_WRITE_ERROR
        && $exception->getMessage() === 'HTTP response body exceeds the 1048576-byte limit.'
    ) {
        echo "bounded\n";
        exit(0);
    }
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
fwrite(STDERR, "Expected response limit failure.\n");
exit(1);
