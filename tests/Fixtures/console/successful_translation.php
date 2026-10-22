<?php

declare(strict_types=1);

namespace Bblslug {
    /** Successful translation stub for CLI file-output checks without API calls. */
    class Bblslug
    {
        public static function translate(...$arguments): array
        {
            return [
                'original' => 'source text',
                'prepared' => 'source text',
                'result' => 'translated text',
                'httpStatus' => 200,
                'debugRequest' => '',
                'debugResponse' => '',
                'rawResponseBody' => '',
                'consumed' => [],
                'lengths' => ['original' => 11, 'prepared' => 11, 'translated' => 15],
                'filterStats' => [],
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    \Bblslug\Console\Cli::run();
}
