<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Google Gemini translation driver using Generative Language API.
 *
 * Builds requests and parses responses for the Google Generative Language Chat Completions API.
 */
class GoogleDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config  Model configuration from registry.
     * @param string              $text    Input text (placeholders applied).
     * @param array<string,mixed> $options Options (all optional; see README):
     *     - candidateCount  (int)         Number of responses to generate.
     *     - context         (string|null) Additional context for system prompt.
     *     - format          (string)      'text' or 'html'.
     *     - includeThoughts (bool|null)   Include chain-of-thought reasoning (Gemini 2.5+).
     *     - maxOutputTokens (int|null)    Maximum tokens for output.
     *     - promptKey       (string)      Key of the prompt template in prompts.yaml.
     *     - temperature     (float)       Sampling temperature.
     *     - thinkingBudget  (int|null)    Budget for internal reasoning (Gemini 2.5+).
     *     - verbose         (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string, headers:string[], body:string}
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];
        $candidateCount = $options['candidateCount'] ?? $defaults['candidateCount'] ?? 1;
        $context = trim((string) ($options['context'] ?? $defaults['context'] ?? ''));
        $format = $options['format'] ?? $defaults['format'] ?? 'text';
        $includeThoughts = $options['includeThoughts'] ?? null;
        $maxOutputTokens = $options['maxOutputTokens'] ?? $defaults['maxOutputTokens'] ?? null;
        $promptKey = $options['promptKey'] ?? 'translator';
        $sourceLang = $defaults['source_lang'] ?? 'auto';
        $targetLang = $defaults['target_lang'] ?? 'EN';
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;
        $thinkingBudget = $options['thinkingBudget'] ?? null;

        // Render system prompt from YAML templates
        $systemText = Prompts::render(
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

        // Wrap user text in markers
        $contentText = self::START . "\n" . $text . "\n" . self::END;

        // Build JSON payload
        $generationConfig = array_filter([
            'temperature' => (float) $temperature,
            'candidateCount' => (int) $candidateCount,
            'maxOutputTokens' => $maxOutputTokens !== null ? (int) $maxOutputTokens : null,
        ], fn($value) => $value !== null);

        // Add optional thinking config
        $thinkingConfig = array_filter([
            'thinkingBudget'  => $thinkingBudget !== null ? (int) $thinkingBudget : null,
            'includeThoughts' => $includeThoughts,
        ], fn($value) => $value !== null);
        if ($thinkingConfig) {
            $generationConfig['thinkingConfig'] = $thinkingConfig;
        }

        $body = [
            'system_instruction' => ['parts' => [['text' => $systemText]]],
            'contents'           => [['parts' => [['text' => $contentText]]]],
            'generationConfig'   => $generationConfig,
        ];

        return [
            'url'     => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body'    => json_encode($body, JSON_UNESCAPED_UNICODE),
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

        $candidate = $data['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? '';

        if ($finishReason === 'MAX_TOKENS') {
            throw new \RuntimeException(
                "Gemini: translation was truncated—reached model's max output tokens.\n" .
                "Try increasing maxOutputTokens or splitting input into smaller chunks."
            );
        }

        if ($finishReason !== 'STOP') {
            throw new \RuntimeException(
                "Gemini: unexpected finishReason '{$finishReason}' — check the response output:\n{$responseBody}"
            );
        }

        $contentWrap = $candidate['content'] ?? null;

        if (!isset($contentWrap['parts']) || !is_array($contentWrap['parts'])) {
            throw new \RuntimeException("Gemini translation failed: no text parts in response: {$responseBody}");
        }

        $accumulated = '';

        foreach ($contentWrap['parts'] as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $accumulated .= $part['text'];
            }
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';

        if (!preg_match($pattern, $accumulated, $matches)) {
            throw new \RuntimeException("Markers not found in Gemini response");
        }

        $text = trim($matches[1]);

        // Usage metadata
        $usage = $data['usageMetadata'] ?? null;

        return [
            'text'  => $text,
            'usage' => $usage,
        ];
    }
}
