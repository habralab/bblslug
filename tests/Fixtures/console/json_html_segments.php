<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled provider response for JSON HTML segment transport CLI tests. */
    class HttpClient
    {
        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $count = max(1, (int) (getenv('BBLSLUG_TEST_SEGMENT_COUNT') ?: '1'));
            $translated = [];
            foreach (range(0, $count - 1) as $index) {
                $translated['s' . $index] = ['Bonjour', 'Monde', 'Salut', 'Terre'][$index] ?? 'Traduit';
            }
            if (getenv('BBLSLUG_TEST_MISSING_SEGMENT') === '1') {
                unset($translated['s0']);
                $translated['s9'] = 'Inattendu';
            }
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
