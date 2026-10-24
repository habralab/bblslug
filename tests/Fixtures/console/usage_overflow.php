<?php

declare(strict_types=1);

namespace Bblslug {
    class HttpClient
    {
        public static function request(...$arguments): array
        {
            $body = json_decode((string) ($arguments['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $content = (string) ($body['messages'][1]['content'] ?? '');
            $marker = '‹‹TRANSLATION››';
            $start = strpos($content, $marker);
            $end = strpos($content, '‹‹END››', $start === false ? 0 : $start);
            $json = $start === false || $end === false
                ? '{}'
                : trim(substr($content, $start + strlen($marker), $end - $start - strlen($marker)));
            $segments = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $translated = [];
            foreach ($segments as $id => $value) {
                $translated[$id] = 'T:' . $value;
            }
            $total = getenv('BBLSLUG_TEST_USAGE_TOTAL');
            $total = $total === false || $total === '' ? 10 : (int) $total;
            return [
                'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => "‹‹TRANSLATION››\n" . json_encode(
                        $translated,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ) . "\n‹‹END››",
                ]]], 'usage' => [
                    'total_tokens' => $total, 'prompt_tokens' => 4, 'completion_tokens' => 6,
                ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'debugRequest' => '', 'debugResponse' => '', 'headers' => [], 'status' => 200,
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
