<?php

declare(strict_types=1);

namespace Bblslug\Models\Drivers;

use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\Prompts;

/**
 * OpenAI GPT translation driver.
 *
 * Builds requests and parses responses for OpenAI text-generation APIs.
 */
class OpenAiDriver implements ModelDriverInterface
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
     *     - reasoning_effort (string) Reasoning effort supported by the selected model.
     *     - max_completion_tokens (int) Optional output budget, including reasoning tokens.
     *     - temperature (float|null) Sampling temperature; null omits the parameter.
     *     - verbose     (bool)        Include debug logs (ignored here).
     *
     * @return array{url:string, headers:string[], body:string}
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
            : throw new \RuntimeException('Missing OpenAI model name');

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

        // Render system prompt from YAML templates
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
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => self::START . "\n{$text}\n" . self::END],
        ];

        $apiType = \is_string($config['api_type'] ?? null)
            ? \strtolower($config['api_type'])
            : 'chat_completions';
        if (!\in_array($apiType, ['chat_completions', 'responses', 'completions'], true)) {
            throw new \InvalidArgumentException("Unsupported OpenAI API type '{$apiType}'");
        }

        $payload = match ($apiType) {
            'responses' => [
                'model' => $model,
                'input' => $messages,
            ],
            'completions' => [
                'model' => $model,
                'prompt' => $systemPrompt . "\n\n" . self::START . "\n{$text}\n" . self::END,
            ],
            default => [
                'model' => $model,
                'messages' => $messages,
            ],
        };
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
            $budgetKey = match ($apiType) {
                'responses' => 'max_output_tokens',
                'completions' => 'max_tokens',
                default => 'max_completion_tokens',
            };
            $payload[$budgetKey] = (int)$budget;
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
        /** @var array<string,mixed> $data */

        if (($data['status'] ?? null) === 'incomplete' || ($data['status'] ?? null) === 'failed') {
            $status = (string)$data['status'];
            $reason = \is_array($data['incomplete_details'] ?? null)
                ? ($data['incomplete_details']['reason'] ?? null)
                : null;
            $detail = \is_string($reason) ? $reason : '';
            throw new \RuntimeException("OpenAI response is {$status}" . ($detail !== '' ? ": {$detail}" : ''));
        }
        if (\is_array($data['error'] ?? null) && \is_string($data['error']['message'] ?? null)) {
            throw new \RuntimeException('OpenAI API error: ' . $data['error']['message']);
        }
        if (isset($data['incomplete_details'])) {
            throw new \RuntimeException("OpenAI response did not complete: {$responseBody}");
        }

        $refusal = $this->extractRefusal($data);
        if ($refusal !== null) {
            throw new \RuntimeException('OpenAI response refused: ' . $refusal);
        }

        $contentRaw = $this->extractText($data);

        $first = null;
        $choices = $data['choices'] ?? null;
        if (\is_array($choices) && isset($choices[0]) && \is_array($choices[0])) {
            $first = $choices[0];
        }

        // If OpenAI cut output by tokens, fail with a clear message before marker search
        $finishReason = null;
        if (\is_array($first) && \is_string($first['finish_reason'] ?? null)) {
            $finishReason = $first['finish_reason'];
        }
        if ($finishReason === 'length') {
            throw new \RuntimeException(
                "OpenAI: translation was truncated (finish_reason=length) — "
                . "increase max_tokens or split input."
            );
        }

        // Validate response structure
        $content = $contentRaw;
        if ($content === '') {
            throw new \RuntimeException("OpenAI translation failed: {$responseBody}");
        }

        // Extract between markers
        $pattern = '/' . preg_quote(self::START, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s';
        if (!preg_match($pattern, $content, $matches)) {
            throw new \RuntimeException("Markers not found in OpenAI response");
        }
        $text = trim($matches[1]);

        // Usage statistics
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

    /** @param array<string,mixed> $data */
    private function extractText(array $data): string
    {
        if (\is_string($data['output_text'] ?? null)) {
            return $data['output_text'];
        }

        $output = $data['output'] ?? null;
        if (\is_array($output)) {
            $parts = '';
            foreach ($output as $item) {
                if (!\is_array($item) || !\is_array($item['content'] ?? null)) {
                    continue;
                }
                foreach ($item['content'] as $part) {
                    if (\is_array($part) && \is_string($part['text'] ?? null)) {
                        $parts .= $part['text'];
                    }
                }
            }
            if ($parts !== '') {
                return $parts;
            }
        }

        $choices = $data['choices'] ?? null;
        if (\is_array($choices) && isset($choices[0]) && \is_array($choices[0])) {
            $choice = $choices[0];
            if (\is_string($choice['text'] ?? null)) {
                return $choice['text'];
            }
            if (\is_array($choice['message'] ?? null) && \is_string($choice['message']['content'] ?? null)) {
                return $choice['message']['content'];
            }
        }

        return '';
    }

    /** @param array<string,mixed> $data */
    private function extractRefusal(array $data): ?string
    {
        $output = $data['output'] ?? null;
        if (!\is_array($output)) {
            return null;
        }
        foreach ($output as $item) {
            if (!\is_array($item) || !\is_array($item['content'] ?? null)) {
                continue;
            }
            foreach ($item['content'] as $part) {
                if (\is_array($part) && \is_string($part['refusal'] ?? null)) {
                    return $part['refusal'];
                }
            }
        }
        return null;
    }
}
