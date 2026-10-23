<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled OpenAI-compatible responses for CLI segment batching tests. */
    class HttpClient
    {
        public static function request(...$arguments): array
        {
            $counterFile = getenv('BBLSLUG_TEST_COUNTER_FILE');
            $count = 1;
            if (
                !($arguments['dryRun'] ?? false)
                && is_string($counterFile) && $counterFile !== ''
            ) {
                $previous = @file_get_contents($counterFile);
                $count = $previous === false || $previous === '' ? 1 : ((int) $previous + 1);
                @file_put_contents($counterFile, (string) $count);
            }
            $failBatch = getenv('BBLSLUG_TEST_FAIL_BATCH');
            if (is_string($failBatch) && $failBatch !== '' && (int) $failBatch === $count) {
                return [
                    'body' => '{"error":{"message":"controlled batch failure"}}',
                    'debugRequest' => '', 'debugResponse' => '', 'headers' => [], 'status' => 500,
                ];
            }

            $body = json_decode((string) ($arguments['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $content = $body['messages'][1]['content'] ?? '';
            if (!is_string($content)) {
                throw new \RuntimeException('Fixture request is missing the user message.');
            }
            $start = strpos($content, '‹‹TRANSLATION››');
            $end = strpos($content, '‹‹END››', $start === false ? 0 : $start);
            $json = $start === false || $end === false
                ? '{}'
                : trim(substr($content, $start + strlen('‹‹TRANSLATION››'), $end - $start - strlen('‹‹TRANSLATION››')));
            $segments = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $translated = [];
            foreach ($segments as $id => $value) {
                $translated[$id] = $value === 'Hello' ? 'Bonjour' : 'T:' . $value;
            }
            $content = "‹‹TRANSLATION››\n" . json_encode(
                $translated,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n‹‹END››";
            return [
                'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => [
                    'content' => $content,
                ]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'debugRequest' => '', 'debugResponse' => '', 'headers' => [], 'status' => 200,
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    \Bblslug\Console\Cli::run();
}
