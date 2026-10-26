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
     *     - json_transport (string) 'auto' (default), 'markers', or native JSON-object output.
     *     - maxTokens   (int)         Maximum tokens to generate.
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float)       Sampling temperature.
     *     - verbose     (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string,headers:string[],body:string,
     *     responseContext:array{html_fence_free?:bool,json_native?:bool}|null}
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

        $nativeJson = self::nativeRequest($config, $text, $options, $format);

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

        // Construct model URI for the native API. OpenAI-compatible requests
        // accept the same URI in the model field, but use a different payload.
        $modelUri = \str_starts_with($modelName, 'gpt://')
            ? $modelName
            : \sprintf('gpt://%s/%s', $folderId, $modelName);

        // Render system prompt
        $systemPrompt = Prompts::render(
            $promptKey,
            $nativeJson ? 'structured_json' : $format,
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
            ['role' => 'user',   'text' => $nativeJson ? $text : self::START . "\n{$text}\n" . self::END],
        ];

        $apiType = \is_string($config['api_type'] ?? null)
            ? \strtolower($config['api_type'])
            : 'foundation';
        if (!\in_array($apiType, ['foundation', 'chat_completions'], true)) {
            throw new \InvalidArgumentException("Unsupported Yandex API type '{$apiType}'");
        }

        // Prepare payload
        if ($apiType === 'chat_completions') {
            $payload = [
                'model'       => $modelUri,
                'messages'    => array_map(
                    static fn (array $message): array => [
                        'role' => $message['role'],
                        'content' => $message['text'],
                    ],
                    $messages
                ),
                'temperature' => (float) $temperature,
                'stream'      => false,
            ];
            if ($nativeJson) {
                $payload['response_format'] = ['type' => 'json_object'];
            }
            if ($maxTokens > 0) {
                $payload['max_tokens'] = $maxTokens;
            }
        } else {
            $payload = [
                'modelUri'          => $modelUri,
                'completionOptions' => [
                    'stream'      => false,
                    'temperature' => (float) $temperature,
                    'maxTokens'   => $maxTokens,
                ],
                'messages'          => $messages,
            ];
            if ($nativeJson) {
                $payload['jsonObject'] = true;
            }
        }

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
        if ($apiType === 'chat_completions') {
            $headers[] = "x-project: {$folderId}";
        }
        $body = \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $responseContext = \in_array($format, ['json', 'linedJson'], true) ? ['json_native' => $nativeJson] : null;
        if ($format === 'html') {
            $responseContext = [
                'html_fence_free' => !self::hasFence(\html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            ];
        }

        return [
            'url'     => $url,
            'headers' => $headers,
            'body'    => $body,
            'responseContext' => $responseContext,
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
     * @param array<string,mixed> $options Request options, including the request responseContext.
     *
     * @return array{text:string, usage:array<string,mixed>|null}
     *
     * @throws \RuntimeException If the response is malformed or markers are missing.
     */
    public function parseResponse(array $config, string $responseBody, array $options = []): array
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
        // OpenAI-compatible Chat Completions response shape
        $choices = $data['choices'] ?? null;
        if (\is_array($choices) && isset($choices[0]) && \is_array($choices[0])) {
            self::assertCompleted($choices[0], 'finish_reason', 'stop');
            $message = \is_array($choices[0]['message'] ?? null) ? $choices[0]['message'] : null;
            if ($message !== null && ($message['refusal'] ?? '') !== '') {
                throw new \RuntimeException('Yandex: translation was refused by the model');
            }
            if ($message !== null && \is_string($message['content'] ?? null)) {
                $content = $message['content'];
            }
        }

        // Newer Yandex response shape
        $result = \is_array($data['result'] ?? null) ? $data['result'] : null;
        if ($content === null && $result !== null) {
            $alts = \is_array($result['alternatives'] ?? null) ? $result['alternatives'] : null;
            if ($alts !== null && isset($alts[0]) && \is_array($alts[0])) {
                self::assertCompleted($alts[0], 'status', 'ALTERNATIVE_STATUS_FINAL');
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
                self::assertCompleted($comps[0], 'finish_reason', 'stop');
                $t = $comps[0]['text'] ?? null;
                if (\is_string($t)) {
                    $content = $t;
                }
            }
        }

        if (!\is_string($content)) {
            throw new \RuntimeException("Yandex translation failed: {$responseBody}");
        }

        $transport = self::jsonTransport($options);
        $context = $options['responseContext'] ?? null;
        $hasNativeContext = \is_array($context) && \is_bool($context['json_native'] ?? null);
        if (!$hasNativeContext && $transport === 'native') {
            $defaults = \is_array($config['defaults'] ?? null) ? $config['defaults'] : [];
            $format = $options['format'] ?? ($defaults['format'] ?? 'text');
            if (!\in_array($format, ['json', 'linedJson'], true)) {
                throw new \InvalidArgumentException('Yandex native JSON requires json or linedJson format');
            }
        }
        $nativeJson = $hasNativeContext
            ? $context['json_native'] : $transport === 'native';
        if ($nativeJson) {
            // JSON mode is a wire contract, independently of optional container checks.
            $text = \trim($content);
            try {
                $document = \json_decode($text, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Yandex native JSON response is not valid JSON', 0, $exception);
            }
            if (!$document instanceof \stdClass) {
                throw new \RuntimeException('Yandex native JSON response requires an object root');
            }
        } else {
            $pattern = '/'
                . \preg_quote(self::START, '/')
                . '(.*?)'
                . \preg_quote(self::END, '/')
                . '/s';
            if (!preg_match($pattern, $content, $matches)) {
                throw new \RuntimeException("Markers not found in Yandex response");
            }
            $text = trim($matches[1]);
            if (\is_array($context) && ($context['html_fence_free'] ?? false) === true) {
                $text = self::removeAddedHtmlFence($text);
            }
        }

        // Usage statistics
        $usage = null;
        $ru = $result['usage'] ?? ($data['usage'] ?? null);
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

    /** @param array<string,mixed> $options */
    private static function jsonTransport(array $options): string
    {
        $transport = $options['json_transport'] ?? 'auto';
        // An explicit null is an invalid override, not an absent setting.
        if (\array_key_exists('json_transport', $options)) {
            $transport = $options['json_transport'];
        }
        if (!\in_array($transport, ['auto', 'markers', 'native'], true)) {
            throw new \InvalidArgumentException('Yandex json_transport must be auto, markers, or native');
        }
        return $transport;
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $options
     */
    private static function nativeRequest(array $config, string $text, array $options, string $format): bool
    {
        $transport = self::jsonTransport($options);
        if ($format === 'structured_json' || \array_key_exists('response_schema', $options)) {
            throw new \InvalidArgumentException(
                'Yandex JSON object mode does not support response_schema or structured_json'
            );
        }
        $jsonFormat = \in_array($format, ['json', 'linedJson'], true);
        if ($transport === 'native' && !$jsonFormat) {
            throw new \InvalidArgumentException('Yandex native JSON requires json or linedJson format');
        }
        $capabilities = \is_array($config['capabilities'] ?? null) ? $config['capabilities'] : [];
        $promptKey = \is_string($options['promptKey'] ?? null) ? $options['promptKey'] : 'translator';
        $eligible = $jsonFormat && $promptKey === 'translator'
            && ($capabilities['native_json'] ?? false) === true
            && \in_array('structured_json', Prompts::list()[$promptKey]['formats'] ?? [], true);
        if ($transport === 'markers' || ($transport === 'auto' && !$eligible)) {
            return false;
        }
        try {
            $source = \json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if ($transport === 'auto') {
                return false;
            }
            throw new \InvalidArgumentException('Yandex native JSON requires a valid object source', 0, $exception);
        }
        if (!$source instanceof \stdClass) {
            if ($transport === 'auto') {
                return false;
            }
            throw new \InvalidArgumentException('Yandex native JSON requires an object source');
        }
        return true;
    }

    private static function hasFence(string $text): bool
    {
        return \str_contains($text, '```') || \str_contains($text, '~~~');
    }

    /** Remove only the opening-only transport artifact observed in Yandex HTML responses. */
    private static function removeAddedHtmlFence(string $text): string
    {
        if (!self::hasFence($text)) {
            return $text;
        }
        if (\preg_match('/\A```[\t ]*(?:html[\t ]*)?\r?\n/i', $text, $opening) === 1) {
            $body = \substr($text, \strlen($opening[0]));
            if (!self::hasFence($body)) {
                return \trim($body);
            }
        }
        // Paired, nested, inline, or unfamiliar fences require an explicit failure, not guessed cleanup.
        throw new \RuntimeException('Yandex HTML response added an unsupported Markdown fence');
    }

    /** @param array<array-key,mixed> $alternative */
    private static function assertCompleted(array $alternative, string $field, string $completed): void
    {
        // Legacy responses may omit completion metadata; explicit failure must never be ignored.
        if (!\array_key_exists($field, $alternative) || $alternative[$field] === $completed) {
            return;
        }
        $status = $alternative[$field];
        if (\in_array($status, ['ALTERNATIVE_STATUS_TRUNCATED_FINAL', 'length'], true)) {
            throw new \RuntimeException(
                'Yandex: translation was truncated; increase maxTokens or split the input into smaller chunks'
            );
        }
        if (\in_array($status, ['ALTERNATIVE_STATUS_CONTENT_FILTER', 'content_filter'], true)) {
            throw new \RuntimeException('Yandex: translation was blocked by the content filter');
        }
        if (\in_array($status, ['ALTERNATIVE_STATUS_TOOL_CALLS', 'tool_calls', 'function_call'], true)) {
            throw new \RuntimeException('Yandex: a tool call was returned instead of a completed translation');
        }
        throw new \RuntimeException('Yandex: translation did not finish successfully');
    }
}
