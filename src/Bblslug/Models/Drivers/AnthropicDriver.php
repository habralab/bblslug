<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Anthropic Claude translation driver.
 *
 * Builds requests and parses responses for the Anthropic Messages API.
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
     *     - effort      (string)      Reasoning effort for models that support it.
     *     - thinking    (array|string|null) Thinking configuration, or `adaptive`.
     *     - maxTokens   (int)         Maximum tokens to generate.
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float|null)  Sampling temperature; null omits the parameter.
     *     - verbose     (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string, headers:string[], body:string}
     *
     * @throws \RuntimeException If required configuration is missing.
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = \is_array($config['defaults'] ?? null) ? $config['defaults'] : [];

        $ctxRaw = $options['context'] ?? ($defaults['context'] ?? '');
        $context = (\is_scalar($ctxRaw) || ($ctxRaw instanceof \Stringable))
            ? \trim((string)$ctxRaw)
            : '';

        $format = \is_string($options['format'] ?? null)
            ? $options['format']
            : (\is_string($defaults['format'] ?? null)
                ? $defaults['format']
                : (\is_string($config['format'] ?? null) ? $config['format'] : 'text'));

        $maxRaw = $options['maxTokens'] ?? ($defaults['max_tokens'] ?? 1000);
        if (
            (!\is_int($maxRaw) && !\is_string($maxRaw))
            || filter_var($maxRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
        ) {
            throw new \InvalidArgumentException('maxTokens must be a positive integer');
        }
        $maxTokens = (int)$maxRaw;

        $model = \is_string($defaults['model'] ?? null)
            ? $defaults['model']
            : throw new \RuntimeException('Missing Anthropic model name');

        $promptKey = \is_string($options['promptKey'] ?? null)
            ? $options['promptKey']
            : 'translator';

        $source = \is_string($defaults['source_lang'] ?? null)
            ? $defaults['source_lang']
            : 'auto';

        $target = \is_string($defaults['target_lang'] ?? null)
            ? (string)$defaults['target_lang']
            : 'EN';

        $settings = array_replace($defaults, $options);
        $tempRaw = \array_key_exists('temperature', $settings) ? $settings['temperature'] : 0.0;
        $temperature = $tempRaw === null ? null : (\is_numeric($tempRaw) ? (float)$tempRaw : 0.0);

        $thinking = $settings['thinking'] ?? null;
        if ($thinking === 'adaptive') {
            $thinking = ['type' => 'adaptive'];
        }

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
            ['role' => 'user',   'content' => self::START . "\n" . $text . "\n" . self::END],
        ];

        // Prepare payload
        $payload = [
            'model' => $model,
            'system' => $systemPrompt,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];
        // Adaptive thinking models reject temperature. Older Claude models
        // continue to use the ordinary sampling parameter.
        if ($temperature !== null && $thinking === null) {
            $payload['temperature'] = $temperature;
        }
        if (\is_array($thinking)) {
            $payload['thinking'] = $thinking;
        }
        if (\is_string($settings['effort'] ?? null)) {
            $payload['output_config'] = ['effort' => $settings['effort']];
        }

        // Normalize return shape and types
        $url = \is_string($config['endpoint'] ?? null) ? (string)$config['endpoint'] : '';
        $headers = [];
        $req = \is_array($config['requirements'] ?? null) ? $config['requirements'] : null;
        $headersSrc = \is_array($req['headers'] ?? null) ? $req['headers'] : [];
        foreach ((array)$headersSrc as $h) {
            if (\is_string($h)) {
                $headers[] = $h;
            }
        }
        $body = \json_encode($payload, JSON_UNESCAPED_UNICODE);

        return [
            'url'     => $url,
            'headers' => $headers,
            'body'    => \is_string($body) ? $body : '{}',
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
        $data = \json_decode($responseBody, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON response: {$responseBody}");
        }

        // Catch API-level errors
        if (\is_array($data['error'] ?? null)) {
            $error   = $data['error'];
            $message = \is_string($error['message'] ?? null)
                ? $error['message']
                : (\json_encode($error, JSON_UNESCAPED_UNICODE) ?: 'unknown error');
            throw new \RuntimeException("Anthropic API error: {$message}");
        }

        $stopReason = \is_string($data['stop_reason'] ?? null) ? $data['stop_reason'] : '';
        if ($stopReason === 'max_tokens' || $stopReason === 'model_context_window_exceeded') {
            throw new \RuntimeException(
                "Anthropic: translation was truncated (stop_reason={$stopReason}) — "
                . "increase maxTokens or split input."
            );
        }
        if ($stopReason !== 'end_turn') {
            throw new \RuntimeException("Anthropic: unexpected stop_reason '{$stopReason}'");
        }

        // Ignore thinking/tool blocks; only text blocks belong to the translation.
        $content = '';
        $blocks = \is_array($data['content'] ?? null) ? $data['content'] : [];
        foreach ($blocks as $block) {
            if (\is_array($block) && ($block['type'] ?? null) === 'text' && \is_string($block['text'] ?? null)) {
                $content .= $block['text'];
            }
        }
        if ($content === '') {
            throw new \RuntimeException('Anthropic translation failed: response has no text blocks');
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';

        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in Anthropic response");
        }

        $text = trim($matches[1]);

        // Raw usage statistics from Anthropic
        $usage = null;
        $u = $data['usage'] ?? null;
        if (\is_array($u)) {
            /** @var array<string,mixed> $typed */
            $typed = [];
            foreach ($u as $k => $v) {
                if (\is_string($k)) {
                    $typed[$k] = $v;
                }
            }
            // Messages has no total_tokens field. Cached input is reported separately.
            $total = 0;
            $tokenFields = ['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'];
            foreach ($tokenFields as $field) {
                $value = $typed[$field] ?? 0;
                $total += \is_numeric($value) ? (int)$value : 0;
            }
            $typed['total_tokens'] = $total;
            $usage = $typed;
        }

        return [
            'text'  => $text,
            'usage' => $usage,
        ];
    }
}
