<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled DeepL response for placeholder integrity tests. */
    class HttpClient
    {
        /** @var array{mutation:string,translated:?string} */
        public static array $scenario = [
            'mutation' => 'identity',
            'translated' => null,
        ];

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $body = (string) ($arguments['body'] ?? '');
            parse_str($body, $payload);
            $prepared = (string) ($payload['text'] ?? '');
            $prepared = strtr($prepared, [
                '<jlc/>' => '{',
                '<jrc/>' => '}',
                '<jlb/>' => '[',
                '<jrb/>' => ']',
                '<jcol/>' => ':',
                '<jcomma/>' => ',',
                '<jqt/>' => '"',
            ]);

            $translated = self::$scenario['translated'] ?? $prepared;
            if ($translated === null) {
                $translated = $prepared;
            }
            $translated = self::mutate($translated, self::$scenario['mutation']);

            return [
                'body' => (string) json_encode(
                    ['translations' => [['text' => $translated]]],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'debugRequest' => '',
                'debugResponse' => '',
                'headers' => [],
                'status' => 200,
            ];
        }

        private static function mutate(string $text, string $mutation): string
        {
            $markers = [];
            preg_match_all('/@@\d+@@/', $text, $matches);
            $markers = $matches[0];

            return match ($mutation) {
                'drop' => self::replaceFirst($text, $markers[count($markers) - 1] ?? '', ''),
                'duplicate' => count($markers) < 2
                    ? $text . ($markers[0] ?? '')
                    : self::replaceFirst($text, $markers[1], $markers[0]),
                'substitute' => $markers === []
                    ? $text . '@@999@@'
                    : self::replaceFirst($text, $markers[0], '@@999@@'),
                'unknown' => $text . ' @@999@@',
                'reorder' => self::reorder($text, $markers),
                default => $text,
            };
        }

        private static function replaceFirst(string $text, string $search, string $replace): string
        {
            if ($search === '') {
                return $text;
            }
            $position = strpos($text, $search);
            if ($position === false) {
                return $text;
            }
            return substr_replace($text, $replace, $position, strlen($search));
        }

        /** @param array<int,string> $markers */
        private static function reorder(string $text, array $markers): string
        {
            if (count($markers) < 2) {
                return $text;
            }

            $index = 0;
            $reversed = array_reverse($markers);
            return (string) preg_replace_callback(
                '/@@\d+@@/',
                static function () use (&$index, $reversed): string {
                    return $reversed[$index++];
                },
                $text
            );
        }
    }
}

namespace {
    $scenario = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    \Bblslug\HttpClient::$scenario = [
        'mutation' => (string) ($scenario['mutation'] ?? 'identity'),
        'translated' => isset($scenario['translated']) ? (string) $scenario['translated'] : null,
    ];

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: (string) ($scenario['format'] ?? 'text'),
            modelKey: 'deepl:free',
            text: (string) ($scenario['input'] ?? ''),
            filters: array_values(array_map('strval', $scenario['filters'] ?? [])),
            validate: (bool) ($scenario['validate'] ?? true),
            verbose: false
        );
        echo json_encode([
            'type' => 'success',
            'result' => $result['result'],
            'prepared' => $result['prepared'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } catch (\Throwable $exception) {
        echo json_encode([
            'type' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
}
