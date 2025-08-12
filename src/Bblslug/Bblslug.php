<?php

declare(strict_types=1);

namespace Bblslug;

use Bblslug\Filters\FilterManager;
use Bblslug\HttpClient;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\Prompts;
use Bblslug\Models\UsageExtractor;
use Bblslug\Validation\HtmlValidator;
use Bblslug\Validation\JsonValidator;
use Bblslug\Validation\Schema;
use Bblslug\Validation\TextLengthValidator;

class Bblslug
{
    /**
     * Return a hierarchical list of available models grouped by vendor.
     *
     * @return array<string, array<string, array<string,mixed>>>
     *     Vendor names as keys, each mapping to [modelKey => config] arrays.
     */
    public static function listModels(): array
    {
        $registry = new ModelRegistry();
        /** @var array<string, array<string,mixed>> $all */
        $all      = $registry->getAll();

        /** @var array<string, array<string, array<string,mixed>>> $grouped */
        $grouped = [];
        foreach ($all as $key => $config) {
            $vendor = (isset($config['vendor']) && \is_string($config['vendor']))
                ? $config['vendor']
                : 'other';
            if (!isset($grouped[$vendor])) {
                $grouped[$vendor] = [];
            }
            /** @var string $modelKey */
            $modelKey = (string) $key;
            $grouped[$vendor][$modelKey] = $config;
        }

        return $grouped;
    }

    /**
     * Return a list of available prompt templates, with formats and notes.
     *
     * @return array<string, array{formats:string[], notes:?string}>
     * @throws \RuntimeException If prompts.yaml is missing
     */
    public static function listPrompts(): array
    {
        return Prompts::list();
    }

    /**
     * Translate text, HTML or JSON via any registered model.
     *
     * @param string                $apiKey     API key for the model - mandatory.
     * @param string                $format     "text", "html" or "json" - mandatory.
     * @param string                $modelKey   Model ID (e.g. "deepl:pro") - mandatory.
     * @param string                $text       The source to be translated - mandatory.
     * @param string|null           $context    Optional context prompt.
     * @param bool                  $dryRun     If true: prepare placeholders only.
     * @param string[]              $filters    Placeholder filters to apply.
     * @param ?callable(string,string=):void $onFeedback Optional feedback callback that
     *        receives (message, level).
     * @param string                $promptKey  Prompt template key
     * @param string|null           $proxy      Optional proxy URL (from env or CLI).
     * @param string|null           $sourceLang Optional source language code.
     * @param string|null           $targetLang Optional target language code.
     * @param bool                  $validate   If true: perform container syntax validation.
     * @param array<string,string>  $variables  Model-specific vars (e.g. ['some'=>'...'])
     * @param bool                  $verbose    If true: include request/response logs.
     *
     * @return array{
     *     original: string,
     *     prepared: string,
     *     result: string,
     *     httpStatus: int,
     *     debugRequest: string,
     *     debugResponse: string,
     *     rawResponseBody: string,
     *     consumed: array<string,mixed>,
     *     lengths: array{original:int,prepared:int,translated:int},
     *     filterStats: array<array{filter:string,count:int}>
     * }
     *
     * @throws \InvalidArgumentException If inputs are invalid.
     * @throws \RuntimeException         On HTTP, parsing or validation errors.
     */
    public static function translate(
        string $apiKey,
        string $format,
        string $modelKey,
        string $text,
        // Optional arguments (alphabetical)
        ?string $context = null,
        bool $dryRun = false,
        array $filters = [],
        ?callable $onFeedback = null,
        string $promptKey = 'translator',
        ?string $proxy = null,
        ?string $sourceLang = null,
        ?string $targetLang = null,
        bool $validate = true,
        array $variables = [],
        bool $verbose = false
    ): array {
        // Prepare holders for validation debug
        $valLogPre  = '';
        $valLogPost = '';
        /** @var array<string,mixed>|null $schemaIn */
        $schemaIn = null;
        /** @var array<string,mixed>|null $rawUsage */
        $rawUsage = null;

        // Feedback helper.
        $say = static function (?callable $cb, string $msg, string $level = 'info'): void {
            if (is_callable($cb)) {
                try {
                    $cb($msg, $level);
                } catch (\Throwable $e) {
                    /* swallow on purpose */
                }
            }
        };

        $say(
            $onFeedback,
            "Starting translation (model='{$modelKey}', format='{$format}', dryRun="
            . ($dryRun ? 'true' : 'false')
            . ")",
            'info'
        );

        // Validate model
        $registry = new ModelRegistry();
        if (!$registry->has($modelKey)) {
            throw new \InvalidArgumentException("Unknown model key: {$modelKey}");
        }
        $endpoint = $registry->getEndpoint($modelKey);
        if (!$endpoint) {
            throw new \InvalidArgumentException("Model {$modelKey} missing required configuration.");
        }
        /** @var array<string,mixed> $model */
        $model = $registry->get($modelKey);
        $driver = $registry->getDriver($modelKey);

        // Override defaults from CLI args if present
        if (!isset($model['defaults']) || !\is_array($model['defaults'])) {
            $model['defaults'] = [];
        }
        if ($targetLang !== null && $targetLang !== '') {
            /** @psalm-suppress MixedArrayOffset */
            $model['defaults']['target_lang'] = $targetLang;
        }
        if ($sourceLang !== null) {
            $model['defaults']['source_lang'] = $sourceLang;
        }
        if ($context !== null) {
            $model['defaults']['context'] = $context;
        }

        // Measure original length
        $originalLength = mb_strlen($text);
        $say($onFeedback, "Input received (length={$originalLength})", 'info');

        // Pre-validation (before filters)
        if ($validate) {
            $say($onFeedback, "Pre-validation started ({$format})", 'info');
            if ($format === 'json') {
                $jsonValidator = new JsonValidator();
                $preResult = $jsonValidator->validate($text);
                if (! $preResult->isValid()) {
                    throw new \RuntimeException(
                        "JSON syntax failed: " . implode('; ', $preResult->getErrors())
                    );
                }
                $parsedIn = json_decode($text, true);
                $schemaIn = Schema::capture($parsedIn);
                if ($verbose) {
                    $valLogPre = "[JSON schema captured]\n";
                }
            } elseif ($format === 'html') {
                $htmlValidator = new HtmlValidator();
                $preResult = $htmlValidator->validate($text);
                if (! $preResult->isValid()) {
                    throw new \RuntimeException(
                        "HTML validation failed: " . implode('; ', $preResult->getErrors())
                    );
                }
                if ($verbose) {
                    $valLogPre = "[HTML validation pre-pass]\n";
                }
            } // other formats: no container validation
            $say($onFeedback, "Pre-validation passed ({$format})", 'info');
        }

        // Apply placeholder filters
        /** @var array<int,string> $filtersList */
        $filtersList = \array_values(\array_map('strval', $filters));
        $filterManager = new FilterManager($filtersList);
        $say($onFeedback, "Applying filters: " . (empty($filters) ? '(none)' : implode(', ', $filters)), 'info');
        $prepared = $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);
        $say($onFeedback, "Filters applied (preparedLength={$preparedLength})", 'info');

