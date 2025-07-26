<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Google Gemini translation driver using Generative Language API.
 */
class GoogleDriver implements ModelDriverInterface
{
    private const START = '‹‹TRANSLATION››';
    private const END   = '‹‹END››';

    /**
     * Build HTTP request params for Google Gemini.
     *
     * @param array<string,mixed> $config  Model config from registry
     * @param string              $text    Input text
     * @param array<string,mixed> $options Options: [
     *     'dryRun'          => bool,
     *     'format'          => 'text'|'html',
     *     'verbose'         => bool,
     *     'temperature'     => float,
     *     'candidateCount'  => int,
     *     'maxOutputTokens' => int|null,
     *     'thinkingBudget'   => int|null,         // Gemini 2.5 only
     *     'includeThoughts'  => bool|null,        // Gemini 2.5 only
     *     'context'         => string|null
     * ]
     *
     * @return array{
     *     url: string,
     *     headers: string[],
     *     body: string
     * }
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];
        $temperature = $options['temperature'] ?? $defaults['temperature'] ?? 0.0;
        $candidateCount = $options['candidateCount'] ?? $defaults['candidateCount'] ?? 1;
        $maxOutputTokens = $options['maxOutputTokens'] ?? $defaults['maxOutputTokens'] ?? null;
        $context = trim((string) ($options['context'] ?? $defaults['context'] ?? ''));

        $sourceLang = $defaults['source_lang'] ?? 'auto';
        $targetLang = $defaults['target_lang'] ?? 'EN';
        $format = $options['format'] ?? 'text';

        $systemText = Prompts::render(
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

        // Wrap user text in markers
        $contentText = self::START . "\n" . $text . "\n" . self::END;

        // Build JSON payload
        $body = [
            'system_instruction'  => ['parts' => [['text' => $systemText]]],
            'contents'            => [['parts' => [['text' => $contentText]]]],
            'generationConfig'    => array_filter([
                'temperature'     => (float)$temperature,
                'candidateCount'  => (int)$candidateCount,
                'maxOutputTokens' => $maxOutputTokens !== null ? (int)$maxOutputTokens : null,
            ], fn($v) => $v !== null),
        ];

        // thinkingConfig for Gemini 2.5+ (optional)
        $thinking = array_filter([
            'thinkingBudget'  => isset($options['thinkingBudget'])  ? (int)$options['thinkingBudget']  : null,
            'includeThoughts' => isset($options['includeThoughts']) ? (bool)$options['includeThoughts'] : null,
        ], fn($v) => $v !== null);
        if ($thinking) {
            $body['generationConfig']['thinkingConfig'] = $thinking;
        }

        // Assemble headers, including API key
        $headers = $config['requirements']['headers'] ?? [];

        return [
            'url'     => $config['endpoint'],
            'headers' => $headers,
            'body'    => json_encode($body, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Parse the translated text from Gemini's response.
     *
     * @param array<string,mixed> $config       Model config (not used)
     * @param string              $responseBody Raw HTTP body
     *
     * @return string Translated text (placeholders still in)
     *
     * @throws \RuntimeException If response malformed or no candidate found
     */
    public function parseResponse(array $config, string $responseBody): string
    {
        // First, extract the 'content' field from the JSON wrapper
        $data = json_decode($responseBody, true);
        $candidate = $data['candidates'][0] ?? null;

        $finish = $candidate['finishReason'] ?? '';
        if ($finish === 'MAX_TOKENS') {
            throw new \RuntimeException(
                "Gemini: translation was truncated—reached model's max output tokens.\n" .
                "Try increasing maxOutputTokens or splitting input into smaller chunks."
            );
        }
        if ($finish !== 'STOP') {
            throw new \RuntimeException(
                "Gemini: unexpected finishReason «{$finish}» — check the response output:\n{$responseBody}"
            );
        }

        $contentWrap = $candidate['content'] ?? null;
        if (!isset($contentWrap['parts']) || !is_array($contentWrap['parts'])) {
            throw new \RuntimeException("Gemini translation failed: no text parts in response: {$responseBody}");
        }

        $texts = [];
        foreach ($contentWrap['parts'] as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        $content = implode('', $texts);

        // Extract between markers
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
            "Markers not found in Gemini response: " . substr($content, 0, 200) . '…'
        );
    }
}
