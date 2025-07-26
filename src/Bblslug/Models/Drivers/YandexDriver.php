<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Yandex Foundation Models driver: builds requests and parses responses
 * for text completions (synchronous).
 */
class YandexDriver implements ModelDriverInterface
{
    /**
     * Markers for segmenting translation.
     */
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
    * Build the HTTP request parameters for Yandex Foundation Models.
    *
    * @param array<string,mixed> $config  Model config from registry
    * @param string              $text    Input text or HTML after placeholder filters are applied
    * @param array<string,mixed> $options Request options:
    *     - format (string) 'text'|'html'
    *     - temperature (float)
    *     - context (string|null)
    *     - maxTokens (int|null)
    *
    * @return array{url:string,headers:string[],body:string}
    * @throws \RuntimeException If required configuration or environment variable is missing
    */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];

        // Retrieve folder ID from injected options
        if (empty($options['folder_id'])) {
            throw new \RuntimeException(
                'YandexDriver: missing "folder_id" in $options; ' .
                'declare under requirements.variables in models.yaml'
            );
        }
        $folderId = $options['folder_id'];

        // Construct model URI.
        $modelName = $defaults['model'] ?? '';
        $modelUri  = sprintf('gpt://%s/%s', $folderId, $modelName);

        // Prepare completion options.
        $completionOptions = [
            'stream'      => false,
            'temperature' => $options['temperature'] ?? $defaults['temperature'] ?? 0.0,
            'maxTokens'   => (int) ($options['maxTokens'] ?? $defaults['max_tokens'] ?? 0),
        ];

        // Render system prompt via template.
        $systemPrompt = Prompts::render(
            'translator',
            $options['format'] ?? $config['format'] ?? 'text',
            [
                'source'  => $defaults['source_lang'] ?? 'auto',
                'target'  => $defaults['target_lang'] ?? 'EN',
                'start'   => self::START,
                'end'     => self::END,
                'context' => $options['context'] ?? '',
            ]
        );

        // Build request payload.
        $payload = [
            'modelUri'          => $modelUri,
            'completionOptions' => $completionOptions,
            'messages'          => [
                ['role' => 'system', 'text' => $systemPrompt],
                ['role' => 'user',   'text' => self::START . "\n{$text}\n" . self::END],
            ],
        ];

        // Send headers from config.
        $headers = $config['requirements']['headers'] ?? [];

        return [
            'url'     => $config['endpoint'],
            'headers' => $headers,
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
    * Parse the raw API response into translated text.
    *
    * @param array<string,mixed> $config       Model config from registry
    * @param string              $responseBody Raw HTTP response body
    *
    * @return string Translated text
    * @throws \RuntimeException On API error or unexpected response format
    */
    public function parseResponse(array $config, string $responseBody): string
    {
        $data = json_decode($responseBody, true);

        // API-level errors
        if (isset($data['error'])) {
            $err = $data['error'];
            $msg = $err['message'] ?? json_encode($err);
            $code = $err['httpCode'] ?? null;
            // folder ID mismatch
            if ($code === 400 && stripos($msg, 'folder ID') !== false) {
                throw new \RuntimeException("Yandex API folder-id mismatch: {$msg}");
            }
            // authentication failures
            if ($code === 401 || (isset($err['httpStatus']) && stripos($err['httpStatus'], 'Unauthorized') !== false)) {
                throw new \RuntimeException("Yandex API authentication error: {$msg}");
            }
            // internal server errors
            if ($code === 500) {
                throw new \RuntimeException("Yandex API internal server error: {$msg}");
            }
            $codePart = $code ? " (HTTP {$code})" : '';
            throw new \RuntimeException("Yandex API error{$codePart}: {$msg}");
        }

        // Extract translated text.
        if (isset($data['result']['alternatives'][0]['message']['text'])) {
            $text = $data['result']['alternatives'][0]['message']['text'];
        } elseif (isset($data['completions'][0]['text'])) {
            $text = $data['completions'][0]['text'];
        } else {
            throw new \RuntimeException("YandexDriver: unexpected response format: {$responseBody}");
        }

        // Pull out content between markers.
        if (
            preg_match(
                '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s',
                $text,
                $matches
            )
        ) {
            return trim($matches[1]);
        }
        throw new \RuntimeException("YandexDriver: markers not found in response");
    }
}
