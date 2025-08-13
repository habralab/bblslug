<?php

declare(strict_types=1);

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
     *     - format          (string)      Indicates which prompt format to use.
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
        $defaults = \is_array($config['defaults'] ?? null) ? $config['defaults'] : [];

        $candRaw = $options['candidateCount'] ?? ($defaults['candidateCount'] ?? 1);
        $candidateCount = (\is_int($candRaw) || \is_numeric($candRaw)) ? (int) $candRaw : 1;

        $ctxRaw = $options['context'] ?? ($defaults['context'] ?? '');
        $context = (\is_scalar($ctxRaw) || ($ctxRaw instanceof \Stringable))
            ? \trim((string) $ctxRaw)
            : '';

        $format = \is_string($options['format'] ?? null)
            ? $options['format']
            : (\is_string($defaults['format'] ?? null) ? $defaults['format'] : 'text');

        $includeThoughts = null;
        if (\array_key_exists('includeThoughts', $options)) {
            $includeThoughts = (bool) $options['includeThoughts'];
        }

        $motRaw = $options['maxOutputTokens'] ?? ($defaults['maxOutputTokens'] ?? null);
        $maxOutputTokens = ($motRaw !== null && (\is_int($motRaw) || \is_numeric($motRaw)))
            ? (int) $motRaw
            : null;

        $promptKey = \is_string($options['promptKey'] ?? null) ? $options['promptKey'] : 'translator';

        $sourceLang = \is_string($defaults['source_lang'] ?? null) ? $defaults['source_lang'] : 'auto';
        $targetLang = \is_string($defaults['target_lang'] ?? null) ? $defaults['target_lang'] : 'EN';

        $tempRaw = $options['temperature'] ?? ($defaults['temperature'] ?? 0.0);
        $temperature = (\is_float($tempRaw) || \is_int($tempRaw) || \is_numeric($tempRaw))
            ? (float) $tempRaw
            : 0.0;

        $tbRaw = $options['thinkingBudget'] ?? null;
        $thinkingBudget = ($tbRaw !== null && (\is_int($tbRaw) || \is_numeric($tbRaw)))
            ? (int) $tbRaw
            : null;

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
        /** @var array<string,mixed> $generationConfig */
        $generationConfig = [
            'temperature'    => $temperature,
            'candidateCount' => $candidateCount,
        ];
        if ($maxOutputTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxOutputTokens;
        }
        $thinking = [];
        if ($thinkingBudget !== null) {
            $thinking['thinkingBudget'] = $thinkingBudget;
        }
        if ($includeThoughts !== null) {
            $thinking['includeThoughts'] = $includeThoughts;
        }
        if ($thinking !== []) {
            $generationConfig['thinkingConfig'] = $thinking;
        }

        $bodyArr = [
            'system_instruction' => ['parts' => [['text' => $systemText]]],
            'contents'           => [['parts' => [['text' => $contentText]]]],
            'generationConfig'   => $generationConfig,
        ];

        // Normalize return shape and types
        $url = \is_string($config['endpoint'] ?? null) ? (string) $config['endpoint'] : '';
        $headers = [];
        $req = \is_array($config['requirements'] ?? null) ? $config['requirements'] : null;
        $headersSrc = \is_array($req['headers'] ?? null) ? $req['headers'] : [];
        foreach ((array) $headersSrc as $h) {
            if (\is_string($h)) {
                $headers[] = $h;
            }
        }

        $body = \json_encode($bodyArr, JSON_UNESCAPED_UNICODE);

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
        if (!\is_array($data)) {
            throw new \RuntimeException("Invalid JSON response: {$responseBody}");
        }

        // Pull first candidate safely
        $candidate = null;
        $cands = $data['candidates'] ?? null;
        if (\is_array($cands) && isset($cands[0]) && \is_array($cands[0])) {
            $candidate = $cands[0];
        }
        if (!\is_array($candidate)) {
            throw new \RuntimeException("Gemini response has no candidates: {$responseBody}");
        }

        $finishReason = \is_string($candidate['finishReason'] ?? null)
            ? $candidate['finishReason']
            : '';

        if ($finishReason === 'MAX_TOKENS') {
            throw new \RuntimeException(
                "Gemini: translation was truncated—reached model's max output tokens.\n"
                . "Try increasing maxOutputTokens or splitting input into smaller chunks."
            );
        }

        if ($finishReason !== 'STOP') {
            throw new \RuntimeException(
                "Gemini: unexpected finishReason '{$finishReason}' — check the response output:\n{$responseBody}"
            );
        }

        $contentWrap = $candidate['content'] ?? null;
        if (!\is_array($contentWrap)) {
            throw new \RuntimeException("Gemini translation failed: missing content wrapper.");
        }

        $parts = $contentWrap['parts'] ?? null;
        if (!\is_array($parts)) {
            throw new \RuntimeException("Gemini translation failed: no text parts in response.");
        }

        $accumulated = '';
        foreach ($parts as $part) {
            if (\is_array($part) && \is_string($part['text'] ?? null)) {
                $accumulated .= $part['text'];
            }
        }

        // Extract between markers
        $pattern = '/'
            . \preg_quote(self::START, '/')
            . '(.*?)'
            . \preg_quote(self::END, '/')
            . '/s';

        if (!\preg_match($pattern, $accumulated, $matches)) {
            throw new \RuntimeException("Markers not found in Gemini response");
        }

        $text = \trim($matches[1]);

        // Usage metadata → array<string,mixed>|null
        $usage = null;
        $u = $data['usageMetadata'] ?? null;
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

        return [
            'text'  => $text,
            'usage' => $usage,
        ];
    }
}
