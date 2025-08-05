<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Yandex Foundation Models translation driver.
 *
 * Builds requests and parses responses for the Yandex Foundation Models Chat Completions API.
 */
class YandexDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config  Model configuration from registry.
     * @param string              $text    Input text (placeholders applied).
     * @param array<string,mixed> $options Options (all optional; see README):
     *     - context     (string|null) Additional context for system prompt.
     *     - dryRun      (bool)        Skip API call (ignored here).
     *     - format      (string)      'text' or 'html'.
     *     - folder_id   (string)      Yandex folder ID (required).
     *     - maxTokens   (int)         Maximum tokens to generate.
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float)       Sampling temperature.
     *     - verbose     (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string, headers:string[], body:string}
     *
     * @throws \RuntimeException If required configuration is missing.
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];
        $context = trim((string) ($options['context'] ?? $defaults['context'] ?? ''));
        $format = $options['format'] ?? $defaults['format'] ?? 'text';
        $folderId = $options['folder_id'] ?? throw new \RuntimeException('Missing Yandex folder_id in options');
        $maxTokens = (int) ($options['maxTokens'] ?? $defaults['max_tokens'] ?? 0);
        $modelName = $defaults['model'] ?? throw new \RuntimeException('Missing Yandex model name');
        $promptKey = $options['promptKey'] ?? 'translator';
        $sourceLang = $defaults['source_lang'] ?? 'auto';
        $targetLang = $defaults['target_lang'] ?? 'EN';
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;

        // Construct model URI.
        $modelUri = sprintf('gpt://%s/%s', $folderId, $modelName);

        // Render system prompt
        $systemPrompt = Prompts::render(
            $promptKey,
            $format,
            [
                'source'  => $sourceLang,
                'target'  => $targetLang,
                'start'   => self::START,
                'end'     => self::END,
                'context' => $context !== '' ? "Context: {$context}" : '',
            ]
        );

        // Compose chat messages
        $messages = [
            ['role' => 'system', 'text' => $systemPrompt],
            ['role' => 'user',   'text' => self::START . "\n{$text}\n" . self::END],
        ];

        // Prepare payload
        $payload = [
            'modelUri'          => $modelUri,
            'completionOptions' => [
                'stream'      => false,
                'temperature' => (float) $temperature,
                'maxTokens'   => $maxTokens,
            ],
            'messages'          => $messages,
        ];

        return [
            'url'     => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Parses the JSON, extracts the assistant’s reply between markers
     * defined by self::START and self::END.
     *
     * @param array<string,mixed> $config       Model configuration (unused).
     * @param string              $responseBody Raw HTTP response body.
     *
     * @return array{text:string, usage:array<string,mixed>|null}
     *
     * @throws \RuntimeException If the response is malformed or markers are missing.
     */
    public function parseResponse(array $config, string $responseBody): array
    {
        $data = json_decode($responseBody, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response: {$responseBody}");
        }

        // API-level errors
        if (isset($data['error'])) {
            $error = $data['error'];
            $message = $error['message'] ?? json_encode($error);
            $code = $error['httpCode'] ?? null;

            // folder ID mismatch
            if ($code === 400 && stripos($message, 'folder ID') !== false) {
                throw new \RuntimeException("Yandex API folder-id mismatch: {$message}");
            }

            // authentication failures
            if (
                $code === 401
                || (isset($error['httpStatus']) && stripos($error['httpStatus'], 'Unauthorized') !== false)
            ) {
                throw new \RuntimeException("Yandex API authentication error: {$message}");
            }

            // internal server errors
            if ($code === 500) {
                throw new \RuntimeException("Yandex API internal server error: {$message}");
            }

            $codePart = $code ? " (HTTP {$code})" : '';
            throw new \RuntimeException("Yandex API error{$codePart}: {$message}");
        }

        // Extract translated text
        $content = null;
        if (isset($data['result']['alternatives'][0]['message']['text'])) {
            $content = $data['result']['alternatives'][0]['message']['text'];
        } elseif (isset($data['completions'][0]['text'])) {
            $content = $data['completions'][0]['text'];
        }

        if (!is_string($content)) {
            throw new \RuntimeException("Yandex translation failed: {$responseBody}");
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';

        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in Yandex response");
        }

        $text = trim($matches[1]);

        // Usage statistics
        $usage = $data['result']['usage'] ?? null;

        return [
            'text'  => $text,
            'usage' => $usage,
        ];
    }
}
