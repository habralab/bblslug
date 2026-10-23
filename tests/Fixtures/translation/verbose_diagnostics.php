<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled provider response for verbose diagnostic tests. */
    class HttpClient
    {
        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            $body = json_decode((string) ($arguments['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $content = '';
            foreach (($body['messages'] ?? []) as $message) {
                if (($message['role'] ?? null) === 'user' && is_string($message['content'] ?? null)) {
                    $content = preg_replace(
                        '/^‹‹TRANSLATION››\n|\n‹‹END››$/u',
                        '',
                        $message['content']
                    ) ?? $message['content'];
                    break;
                }
            }
            $scenario = (string) (getenv('BBLSLUG_DIAGNOSTIC_SCENARIO') ?: 'success');
            $secret = 'demo +/key=value';
            $secret = implode(' ', [$secret, rawurlencode($secret), urlencode($secret),
                substr(json_encode($secret, JSON_THROW_ON_ERROR), 1, -1),
                substr(json_encode(json_encode($secret, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR), 1, -1)]);
            $translated = match ($scenario) {
                'json-invalid' => $secret . str_repeat('x', 3000),
                'html-invalid' => $secret . $content . '<',
                'html-structure' => $secret . str_replace('<div>', '<span>', $content),
                default => $content,
            };
            return [
                'body' => json_encode([
                    'choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => "‹‹TRANSLATION››\n{$translated}\n‹‹END››",
                    ]]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'debugRequest' => '', 'debugResponse' => '', 'headers' => [], 'status' => 200,
            ];
        }
    }
}

namespace {
    $scenario = json_decode((string) file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    $feedback = [];
    $format = (string) ($scenario['format'] ?? 'html');
    $source = $format === 'json'
        ? json_encode(['message' => 'Hello'], JSON_THROW_ON_ERROR)
        : '<div><p>Hello</p><p>' . str_repeat('A', 2500) . '</p>'
            . '<a href="https://example.org/?token=demo%20%2B%2Fkey%3Dvalue">Docs</a>'
            . '<pre><code>echo "demo +/key=value";</code></pre></div>';
    if (is_string($scenario['source'] ?? null)) {
        $source = $scenario['source'];
    }
    putenv('BBLSLUG_DIAGNOSTIC_SCENARIO=' . (string) ($scenario['scenario'] ?? 'success'));
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    try {
        $result = \Bblslug\Bblslug::translate(
            apiKey: 'demo +/key=value',
            format: $format,
            modelKey: 'openai:gpt-5-mini',
            text: $source,
            filters: is_array($scenario['filters'] ?? null)
                ? array_values(array_map('strval', $scenario['filters']))
                : ($format === 'html' ? ['url', 'html_code'] : []),
            validate: true,
            verbose: (bool) ($scenario['verbose'] ?? true),
            onFeedback: static function (string $message, string $level = 'info') use (&$feedback): void {
                if (getenv('BBLSLUG_DIAGNOSTIC_THROW_FEEDBACK') === '1') {
                    throw new \RuntimeException('feedback callback failure');
                }
                $feedback[] = [$level, $message];
            }
        );
        echo json_encode(
            ['type' => 'success', 'result' => $result['result'], 'feedback' => $feedback],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ), "\n";
    } catch (\Throwable $exception) {
        echo json_encode(
            ['type' => $exception::class, 'message' => $exception->getMessage(), 'feedback' => $feedback],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ), "\n";
    }
}
