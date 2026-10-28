<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static function request(...$arguments): array
        {
            file_put_contents((string) getenv('BBLSLUG_TEST_HTTP_LOG'), "request\n", FILE_APPEND);
            throw new \RuntimeException('Unexpected HTTP request');
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
