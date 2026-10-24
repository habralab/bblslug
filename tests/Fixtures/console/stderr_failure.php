<?php

declare(strict_types=1);

namespace Bblslug {
    /** Deterministic CLI result/failure stub that never performs an API request. */
    class Bblslug
    {
        /** @return array<string,mixed> */
        public static function translate(...$arguments): array
        {
            if (getenv('BBLSLUG_TEST_FAILURE') === '1') {
                throw new \RuntimeException('fixture provider failure');
            }

            return [
                'original' => '{"source":"Hello"}',
                'prepared' => '{"source":"Hello"}',
                'result' => '{"translated":"Bonjour"}',
                'httpStatus' => 200,
                'debugRequest' => "FIXTURE DEBUG REQUEST\n",
                'debugResponse' => "FIXTURE DEBUG RESPONSE\n",
                'rawResponseBody' => '',
                'consumed' => ['tokens' => [
                    'total' => 20, 'breakdown' => ['prompt' => 8, 'completion' => 12],
                ]],
                'lengths' => ['original' => 18, 'prepared' => 18, 'translated' => 24],
                'filterStats' => [],
            ];
        }
    }
}

namespace {
    if (getenv('BBLSLUG_CLOSE_STDERR') === '1' && defined('STDERR') && is_resource(STDERR)) {
        fclose(STDERR);
    }

    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
