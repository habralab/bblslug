<?php

namespace Bblslug\Models;

use Bblslug\Models\ModelDriverInterface;

/**
 * Anthropic Claude driver: builds requests and parses responses for text completions.
 */

class AnthropicDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * Build the HTTP request parameters for Anthropic
     *
     * @param array<string,mixed> $config  Model config from registry
     * @param string              $text    Input text or HTML
     * @param array<string,mixed> $options Options: [
     *     'format'      => 'text'|'html',
     *     'temperature' => float,
     *     'context'     => string|null,
     * ]
     *
     * @return array{url:string, headers:string[], body:string}
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];
        $model = $defaults['model'] ?? throw new \RuntimeException('Missing Anthropic model name');
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;
        $maxTokens = $defaults['max_tokens'] ?? 1000;
        $context = trim((string)($options['context'] ?? $defaults['context'] ?? ''));
        $format = $options['format'] ?? $config['format'] ?? 'text';
        $source = $defaults['source_lang'] ?? 'auto';
        $target = $defaults['target_lang'] ?? 'EN';

        // System prompt template based on format
        if ($format === 'html') {
            $template = <<<'EOD'
You are a professional HTML translator.
- Translate from {source} to {target}.
- Preserve all HTML tags and attributes exactly.
- Translate only visible text nodes.
- Translate HTML attributes that contain natural language (e.g., title, alt, aria-label).
- Do not touch any URLs or IDN domain names.
- Do not modify or translate placeholders of the form @@number@@.
- Wrap the translated HTML between markers: {start} and {end}.
{ctx}
EOD;
        } else {
            $template = <<<'EOD'
You are a professional translator.
- Translate from {source} to {target}.
- Translate the input text.
- Do not modify or translate placeholders of the form @@number@@.
- Do not alter any URLs or IDN domain names.
- Wrap the translated text between markers: {start} and {end}.
{ctx}
EOD;
        }

        $systemPrompt = strtr($template, [
            '{source}' => $source,
            '{target}' => $target,
            '{start}'  => self::START,
            '{end}'    => self::END,
            '{ctx}'    => $context !== '' ? "Context: {$context}" : '',
        ]);

        // Build message sequence
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => self::START . "\n" . $text . "\n" . self::END],
        ];

        // Construct payload for Messages API
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => (float)$temperature,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return [
            'url'     => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body'    => $body,
        ];
    }

    /**
     * Parse the raw API response into translated text.
     *
     * @param array<string,mixed> $config       Model config from registry
     * @param string              $responseBody Raw JSON response body
     *
     * @return string Translated text
     * @throws \RuntimeException If the response is malformed or an API error is returned
     */
    public function parseResponse(array $config, string $responseBody): string
    {
        $data = json_decode($responseBody, true);

        // Catch API-level errors
        if (isset($data['error'])) {
            $err = $data['error'];
            $msg = $err['message'] ?? json_encode($err);
            // Specific handling for max_tokens errors
            if (strpos($msg, 'max_tokens') !== false) {
                throw new \RuntimeException(
                    "Requested max_tokens ({$config['defaults']['max_tokens']}) exceeds model limit. " .
                    "Please reduce to at most the allowed number of tokens.\n\nResponse: {$msg}"
                );
            }
            throw new \RuntimeException("Anthropic API error: {$msg}");
        }

        // Extract assistant content
        if (
            !isset($data['choices'][0]['message']['content']) ||
            !is_string($data['choices'][0]['message']['content'])
        ) {
            throw new \RuntimeException("Invalid Anthropic response: {$responseBody}");
        }
        $content = $data['choices'][0]['message']['content'];

        // Pull out text between markers
        if (
                preg_match(
                    '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s',
                    $content,
                    $matches
                )
        ) {
            return trim($matches[1]);
        }

        // If markers are missing, throw to avoid silent failures
        throw new \RuntimeException(
            "Markers not found in Anthropic response: " . substr($content, 0, 200) . '…'
        );
    }
}
