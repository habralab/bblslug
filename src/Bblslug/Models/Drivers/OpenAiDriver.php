<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * OpenAI GPT translation driver.
 *
 * Builds requests and parses responses for the OpenAI Chat Completions API.
 */
class OpenAiDriver implements ModelDriverInterface
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
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float)       Sampling temperature.
     *     - verbose     (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string, headers:string[], body:string}
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
                : 'text');

        $model = \is_string($defaults['model'] ?? null)
            ? $defaults['model']
            : throw new \RuntimeException('Missing OpenAI model name');

        $promptKey = \is_string($options['promptKey'] ?? null)
            ? $options['promptKey']
            : 'translator';

        $sourceLang = \is_string($defaults['source_lang'] ?? null)
            ? $defaults['source_lang']
            : 'auto';

        $targetLang = \is_string($defaults['target_lang'] ?? null)
            ? $defaults['target_lang']
            : 'EN';

        $tempRaw = $options['temperature'] ?? ($defaults['temperature'] ?? 0.0);
        $temperature = (\is_float($tempRaw) || \is_int($tempRaw) || \is_numeric($tempRaw))
            ? (float)$tempRaw
            : 0.0;

        // Render system prompt from YAML templates
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
        ];

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

        // Extract the first choice safely
        $first = null;
        $choices = $data['choices'] ?? null;
        if (\is_array($choices) && isset($choices[0]) && \is_array($choices[0])) {
            $first = $choices[0];
        }

        // Extract raw content early
        $contentRaw = '';
        if (\is_array($first) && \is_array($first['message'] ?? null)) {
            $contentRaw = $first['message']['content'] ?? '';
        }
        $contentRaw = \is_string($contentRaw) ? $contentRaw : '';

        // If OpenAI cut output by tokens, fail with a clear message before marker search
        $finishReason = null;
        if (\is_array($first) && \is_string($first['finish_reason'] ?? null)) {
            $finishReason = $first['finish_reason'];
        }
        if ($finishReason === 'length') {
            throw new \RuntimeException(
                "OpenAI: translation was truncated (finish_reason=length) — "
                . "increase max_tokens or split input."
            );
        }

        // Validate response structure
        $content = $contentRaw;
        if ($content === '') {
            throw new \RuntimeException("OpenAI translation failed: {$responseBody}");
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';
        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in OpenAI response");
        }
        $text = trim($matches[1]);

        // Usage statistics
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
            $usage = $typed;
        }

        return ['text' => $text, 'usage' => $usage];
    }
}
