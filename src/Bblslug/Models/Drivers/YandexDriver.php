<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * Yandex Foundation Models translation driver.
 *
 * Builds requests and parses responses for the Yandex Foundation Models Chat Completions API.
 */
class YandexDriver implements ModelDriverInterface
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
     *     - folder_id   (string)      Yandex folder ID (required).
     *     - maxTokens   (int)         Maximum tokens to generate.
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float)       Sampling temperature.
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

        $folderId = $options['folder_id'] ?? null;
        if (!\is_string($folderId) || $folderId === '') {
            throw new \RuntimeException('Missing Yandex folder_id in options');
        }

        $maxRaw = $options['maxTokens'] ?? ($defaults['max_tokens'] ?? 0);
        $maxTokens = (\is_int($maxRaw) || \is_numeric($maxRaw)) ? (int)$maxRaw : 0;

        $modelName = \is_string($defaults['model'] ?? null)
            ? $defaults['model']
            : throw new \RuntimeException('Missing Yandex model name');

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

        // Construct model URI.
        $modelUri = \sprintf('gpt://%s/%s', $folderId, $modelName);

        // Render system prompt
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
            ['role' => 'system', 'text' => $systemPrompt],
            ['role' => 'user',   'text' => self::START . "\n{$text}\n" . self::END],
        ];

        // Prepare payload
        $payload = [
            'modelUri'          => $modelUri,
            'completionOptions' => [
                'stream'      => false,
                'temperature' => (float) $temperature,
                'maxTokens'   => $maxTokens,
            ],
            'messages'          => $messages,
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

        // API-level errors
        if (\is_array($data['error'] ?? null)) {
            $error = $data['error'];
            $msg = \is_string($error['message'] ?? null)
                ? $error['message']
                : (\json_encode($error, JSON_UNESCAPED_UNICODE) ?: 'unknown error');
            $code = $error['httpCode'] ?? null;
            $status = \is_string($error['httpStatus'] ?? null) ? $error['httpStatus'] : null;

            // folder ID mismatch
            if (
                (\is_int($code) && $code === 400)
                && (\stripos($msg, 'folder ID') !== false)
            ) {
                throw new \RuntimeException("Yandex API folder-id mismatch: {$msg}");
            }

            // authentication failures
            if (
                (\is_int($code) && $code === 401)
                || ($status !== null && \stripos($status, 'Unauthorized') !== false)
            ) {
                throw new \RuntimeException("Yandex API authentication error: {$msg}");
            }

            // internal server errors
            if (\is_int($code) && $code === 500) {
                throw new \RuntimeException("Yandex API internal server error: {$msg}");
            }

            $codePart = \is_int($code) ? " (HTTP {$code})" : '';
            throw new \RuntimeException("Yandex API error{$codePart}: {$msg}");
        }

        // Extract translated text
        $content = null;
        // Newer Yandex response shape
        $result = \is_array($data['result'] ?? null) ? $data['result'] : null;
        if ($result !== null) {
            $alts = \is_array($result['alternatives'] ?? null) ? $result['alternatives'] : null;
            if ($alts !== null && isset($alts[0]) && \is_array($alts[0])) {
                $msg = \is_array($alts[0]['message'] ?? null) ? $alts[0]['message'] : null;
                if ($msg !== null && \is_string($msg['text'] ?? null)) {
                    $content = $msg['text'];
                }
            }
        }
        // Legacy completions shape
        if ($content === null) {
            $comps = \is_array($data['completions'] ?? null) ? $data['completions'] : null;
            if ($comps !== null && isset($comps[0]) && \is_array($comps[0])) {
                $t = $comps[0]['text'] ?? null;
                if (\is_string($t)) {
                    $content = $t;
                }
            }
        }

        if (!\is_string($content)) {
            throw new \RuntimeException("Yandex translation failed: {$responseBody}");
        }

        // Extract between markers
        $pattern = '/'
            . \preg_quote(self::START, '/')
            . '(.*?)'
            . \preg_quote(self::END, '/')
            . '/s';

        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in Yandex response");
        }

        $text = trim($matches[1]);

        // Usage statistics
        $usage = null;
        $ru = $result['usage'] ?? null;
        if (\is_array($ru)) {
            /** @var array<string,mixed> $typed */
            $typed = [];
            foreach ($ru as $k => $v) {
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
