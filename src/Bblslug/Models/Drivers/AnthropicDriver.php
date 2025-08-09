<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Anthropic Claude translation driver.
 *
 * Builds requests and parses responses for the Anthropic Claude Chat Completions API.
 */

class AnthropicDriver implements ModelDriverInterface
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
     *     - format      (string)      Indicates which prompt format to use.
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
        $context = trim((string)($options['context'] ?? $defaults['context'] ?? ''));
        $format = $options['format'] ?? $defaults['format'] ?? $config['format'] ?? 'text';
        $model = $defaults['model'] ?? throw new \RuntimeException('Missing Anthropic model name');
        $maxTokens = $options['maxTokens'] ?? $defaults['max_tokens'] ?? 1000;
        $promptKey = $options['promptKey'] ?? 'translator';
        $source = $defaults['source_lang'] ?? 'auto';
        $target = $defaults['target_lang'] ?? 'EN';
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;

        // Render system prompt
        $systemPrompt = Prompts::render(
            $promptKey,
            $format,
            [
                'source'  => $source,
                'target'  => $target,
                'start'   => self::START,
                'end'     => self::END,
                'context' => $context !== '' ? "Context: {$context}" : '',
            ]
        );

        // Compose chat messages
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => self::START . "\n" . $text . "\n" . self::END],
        ];

        // Prepare payload
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => (float)$temperature,
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

        // Catch API-level errors
        if (isset($data['error'])) {
            $error = $data['error'];
            $message = $error['message'] ?? json_encode($error);
            // Specific handling for max_tokens errors
            if (strpos($message, 'max_tokens') !== false) {
                throw new \RuntimeException(
                    "Requested max_tokens ({$config['defaults']['max_tokens']}) exceeds model limit. " .
                    "Please reduce to at most the allowed number.\n\nResponse: {$message}"
                );
            }
            throw new \RuntimeException("Anthropic API error: {$message}");
        }

        // Extract raw content early (may be partial when truncated)
        $contentRaw = $data['choices'][0]['message']['content'] ?? '';
        $contentRaw = is_string($contentRaw) ? $contentRaw : '';

        // If Anthropic cut output by tokens, fail with a clear message before marker search
        $finishReason = $data['choices'][0]['finish_reason'] ?? null;
        if ($finishReason === 'length') {
            throw new \RuntimeException(
                "Anthropic: translation was truncated (finish_reason=length) — increase max_tokens or split input. "
            );
        }

        // Validate content
        $content = $contentRaw;
        if ($content === '') {
            throw new \RuntimeException("Anthropic translation failed: {$responseBody}");
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';

        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in Anthropic response");
        }

        $text = trim($matches[1]);

        // Raw usage statistics from Anthropic
        $usage = $data['usage'] ?? null;

        return [
            'text'  => $text,
            'usage' => $usage,
        ];
    }
}
