<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * xAI Grok driver using explicit markers and OpenAI‐style API.
 */
class XaiDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * Build HTTP request params for Grok.
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
     *     url:     string,
     *     headers: string[],
     *     body:    string
     * }
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults    = $config['defaults'] ?? [];
        $model       = $defaults['model'] ?? throw new \RuntimeException('Missing xAI model name');
        $temperature = (float) ($options['temperature'] ?? $defaults['temperature'] ?? 0.0);
        $context     = trim((string) ($options['context'] ?? $defaults['context'] ?? ''));
        $format      = $options['format'] ?? 'text';

        $systemPrompt = Prompts::render(
            'translator',
            $format,
            [
                'source'  => $defaults['source_lang'] ?? 'auto',
                'target'  => $defaults['target_lang'] ?? 'EN',
                'start'   => self::START,
                'end'     => self::END,
                'context' => $context !== '' ? "Context: {$context}" : '',
            ]
        );

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
     * Parse the translated text from Grok's response.
     *
     * @param array<string,mixed> $config       Model config (not used)
     * @param string              $responseBody Raw HTTP body
     *
     * @return array{
     *     text:  string,
     *     usage: array<string,mixed>|null
     * }
     *
     * @throws \RuntimeException If API returned an error or markers not found
     */
    public function parseResponse(array $config, string $responseBody): array
    {
        $data = json_decode($responseBody, true);

        // xAI error payload (model not found, auth, etc.)
        if (isset($data['error'])) {
            $code = $data['code'] ?? 'unknown_error';
            throw new \RuntimeException("Grok API error [{$code}]: {$data['error']}");
        }

        // Normal completion path
        if (
            empty($data['choices'][0]['message']['content'])
            || !is_string($data['choices'][0]['message']['content'])
        ) {
            throw new \RuntimeException("Grok translation failed: {$responseBody}");
        }

        $content = $data['choices'][0]['message']['content'];

        if (
            !preg_match(
                '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s',
                $content,
                $matches
            )
        ) {
            throw new \RuntimeException("Markers not found in Grok response");
        }

        $text  = trim($matches[1]);
        $usage = $data['usage'] ?? null;

        return ['text' => $text, 'usage' => $usage];
    }
}
