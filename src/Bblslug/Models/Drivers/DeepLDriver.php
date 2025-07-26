<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;

/**
 * DeepL model driver: builds requests and parses responses for DeepL API.
 */
class DeepLDriver implements ModelDriverInterface
{
    /**
     * Construct the HTTP request parameters for DeepL.
     *
     * @param array<string,mixed> $config  Model config from registry (endpoint, requirements, defaults…)
     * @param string              $text    Input text (placeholders already applied)
     * @param array<string,mixed> $options Options: [
     *     'dryRun' => bool,
     *     'format' => 'text'|'html',
     *     'verbose'=> bool
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
        // Start payload with core fields
        $defaults = $config['defaults'] ?? [];
        $payload = [
            'text'        => $text,
            'target_lang' => $defaults['target_lang'] ?? 'EN',
            'formality'   => $defaults['formality']   ?? 'prefer_more',
        ];

        // Optional overrides (only if set)
        if (!empty($defaults['source_lang'])) {
            $payload['source_lang'] = $defaults['source_lang'];
        }
        if (!empty($defaults['context'])) {
            $payload['context'] = $defaults['context'];
        }

        // HTML-specific parameters
        if ($options['format'] === 'html') {
            $payload['tag_handling']      = 'html';
            $payload['preserve_formatting'] = '1';
            $payload['outline_detection']   = '1';
        }

        return [
            'url'     => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body'    => http_build_query($payload),
        ];
    }

    /**
     * Extract the translated text from DeepL's JSON response.
     *
     * @param array<string,mixed> $config       Model config (unused here).
     * @param string              $responseBody Raw JSON response body.
     *
     * @return string Translated text.
     *
     * @throws RuntimeException If the response format is unexpected.
     */
    public function parseResponse(array $config, string $responseBody): string
    {
        $data = json_decode($responseBody, true);
        if (
            !isset($data['translations']) ||
            !isset($data['translations'][0]['text'])
        ) {
            throw new \RuntimeException("DeepL translation failed: {$responseBody}");
        }
        return $data['translations'][0]['text'];
    }
}
