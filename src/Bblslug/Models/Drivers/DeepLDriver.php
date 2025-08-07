<?php

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;

/**
 * DeepL model driver: builds requests and parses responses for DeepL API.
 *
 * Builds HTTP params in x-www-form-urlencoded format.
 */
class DeepLDriver implements ModelDriverInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config  Model config from registry (endpoint, requirements, defaults…)
     * @param string              $text    Input text (placeholders already applied)
     * @param array<string,mixed> $options Options:
     *     - dryRun    (bool)   Skip real HTTP call
     *     - format    (string) 'text', 'html' or 'json'
     *     - verbose   (bool)   Include debug logs
     *
     * @return array{
     *     url:     string,   // Full endpoint URL
     *     headers: string[], // HTTP headers to send
     *     body:    string    // URL-encoded form body
     * }
     */
    public function buildRequest(array $config, string $text, array $options): array
    {
        $defaults = $config['defaults'] ?? [];

        // Core payload
        $payload = [
            'text' => $text,
            'target_lang' => $defaults['target_lang'] ?? 'EN',
            'formality' => $defaults['formality'] ?? 'prefer_more',
        ];

        // Optional overrides
        if (!empty($defaults['source_lang'])) {
            $payload['source_lang'] = $defaults['source_lang'];
        }
        if (!empty($defaults['context'])) {
            $payload['context'] = $defaults['context'];
        }

        // Format-specific adjustments
        $format = $options['format'] ?? $defaults['format'] ?? 'text';

        switch ($format) {
            case 'html':
                $payload['tag_handling'] = 'html';
                $payload['preserve_formatting'] = '1';
                $payload['outline_detection'] = '1';
                break;

            case 'json':
                $payload['tag_handling'] = 'html';
                $payload['preserve_formatting'] = '1';
                $payload['outline_detection'] = '1';
                $protect = [
                    '{'   => '<jlc/>',
                    '}'   => '<jrc/>',
                    '['   => '<jlb/>',
                    ']'   => '<jrb/>',
                    ':'   => '<jcol/>',
                    ','   => '<jcomma/>',
                    '"'   => '<jqt/>',
                ];
                $protectedText = strtr($payload['text'], $protect);
                $payload['text'] = $protectedText;
                break;

            case 'text':
            default:
                // Default text behavior: nothing extra
                break;
        }

        return [
            'url' => $config['endpoint'],
            'headers' => $config['requirements']['headers'] ?? [],
            'body' => http_build_query($payload),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config       Model config (unused here).
     * @param string              $responseBody Raw JSON response body.
     *
     * @return array{text:string, usage:array<string,mixed>|null}
     *
     * @throws \RuntimeException If the response format is unexpected.
     */
    public function parseResponse(array $config, string $responseBody): array
    {
        $data = json_decode($responseBody, true);
        if (
            !isset($data['translations']) ||
            !isset($data['translations'][0]['text'])
        ) {
            throw new \RuntimeException("DeepL translation failed: {$responseBody}");
        }

        $text = $data['translations'][0]['text'];

        // Restore any JSON pseudo-tags back to original characters
        $reverse = [
            '<jlc/>'    => '{',
            '<jrc/>'    => '}',
            '<jlb/>'    => '[',
            '<jrb/>'    => ']',
            '<jcol/>'   => ':',
            '<jcomma/>' => ',',
            '<jqt/>'    => '"',
        ];
        $text = strtr($text, $reverse);

        return [
            'text'  => $text,
            'usage' => null,  // DeepL API does not provide token usage
        ];
    }
}
