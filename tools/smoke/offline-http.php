<?php

declare(strict_types=1);

namespace Bblslug;

/** Test-only HTTP replacement, loaded explicitly by the offline smoke children. */
final class HttpClient
{
    private static int $calls = 0;

    public static function request(...$arguments): array
    {
        ++self::$calls;
        $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
        $source = $request['contents'][0]['parts'][0]['text'] ?? '';
        $source = preg_replace('/\A‹‹TRANSLATION››\s*|\s*‹‹END››\z/u', '', $source);
        $packet = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($packet)) {
            throw new \RuntimeException('Offline smoke expects a Google segment object');
        }
        $capture = getenv('BBLSLUG_SMOKE_CAPTURE');
        if (is_string($capture) && $capture !== '') {
            file_put_contents(
                $capture,
                json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
                FILE_APPEND
            );
        }
        if (getenv('BBLSLUG_SMOKE_NO_HTTP') === '1') {
            throw new \RuntimeException('Smoke guard detected an unexpected HTTP request');
        }
        if ((int) getenv('BBLSLUG_SMOKE_FAIL_AT') === self::$calls) {
            return self::response('{"error":{"message":"controlled payment decline"}}', 402);
        }
        $translated = [];
        foreach ($packet as $id => $value) {
            $translated[$id] = 'Übersetzt: ' . str_replace(
                ['И. И. Иванов', 'ул. Ленина', 'Проф. Петров'],
                ['I. I. Ivanov', 'Lenin St.', 'Prof. Petrov'],
                $value
            );
        }
        $text = json_encode((object) $translated, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (($request['generationConfig']['responseMimeType'] ?? '') !== 'application/json') {
            $text = "‹‹TRANSLATION››\n" . $text . "\n‹‹END››";
        }
        return self::response(json_encode([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text]]]]],
            'usageMetadata' => ['totalTokenCount' => 10, 'promptTokenCount' => 4, 'candidatesTokenCount' => 6],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function response(string $body, int $status = 200): array
    {
        return ['status' => $status, 'body' => $body, 'headers' => [], 'debugRequest' => '', 'debugResponse' => ''];
    }
}
