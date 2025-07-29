<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * OpenAI Chat-based translation driver using explicit markers.
 */
class OpenAiDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * Build HTTP request params for OpenAI.
     *
     * @param array<string,mixed> $config  Model config from registry
     * @param string              $text    Input text
     * @param array<string,mixed> $options Options: [
     *     'dryRun'      => bool,
     *     'format'      => 'text'|'html',
     *     'verbose'     => bool,
     *     'temperature' => float,
     *     'context'     => string|null,
     * ]
     *
     * @return array{
     *     body: string,
     *     headers: string[],
     *     url: string
     * }
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];
        $model = $defaults['model'] ?? throw new \RuntimeException('Missing OpenAI model name');
        $format = $options['format'] ?? 'text';
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;
        $context = trim((string) ($options['context'] ?? $defaults['context'] ?? ''));
        $sourceLang = $defaults['source_lang'] ?? 'auto';
        $targetLang = $defaults['target_lang'] ?? 'EN';

        // Render system prompt from YAML templates
        $systemPrompt = Prompts::render(
            'translator',
            $format,
            [
                'source'  => $sourceLang,
                'target'  => $targetLang,
                'start'   => self::START,
                'end'     => self::END,
                'context' => $context !== '' ? "Context: {$context}" : '',
            ]
        );

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $text],
        ];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => (float) $temperature,
        ];

        return [
            'url'     => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Parse the translated text from OpenAI's response.
     *
     * @param array<string,mixed> $config       Model config (not used)
     * @param string              $responseBody Raw HTTP body
     *
     * @return array{
     *     text:  string,
     *     usage: array<string,mixed>|null
     *
     * @throws \RuntimeException If response is malformed or markers not found
     */
    public function parseResponse(array $config, string $responseBody): array
    {
        // First, extract the 'content' field from the JSON wrapper
        $data = json_decode($responseBody, true);
        if (
            !isset($data['choices'][0]['message']['content'])
            || !is_string($data['choices'][0]['message']['content'])
        ) {
            throw new \RuntimeException("OpenAI translation failed: {$responseBody}");
        }
        $content = $data['choices'][0]['message']['content'];

        // Now pull out everything between our markers
        if (
            !preg_match(
                '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s',
                $content,
                $matches
            )
        ) {
            throw new \RuntimeException("Markers not found in OpenAI response");
        }
        $text = trim($matches[1]);

        // Usage statistics
        $usage = $data['usage'] ?? null;

        return ['text' => $text, 'usage' => $usage];
    }
}
