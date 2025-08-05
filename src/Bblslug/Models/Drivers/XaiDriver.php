<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * xAI Grok translation driver.
 *
 * Builds requests and parses responses for the xAI (Grok) Chat Completions API.
 */
class XaiDriver implements ModelDriverInterface
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
        $model = $defaults['model'] ?? throw new \RuntimeException('Missing xAI model name');
        $promptKey = $options['promptKey'] ?? 'translator';
        $sourceLang = $defaults['source_lang'] ?? 'auto';
        $targetLang = $defaults['target_lang'] ?? 'EN';
        $temperature = (float) ($options['temperature'] ?? $defaults['temperature'] ?? 0.0);

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
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => self::START . "\n{$text}\n" . self::END],
        ];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'stream'      => false,
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

        // xAI error payload (model not found, auth, etc.)
        if (isset($data['error'])) {
            $code = $data['code'] ?? 'unknown_error';
            throw new \RuntimeException("Grok API error [{$code}]: {$data['error']}");
        }

        // Normal completion path
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new \RuntimeException("Grok translation failed: {$responseBody}");
        }

        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';
        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in Grok response");
        }

        $text  = trim($matches[1]);
        $usage = $data['usage'] ?? null;

        return ['text' => $text, 'usage' => $usage];
    }
}
