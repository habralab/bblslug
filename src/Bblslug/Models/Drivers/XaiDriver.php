<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * xAI Grok translation driver.
 *
 * Builds requests and parses responses for the xAI (Grok) Chat Completions API.
 */
class XaiDriver implements ModelDriverInterface
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
     *     - promptKey   (string)      Key of the prompt template in prompts.yaml.
     *     - temperature (float|null)  Sampling temperature; null omits it.
     *     - reasoning_effort (string) Reasoning effort for supported Grok models.
     *     - max_completion_tokens (int) Optional output budget.
     *     - response_schema (array) Native JSON Schema for structured output.
     *     - response_schema_name (string) Schema name (default: anonymous).
     *     - strict (bool) Enforce the native schema (default: true).
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
                : 'text');

        $model = \is_string($defaults['model'] ?? null)
            ? $defaults['model']
            : throw new \RuntimeException('Missing xAI model name');

        $promptKey = \is_string($options['promptKey'] ?? null)
            ? $options['promptKey']
            : 'translator';

        $sourceLang = \is_string($defaults['source_lang'] ?? null)
            ? $defaults['source_lang']
            : 'auto';

        $targetLang = \is_string($defaults['target_lang'] ?? null)
            ? $defaults['target_lang']
            : 'EN';

        $settings = array_replace($defaults, $options);
        $tempRaw = \array_key_exists('temperature', $settings) ? $settings['temperature'] : 0.0;
        $temperature = $tempRaw === null ? null : (\is_numeric($tempRaw) ? (float)$tempRaw : 0.0);

        $hasSchema = \array_key_exists('response_schema', $settings);
        $schema = $settings['response_schema'] ?? null;
        if ($hasSchema && (!\is_array($schema) || $schema === [] || \array_is_list($schema))) {
            throw new \InvalidArgumentException('response_schema must be a non-empty associative array');
        }
        if ($format === 'structured_json' && !$hasSchema) {
            throw new \InvalidArgumentException('format=structured_json requires response_schema');
        }
        if ($hasSchema && !\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
            throw new \InvalidArgumentException('response_schema requires format json, structured_json, or linedJson');
        }
        if ($hasSchema && ($options['json_transport'] ?? 'auto') === 'markers') {
            throw new \InvalidArgumentException('response_schema conflicts with json_transport=markers');
        }
        $schemaName = $settings['response_schema_name'] ?? 'anonymous';
        $strict = $settings['strict'] ?? true;
        if ($hasSchema) {
            if (!\is_string($schemaName) || !\preg_match('/^[A-Za-z0-9_-]{1,64}$/', $schemaName)) {
                throw new \InvalidArgumentException('response_schema_name must match [A-Za-z0-9_-]{1,64}');
            }
            if (!\is_bool($strict)) {
                throw new \InvalidArgumentException('strict must be a boolean');
            }
        }

        // Render system prompt
        $systemPrompt = Prompts::render(
            $promptKey,
            $hasSchema ? 'structured_json' : $format,
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
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $hasSchema ? $text : self::START . "\n{$text}\n" . self::END],
        ];

        $payload = [
            'model'       => $model,
        ];
        $apiType = \is_string($config['api_type'] ?? null)
            ? \strtolower($config['api_type'])
            : 'chat_completions';
        if (!\in_array($apiType, ['chat_completions', 'responses'], true)) {
            throw new \InvalidArgumentException("Unsupported xAI API type '{$apiType}'");
        }
        if ($apiType === 'responses') {
            $payload['input'] = $messages;
        } else {
            $payload['messages'] = $messages;
            $payload['stream'] = false;
        }
        if ($hasSchema) {
            $nativeFormat = ['name' => $schemaName, 'strict' => $strict, 'schema' => $schema];
            if ($apiType === 'responses') {
                $payload['text'] = ['format' => ['type' => 'json_schema'] + $nativeFormat];
            } else {
                $payload['response_format'] = ['type' => 'json_schema', 'json_schema' => $nativeFormat];
            }
        }
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }
        if (\is_string($settings['reasoning_effort'] ?? null)) {
            if ($apiType === 'responses') {
                $payload['reasoning'] = ['effort' => $settings['reasoning_effort']];
            } else {
                $payload['reasoning_effort'] = $settings['reasoning_effort'];
            }
        }
        if (isset($settings['max_completion_tokens'])) {
            $budget = $settings['max_completion_tokens'];
            if (
                (!\is_int($budget) && !\is_string($budget))
                || filter_var($budget, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            ) {
                throw new \InvalidArgumentException('max_completion_tokens must be a positive integer');
            }
            $payload[$apiType === 'responses' ? 'max_output_tokens' : 'max_tokens'] = (int)$budget;
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
     * @param array<string,mixed> $options Request options (unused).
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

        // xAI error payload (model not found, auth, etc.)
        if (\is_array($data['error'] ?? null) || \is_string($data['error'] ?? null)) {
            $code = \is_string($data['code'] ?? null) ? $data['code'] : 'unknown_error';
            $err  = $data['error'];
            $msg  = \is_string($err)
                ? $err
                : (\json_encode($err, JSON_UNESCAPED_UNICODE) ?: 'unknown error');
            throw new \RuntimeException("Grok API error [{$code}]: {$msg}");
        }

        $isResponses = \array_key_exists('output', $data);
        if ($isResponses && \array_key_exists('status', $data) && $data['status'] !== 'completed') {
            $status = \is_string($data['status']) ? $data['status'] : \get_debug_type($data['status']);
            throw new \RuntimeException("Grok Responses API did not complete the request: {$status}");
        }

        // Extract the first choice safely
        $first = null;
        $choices = $data['choices'] ?? null;
        if (\is_array($choices) && isset($choices[0]) && \is_array($choices[0])) {
            $first = $choices[0];
        }

        $this->assertCompletedChoice($first);

        // Normal completion path: content string at choices[0].message.content
        $content = null;
        if (\is_array($first) && \is_array($first['message'] ?? null)) {
            if (\is_string($first['message']['refusal'] ?? null)) {
                throw new \RuntimeException('Grok returned a refusal');
            }
            $content = $first['message']['content'] ?? null;
        }
        if (!\is_string($content)) {
            $output = $data['output'] ?? null;
            if (\is_array($output)) {
                $outputText = '';
                foreach ($output as $item) {
                    if (!\is_array($item)) {
                        continue;
                    }
                    if (
                        ($item['type'] ?? null) === 'refusal'
                        || \is_string($item['refusal'] ?? null)
                    ) {
                        throw new \RuntimeException('Grok Responses API returned a refusal');
                    }
                    if (($item['type'] ?? null) !== 'message') {
                        continue;
                    }
                    $parts = $item['content'] ?? null;
                    if (!\is_array($parts)) {
                        continue;
                    }
                    foreach ($parts as $part) {
                        if (!\is_array($part)) {
                            continue;
                        }
                        if (
                            ($part['type'] ?? null) === 'refusal'
                            || \is_string($part['refusal'] ?? null)
                        ) {
                            throw new \RuntimeException('Grok Responses API returned a refusal');
                        }
                        if (
                            ($part['type'] ?? null) === 'output_text'
                            && \is_string($part['text'] ?? null)
                        ) {
                            $outputText .= $part['text'];
                        }
                    }
                }
                if ($outputText !== '') {
                    $content = $outputText;
                }
            }
        }
        if (!\is_string($content)) {
            throw new \RuntimeException("Grok translation failed: {$responseBody}");
        }

        $defaults = \is_array($config['defaults'] ?? null) ? $config['defaults'] : [];
        $settings = \array_replace($defaults, $options);
        if (\array_key_exists('response_schema', $settings) || ($settings['format'] ?? null) === 'structured_json') {
            $text = trim($content);
            try {
                json_decode($text, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Grok native JSON response is not valid JSON', 0, $exception);
            }
        } else {
            $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';
            if (!preg_match($pattern, $content, $matches)) {
                throw new \RuntimeException('Markers not found in Grok response');
            }
            $text = trim($matches[1]);
        }
        // Usage statistics normalized to array<string,mixed>|null
        $usage = null;
        $u = $data['usage'] ?? null;
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

        return ['text' => $text, 'usage' => $usage];
    }

    /** @param array<array-key,mixed>|null $choice */
    private function assertCompletedChoice(?array $choice): void
    {
        if ($choice === null || !\array_key_exists('finish_reason', $choice)) {
            return;
        }
        $finishReason = $choice['finish_reason'];
        if ($finishReason === 'length') {
            throw new \RuntimeException(
                "Grok: translation was truncated (finish_reason=length) — "
                . "increase max_completion_tokens or split input."
            );
        }
        if ($finishReason !== 'stop') {
            $actual = \is_string($finishReason) ? $finishReason : \get_debug_type($finishReason);
            throw new \RuntimeException("Grok: translation did not finish successfully (finish_reason={$actual})");
        }
    }
}