        // Length guard: make sure prepared text fits model constraints
        $say($onFeedback, "Checking model length limits", 'info');
        $lengthValidator = TextLengthValidator::fromModelConfig($model);
        $lenResult = $lengthValidator->validate($prepared);
        if (! $lenResult->isValid()) {
            throw new \RuntimeException(
                "Input length exceeds model limits: " . implode('; ', $lenResult->getErrors())
            );
        }
        $say($onFeedback, "Length check passed", 'info');

        // Prepare options for driver, merging in any CLI-provided variables
        $options = array_merge(
            [
                'format' => $format,
                'dryRun' => $dryRun,
                'promptKey' => $promptKey,
                'verbose' => $verbose,
            ],
            $variables
        );

        if ($context !== null) {
            $options['context'] = $context;
        }

        // Build request
        $say($onFeedback, "Building request for endpoint: {$endpoint}", 'info');
        /** @var array{url:mixed, body:mixed, headers:mixed} $reqRaw */
        $reqRaw = $driver->buildRequest(
            $model,
            $prepared,
            $options
        );

        // Normalize request shape
        $reqUrl = \is_string($reqRaw['url']  ?? null) ? (string)$reqRaw['url']  : '';
        $reqBody = \is_string($reqRaw['body'] ?? null) ? (string)$reqRaw['body'] : '';
        $reqHeaders = [];
        foreach ((array)($reqRaw['headers'] ?? []) as $h) {
            if (\is_string($h)) {
                $reqHeaders[] = $h;
            }
        }

        // API auth key handling
        $requirements = isset($model['requirements']) && \is_array($model['requirements'])
            ? $model['requirements']
            : null;
        $auth = \is_array($requirements) ? ($requirements['auth'] ?? null) : null;
        if (\is_array($auth)) {
            if (!$apiKey) {
                throw new \InvalidArgumentException("API key is required for {$modelKey}");
            }
            $say($onFeedback, "Injecting auth credentials", 'info');
            $keyName = \is_string($auth['key_name'] ?? null) ? $auth['key_name'] : '';
            $prefix  = (\is_string($auth['prefix'] ?? null) && $auth['prefix'] !== '')
                ? $auth['prefix'] . ' '
                : '';
            $type = \is_string($auth['type'] ?? null) ? $auth['type'] : 'form';
            switch ($type) {
                case 'header':
                    $reqHeaders[] = "{$keyName}: {$prefix}{$apiKey}";
                    break;
                case 'form':
                    $reqBody .= '&' . urlencode($keyName) . '=' . urlencode($apiKey);
                    break;
                case 'query':
                    $sep = \str_contains($reqUrl, '?') ? '&' : '?';
                    $reqUrl .= $sep . http_build_query([$keyName => $apiKey]);
                    break;
                default:
                    throw new \RuntimeException("Unsupported auth type: {$type}");
            }
        }

