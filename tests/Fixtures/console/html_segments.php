<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled provider response for the HTML segment transport CLI tests. */
    class HttpClient
    {
        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $request = (string) ($arguments['body'] ?? '');
            $translated = str_contains($request, 's1')
                ? ['s0' => 'Bonjour', 's1' => 'Monde']
                : ['s0' => 'Bonjour', 's9' => 'Monde'];
            $content = "‹‹TRANSLATION››\n" . json_encode(
                $translated,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n‹‹END››";
            return [
                'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => $content,
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
