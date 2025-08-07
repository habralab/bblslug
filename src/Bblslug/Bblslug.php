<?php

namespace Bblslug;

use Bblslug\Filters\FilterManager;
use Bblslug\HttpClient;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\Prompts;
use Bblslug\Models\UsageExtractor;
use Bblslug\Validation\HtmlValidator;
use Bblslug\Validation\JsonValidator;
use Bblslug\Validation\Schema;

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
        $all      = $registry->getAll();

        $grouped = [];
        foreach ($all as $key => $config) {
            $vendor = $config['vendor'] ?? 'other';
            $grouped[$vendor][$key] = $config;
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

        // Validate model
        $registry = new ModelRegistry();
        if (!$registry->has($modelKey)) {
            throw new \InvalidArgumentException("Unknown model key: {$modelKey}");
        }
        $endpoint = $registry->getEndpoint($modelKey);
        if (!$endpoint) {
            throw new \InvalidArgumentException("Model {$modelKey} missing required configuration.");
        }
        $model = $registry->get($modelKey);
        $driver = $registry->getDriver($modelKey);

        // Override defaults from CLI args if present
        if ($targetLang !== null) {
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

        // Pre-validation (before filters)
        if ($validate && $format !== 'text') {
            switch ($format) {
                case 'json':
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
                    break;

                case 'html':
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
                    break;

                default:
                    // Other formats: no container validation
                    break;
            }
        }

        // Apply placeholder filters
        $filterManager = new FilterManager($filters);
        $prepared = $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);

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
        $req = $driver->buildRequest(
            $model,
            $prepared,
            $options
        );

        // API auth key handling
        $auth = $model['requirements']['auth'] ?? null;
        if ($auth) {
            if (!$apiKey) {
                throw new \InvalidArgumentException("API key is required for {$modelKey}");
            }
            $keyName = $auth['key_name'];
            $prefix = isset($auth['prefix']) && $auth['prefix'] !== ''
                ? $auth['prefix'] . ' '
                : '';
            switch ($auth['type'] ?? 'form') {
                case 'header':
                    $req['headers'][] = "{$keyName}: {$prefix}{$apiKey}";
                    break;
                case 'form':
                    $req['body'] .= '&' . urlencode($keyName) . '=' . urlencode($apiKey);
                    break;
                case 'query':
                    $sep = str_contains($req['url'], '?') ? '&' : '?';
                    $req['url'] .= $sep . http_build_query([$keyName => $apiKey]);
                    break;
                default:
                    throw new \RuntimeException("Unsupported auth type: {$auth['type']}");
            }
        }

        // Perform HTTP request
        $http = HttpClient::request(
            method: 'POST',
            url: $req['url'],
            body: $req['body'],
            dryRun: $dryRun,
            headers: $req['headers'],
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
        } else {
            if ($httpStatus >= 400 && empty($model['http_error_handling'])) {
                throw new \RuntimeException(
                    "HTTP {$httpStatus} error from {$req['url']}: {$raw}\n\n" .
                    $debugRequest . $debugResponse
                );
            }
            try {
                $parsed     = $driver->parseResponse($model, $raw);
                $translated = $parsed['text'];
                $rawUsage   = $parsed['usage'] ?? null;
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    $e->getMessage() . "\n\n" . $debugRequest . $debugResponse
                );
            }
        }

        $consumed = UsageExtractor::extract($model, $rawUsage);

        // Restore placeholders
        $result = $filterManager->restore($translated);
        $finalLength = mb_strlen($result);

        // Collect stats
        $filterStats = $filterManager->getStats();

        // Post-validation (after translation)
        if ($validate && $format !== 'text') {
            switch ($format) {
                case 'json':
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
                    break;

                case 'html':
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
                    break;

                default:
                    // Other formats: no container validation
                    break;
            }
        }

        // Append post-validation log into response debug
        if ($verbose) {
            $debugResponse .= $valLogPost;
        }

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