        // Perform HTTP request
        $say($onFeedback, $dryRun ? "Dry-run: skipping HTTP request" : "Sending HTTP request", 'info');
        $http = HttpClient::request(
            method: 'POST',
            url: $reqUrl,
            body: $reqBody,
            dryRun: $dryRun,
            headers: $reqHeaders,
            maskPatterns: [
                $apiKey,
            ],
            proxy: $proxy,
            verbose: $verbose
        );

        $httpStatus = $http['status'];
        $debugRequest = $valLogPre . $http['debugRequest'];
        $debugResponse = $http['debugResponse'];
        $raw = $http['body'];

        // Parse response
        if ($dryRun) {
            $translated = $prepared;
            $rawUsage   = null;
            $say($onFeedback, "Dry-run completed", 'info');
        } else {
            $say($onFeedback, "HTTP response received (status={$httpStatus})", 'info');
            $hasCustomHttpHandling = isset($model['http_error_handling']) && !empty($model['http_error_handling']);
            if ($httpStatus >= 400 && !$hasCustomHttpHandling) {
                throw new \RuntimeException(
                    "HTTP {$httpStatus} error from {$reqUrl}: {$raw}\n\n" .
                    $debugRequest . $debugResponse
                );
            }
            $say($onFeedback, "Parsing provider response", 'info');
            try {
                /** @var array{text:string, usage?:array<string,mixed>|null} $parsed */
                $parsed     = $driver->parseResponse($model, $raw);
                $translated = $parsed['text'];
                /** @var array<string,mixed>|null $rawUsage */
                $rawUsage   = $parsed['usage'] ?? null;
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    $e->getMessage() . "\n\n" . $debugRequest . $debugResponse
                );
            }
            $say($onFeedback, "Provider response parsed", 'info');
        }

        $consumed = UsageExtractor::extract($model, $rawUsage);

        // Restore placeholders
        $say($onFeedback, "Restoring placeholders", 'info');
        $result = $filterManager->restore($translated);
        $finalLength = mb_strlen($result);
        $say($onFeedback, "Placeholders restored (translatedLength={$finalLength})", 'info');

        // Collect stats
        $filterStats = $filterManager->getStats();

        // Post-validation (after translation)
        if ($validate) {
            $say($onFeedback, "Post-validation started ({$format})", 'info');
            if ($format === 'json') {
                if ($schemaIn === null) {
                    $schemaIn = Schema::capture(\json_decode($text, true));
                }
                $postResult = (new JsonValidator())->validate($result);
                if (! $postResult->isValid()) {
                    throw new \RuntimeException(
                        "JSON syntax broken: " . implode('; ', $postResult->getErrors()) .
                        "\n\n" . $debugRequest . $debugResponse
                    );
                }
                $parsedOut = json_decode($result, true);
                $schemaOut = Schema::capture($parsedOut);
                $schemaValidation = Schema::validate($schemaIn, $schemaOut);
                if (! $schemaValidation->isValid()) {
                    throw new \RuntimeException(
                        "Schema mismatch: " . implode('; ', $schemaValidation->getErrors()) .
                        "\n\n" . $debugRequest . $debugResponse
                    );
                }
                if ($verbose) {
                    $valLogPost = "[JSON schema validated]\n";
                }
            } elseif ($format === 'html') {
                $htmlValidator = new HtmlValidator();
                $postResult = $htmlValidator->validate($result);
                if (! $postResult->isValid()) {
                    throw new \RuntimeException(
                        "HTML validation failed: " . implode('; ', $postResult->getErrors())
                    );
                }
                if ($verbose) {
                    $valLogPost = "[HTML validation post-pass]\n";
                }
            } // other formats: no container validation
            $say($onFeedback, "Post-validation passed ({$format})", 'info');
        }

        // Append post-validation log into response debug
        if ($verbose) {
            $debugResponse .= $valLogPost;
        }

        $say($onFeedback, "Done", 'info');

        return [
            'original' => $text,
            'prepared' => $prepared,
            'result' => $result,
            'httpStatus' => $httpStatus,
            'debugRequest' => $debugRequest,
            'debugResponse' => $debugResponse,
            'rawResponseBody' => $raw,
            'consumed' => $consumed,
            'lengths' => [
                'original' => $originalLength,
                'prepared' => $preparedLength,
                'translated' => $finalLength,
            ],
            'filterStats' => $filterStats,
        ];
    }
}
