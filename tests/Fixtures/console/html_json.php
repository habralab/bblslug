<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled response containing valid-but-different HTML in JSON string values. */
    class HttpClient
    {
        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $translated = json_encode([
                'body' => '<p>Hello</p>',
                'other' => '<strong>World</strong>',
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return [
                'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n" . $translated . "\n‹‹END››",
                ]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'debugRequest' => '',
                'debugResponse' => '',
                'headers' => [],
                'status' => 200,
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
