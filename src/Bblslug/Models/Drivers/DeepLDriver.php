<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;

/**
 * DeepL model driver: builds requests and parses responses for DeepL API.
 *
 * Uses form requests for text/HTML and JSON text arrays for JSON documents.
 */
class DeepLDriver implements ModelDriverInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config  Model config from registry (endpoint, requirements, defaults…)
     * @param string              $text    Input text (placeholders already applied)
     * @param array<string,mixed> $options Options:
     *     - model_type (string) Translation engine: quality_optimized, prefer_quality_optimized, latency_optimized.
     *     - dryRun    (bool)   Skip real HTTP call
     *     - format    (string) 'text', 'html' or 'json'
     *     - verbose   (bool)   Include debug logs
     *
     * @return array{
     *     url:     string,   // Full endpoint URL
     *     headers: string[], // HTTP headers to send
     *     body:    string,   // URL-encoded form body or JSON batch
     *     responseContext?: DeepLJsonDocument,
     *     localResult?: string
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

        $modelType = $options['model_type'] ?? ($defaults['model_type'] ?? null);
        if ($modelType !== null) {
            if (!in_array($modelType, ['quality_optimized', 'prefer_quality_optimized', 'latency_optimized'], true)) {
                throw new \InvalidArgumentException('Unsupported DeepL model_type');
            }
            $payload['model_type'] = $modelType;
        }

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

        $jsonDocument = null;

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
            case 'linedJson':
                $jsonDocument = new DeepLJsonDocument($text);
                $payload['text'] = $jsonDocument->getTexts();
                $payload['preserve_formatting'] = true;
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

        $request = [
            'url'     => $url,
            'headers' => $headers,
            'body'    => \http_build_query($payload),
        ];
        if ($jsonDocument !== null) {
            // JSON requests use DeepL's ordered text array, not HTML pseudo-tags.
            $request['headers'] = array_values(array_filter(
                $headers,
                static fn (string $header): bool => stripos($header, 'Content-Type:') !== 0
            ));
            $request['headers'][] = 'Content-Type: application/json';
            $request['body'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($request['body']) > 128 * 1024) {
                throw new \InvalidArgumentException('DeepL JSON request exceeds the 128 KiB request limit');
            }
            $request['responseContext'] = $jsonDocument;
            if ($jsonDocument->getTexts() === []) {
                $request['localResult'] = $text;
            }
        }
        return $request;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string,mixed> $config       Model config (unused here).
     * @param string              $responseBody Raw JSON response body.
     *
     * @param array<string,mixed> $options Request options, including responseContext from buildRequest.
     *
     * @return array{text:string, usage:array<string,mixed>|null}
     *
     * @throws \RuntimeException If the response format is unexpected.
     */
    public function parseResponse(array $config, string $responseBody, array $options = []): array
    {
        $data = \json_decode($responseBody, true);
        if (!\is_array($data)) {
            throw new \RuntimeException("Invalid JSON response: {$responseBody}");
        }

        $translations = $data['translations'] ?? null;
        if (!is_array($translations) || !array_is_list($translations) || $translations === []) {
            throw new \RuntimeException("DeepL translation failed: {$responseBody}");
        }
        $texts = [];
        foreach ($translations as $translation) {
            if (!is_array($translation) || !is_string($translation['text'] ?? null)) {
                throw new \RuntimeException("DeepL translation failed: {$responseBody}");
            }
            $texts[] = $translation['text'];
        }
        $document = $options['responseContext'] ?? null;
        if ($document instanceof DeepLJsonDocument) {
            $text = $document->restore($texts);
        } else {
            if (in_array($options['format'] ?? null, ['json', 'linedJson'], true)) {
                throw new \RuntimeException('DeepL JSON response requires its request responseContext');
            }
            if (count($texts) !== 1) {
                throw new \RuntimeException('Unexpected DeepL translation count without responseContext');
            }
            $text = $texts[0];
        }

        return [
            'text'  => $text,
            // DeepL API does not provide usage here
            'usage' => null,
        ];
    }
}
