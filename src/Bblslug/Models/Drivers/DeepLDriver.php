<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

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
        $defaults = \is_array($config['defaults'] ?? null) ? $config['defaults'] : [];

        // Base payload fields
        $payloadText = $text;
        $targetLang  = \is_string($defaults['target_lang'] ?? null)
            ? $defaults['target_lang']
            : 'EN';
        $formality   = \is_string($defaults['formality'] ?? null)
            ? $defaults['formality']
            : 'prefer_more';

        // Core payload
        $payload = [
            'text'        => $payloadText,
            'target_lang' => $targetLang,
            'formality'   => $formality,
        ];

        // Optional overrides
        $src = $defaults['source_lang'] ?? null;
        if (\is_string($src) && $src !== '') {
            $payload['source_lang'] = $src;
        }
        $ctx = $defaults['context'] ?? null;
        if (\is_scalar($ctx) || ($ctx instanceof \Stringable)) {
            $ctxStr = \trim((string) $ctx);
            if ($ctxStr !== '') {
                $payload['context'] = $ctxStr;
            }
        }

        // Format-specific adjustments
        $format = \is_string($options['format'] ?? null)
            ? $options['format']
            : (\is_string($defaults['format'] ?? null) ? $defaults['format'] : 'text');

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
                $protectedText = \strtr($payload['text'], $protect);
                $payload['text'] = $protectedText;
                break;

            case 'text':
            default:
                // Default text behavior: nothing extra
                break;
        }

        // Normalize return shape and types (url/headers)
        $url = \is_string($config['endpoint'] ?? null) ? (string) $config['endpoint'] : '';
        $headers = [];
        $req = \is_array($config['requirements'] ?? null) ? $config['requirements'] : null;
        $headersSrc = \is_array($req['headers'] ?? null) ? $req['headers'] : [];
        foreach ((array) $headersSrc as $h) {
            if (\is_string($h)) {
                $headers[] = $h;
            }
        }

        return [
            'url'     => $url,
            'headers' => $headers,
            'body'    => \http_build_query($payload),
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
        $data = \json_decode($responseBody, true);
        if (!\is_array($data)) {
            throw new \RuntimeException("Invalid JSON response: {$responseBody}");
        }

        // translations[0]['text'] safe extraction
        $translations = \is_array($data['translations'] ?? null) ? $data['translations'] : null;
        if ($translations === null || !isset($translations[0]) || !\is_array($translations[0])) {
            throw new \RuntimeException("DeepL translation failed: {$responseBody}");
        }
        $first = $translations[0];
        $txt = $first['text'] ?? null;
        if (!\is_string($txt)) {
            throw new \RuntimeException("DeepL translation failed: {$responseBody}");
        }
        $text = $txt;

        // Restore any JSON pseudo-tags back to original characters
        $reverse = [
            '<jlc/>' => '{',
            '<jrc/>' => '}',
            '<jlb/>' => '[',
            '<jrb/>' => ']',
            '<jcol/>' => ':',
            '<jcomma/>' => ',',
            '<jqt/>' => '"',
        ];
        $text = \strtr($text, $reverse);

        return [
            'text'  => $text,
            // DeepL API does not provide usage here
            'usage' => null,
        ];
    }
}
