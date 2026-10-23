<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;

/**
 * DeepL model driver: builds requests and parses responses for DeepL API.
 *
 * Uses form requests for text/HTML and native document translation for JSON.
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
     *     - format    (string) 'text', 'html', 'json' or 'linedJson'
     *     - json_transport (string) 'document' (default) or explicit 'text_batch'.
     *     - document_timeout (int) Upload timeout and polling/download deadline, in seconds.
     *     - verbose   (bool)   Include debug logs
     *
     * @return array{
     *     url:     string,   // Full endpoint URL
     *     headers: string[], // HTTP headers to send
     *     body:    string,   // URL-encoded form, multipart upload or JSON batch
     *     responseContext?: DeepLJsonDocument|DeepLHtmlPlaceholders|DeepLDocumentWorkflow,
     *     workflow?: DeepLDocumentWorkflow,
     *     timeoutSeconds?: int,
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
        $htmlPlaceholders = null;

        // Format-specific adjustments
        $format = \is_string($options['format'] ?? null)
            ? $options['format']
            : (\is_string($defaults['format'] ?? null) ? $defaults['format'] : 'text');

        switch ($format) {
            case 'html':
                $htmlPlaceholders = new DeepLHtmlPlaceholders($text);
                $payload['text'] = $htmlPlaceholders->getText();
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
            $transport = $options['json_transport'] ?? 'document';
            if (!in_array($transport, ['document', 'text_batch'], true)) {
                throw new \InvalidArgumentException('Unsupported DeepL json_transport');
            }
            if ($transport === 'document') {
                return $this->documentRequest($text, $payload, $request, $jsonDocument, $options);
            }
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
        if ($htmlPlaceholders !== null && $htmlPlaceholders->getText() !== $text) {
            if (strlen($request['body']) > 128 * 1024) {
                throw new \InvalidArgumentException('DeepL HTML request exceeds 128 KiB after placeholder protection');
            }
            $request['responseContext'] = $htmlPlaceholders;
        }
        return $request;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array{url:string,headers:array<int,string>,body:string} $request
     * @param array<string,mixed> $options
     * @return array{url:string,headers:array<int,string>,body:string,responseContext:DeepLDocumentWorkflow,workflow:DeepLDocumentWorkflow,timeoutSeconds:int,localResult?:string}
     */
    private function documentRequest(
        string $text,
        array $payload,
        array $request,
        DeepLJsonDocument $json,
        array $options
    ): array {
        if (strlen($text) > 1_000_000) {
            throw new \InvalidArgumentException('DeepL JSON document exceeds the 1 MB upload limit');
        }
        $timeout = $options['document_timeout'] ?? 120;
        if (!is_int($timeout) || $timeout < 1) {
            throw new \InvalidArgumentException('DeepL document_timeout must be a positive integer');
        }
        if (isset($payload['context']) || isset($payload['model_type'])) {
            throw new \InvalidArgumentException(
                'DeepL document translation does not support context or model_type; use json_transport=text_batch'
            );
        }
        $url = preg_replace('~/translate$~', '/document', $request['url']);
        if ($url === null || $url === $request['url']) {
            throw new \InvalidArgumentException('DeepL document translation requires a /translate endpoint');
        }
        do {
            $boundary = 'bblslug-' . bin2hex(random_bytes(18));
        } while (str_contains($text, $boundary));
        $body = '';
        foreach (['source_lang', 'target_lang', 'formality'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$field}\"\r\n\r\n"
                    . $payload[$field] . "\r\n";
            }
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"translation.json\"\r\n"
            . "Content-Type: application/json\r\n\r\n" . $text . "\r\n--{$boundary}--\r\n";
        $headers = array_values(array_filter(
            $request['headers'],
            static fn (string $header): bool => stripos($header, 'Content-Type:') !== 0
        ));
        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
        $workflow = new DeepLDocumentWorkflow($url, $timeout);
        $result = [
            'url' => $url, 'body' => $body, 'headers' => $headers,
            'responseContext' => $workflow, 'workflow' => $workflow, 'timeoutSeconds' => $timeout,
        ];
        if ($json->getTexts() === []) {
            $result['localResult'] = $text;
        }
        return $result;
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
        $document = $options['responseContext'] ?? null;
        if ($document instanceof DeepLDocumentWorkflow) {
            if (!$document->isComplete()) {
                throw new \RuntimeException('DeepL document response requires its completed workflow');
            }
            try {
                json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new \RuntimeException('DeepL document response is not valid JSON');
            }
            return ['text' => $responseBody, 'usage' => null];
        }
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
            $text = $document instanceof DeepLHtmlPlaceholders
                ? $document->restore($texts[0])
                : $texts[0];
        }

        return [
            'text'  => $text,
            // DeepL API does not provide usage here
            'usage' => null,
        ];
    }
}
