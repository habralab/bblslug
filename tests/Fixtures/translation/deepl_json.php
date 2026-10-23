<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled DeepL endpoint, exercising the real request and response driver. */
    class HttpClient
    {
        /** @var array<string,mixed> */
        public static array $scenario = [];
        /** @var array<int,mixed> */
        public static array $texts = [];
        public static int $calls = 0;

        public static function request(...$arguments): array
        {
            if ($arguments['dryRun'] ?? false) {
                return ['status' => 0, 'body' => '', 'debugRequest' => '', 'debugResponse' => '', 'headers' => []];
            }
            self::$calls++;
            if (str_starts_with(ltrim($arguments['body']), '{')) {
                $payload = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            } else {
                parse_str($arguments['body'], $payload);
            }
            self::$texts = is_array($payload['text']) ? $payload['text'] : [$payload['text']];
            if (isset(self::$scenario['htmlMutation'])) {
                $html = self::$texts[0];
                preg_match('/<span\b[^>]*>@@[0-9]+@@<\/span>/', $html, $wrapper);
                if (self::$scenario['htmlMutation'] === 'move') {
                    $html = str_replace($wrapper[0], '', $html);
                    $html = str_replace('</a>', $wrapper[0] . '</a>', $html);
                } elseif (self::$scenario['htmlMutation'] === 'drop') {
                    $html = str_replace($wrapper[0], '', $html);
                } elseif (self::$scenario['htmlMutation'] === 'duplicate') {
                    $html .= $wrapper[0];
                } else {
                    $html = str_replace(['Hi', 'there', 'Documentation'], ['Hallo', 'da', 'Dokumentation'], $html);
                }
                self::$texts = [$html];
            }
            $translations = self::$scenario['translations'] ?? self::$texts;
            return [
                'status' => 200,
                'body' => json_encode(['translations' => array_map(
                    static fn ($text): array => ['text' => $text],
                    $translations
                )], JSON_THROW_ON_ERROR),
                'debugRequest' => '', 'debugResponse' => '', 'headers' => [],
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    $scenario = json_decode(file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    \Bblslug\HttpClient::$scenario = $scenario;
    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'fixture-key',
            format: $scenario['format'] ?? 'json',
            modelKey: 'deepl:free',
            text: $scenario['source'],
            filters: $scenario['filters'] ?? [],
            variables: ['json_transport' => 'text_batch'],
            validateEmbeddedJson: ($scenario['format'] ?? 'json') !== 'html'
        );
        echo json_encode([
            'type' => 'success', 'result' => $result['result'], 'httpStatus' => $result['httpStatus'],
            'texts' => \Bblslug\HttpClient::$texts, 'calls' => \Bblslug\HttpClient::$calls,
        ], JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        echo json_encode(['type' => $e::class, 'message' => $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}
