<?php

declare(strict_types=1);

namespace Bblslug;

use Bblslug\Filters\FilterManager;
use Bblslug\HttpClient;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\ModelDriverInterface;
use Bblslug\Models\ModelVariables;
use Bblslug\Models\Prompts;
use Bblslug\Models\UsageExtractor;
use Bblslug\Translation\BatchCheckpoint;
use Bblslug\Translation\CheckpointIdentity;
use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\JsonHtmlDocument;
use Bblslug\Translation\SegmentBatches;
use Bblslug\Translation\SegmentPlan;
use Bblslug\Translation\SegmentTranslations;
use Bblslug\Translation\TextDocument;
use Bblslug\Validation\EmbeddedJsonValidator;
use Bblslug\Validation\JsonHtmlValidator;
use Bblslug\Validation\HtmlValidator;
use Bblslug\Validation\HtmlStructureValidator;
use Bblslug\Validation\JsonValidator;
use Bblslug\Validation\JsonScalarValidator;
use Bblslug\Validation\JsonStringContentValidator;
use Bblslug\Validation\PlaceholderValidator;
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
     * @param string                $format     "text", "html", "json", "structured_json" or "linedJson".
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
     * @param array<string,mixed>   $variables  Model-specific vars; facade controls are reserved.
     * @param bool                  $verbose    If true: include request/response logs.
     * @param bool $validateEmbeddedJson Opt in to checking JSON containers encoded in string values.
     * @param array<int,string> $validateHtmlJsonPointers Explicit JSON Pointers to HTML strings to validate.
     * @param string $htmlTransport HTML document translation or locally reconstructed text segments.
     * @param array<int,string> $htmlJsonPointers Explicit HTML strings to translate in JSON segment mode.
     * @param int|null $htmlSegmentBatchSize Maximum segments per sequential request; null keeps one request.
     * @param string $textTransport Plain text document translation or locally reconstructed line segments.
     * @param int|null $textSegmentBatchSize Maximum text segments per sequential request; null keeps one request.
     * @param int|null $segmentBatchMaxBytes Maximum serialized segment JSON bytes; splits at whitespace if needed.
     * @param int|null $operationTimeoutSeconds Total time budget for preparation and all provider requests.
     * @param string|null $checkpointPath Optional local file retaining validated segment packets for resume.
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
     *     filterStats: array<array{filter:string,count:int}>,
     *     batches?: list<array{index:int,segments:int,httpStatus:int,consumed:array<string,mixed>,reused?:bool}>,
     *     reusedBatches?: int,
     *     consumedThisRun?: array<string,array{total?:int,breakdown?:array<string,int>}>
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
        bool $verbose = false,
        ?callable $onFeedback = null,
        bool $validateEmbeddedJson = false,
        array $validateHtmlJsonPointers = [],
        string $htmlTransport = 'document',
        array $htmlJsonPointers = [],
        ?int $htmlSegmentBatchSize = null,
        string $textTransport = 'document',
        ?int $textSegmentBatchSize = null,
        ?int $segmentBatchMaxBytes = null,
        ?int $operationTimeoutSeconds = null,
        ?string $checkpointPath = null
    ): array {
        $deadline = $operationTimeoutSeconds !== null ? new OperationDeadline($operationTimeoutSeconds) : null;
        if (
            $checkpointPath !== null
            && (
                $checkpointPath === '' || str_contains($checkpointPath, "\0")
                || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $checkpointPath) === 1
                || ($htmlSegmentBatchSize === null && $textSegmentBatchSize === null && $segmentBatchMaxBytes === null)
            )
        ) {
            throw new \InvalidArgumentException('checkpointPath requires a local path and configured segment batching');
        }
        if (
            $segmentBatchMaxBytes !== null
            && ($segmentBatchMaxBytes < 1 || ($textTransport !== 'segments' && $htmlTransport !== 'segments'))
        ) {
            throw new \InvalidArgumentException(
                'segmentBatchMaxBytes requires segments transport and a positive integer'
            );
        }
        if ($textSegmentBatchSize !== null && ($textSegmentBatchSize < 1 || $textTransport !== 'segments')) {
            throw new \InvalidArgumentException(
                'textSegmentBatchSize requires text segments transport and a positive integer'
            );
        }
        if (!\in_array($textTransport, ['document', 'segments'], true)) {
            throw new \InvalidArgumentException('textTransport must be document or segments');
        }
        if ($textTransport === 'segments' && $format !== 'text') {
            throw new \InvalidArgumentException('textTransport segments requires format text');
        }
        if ($textTransport === 'segments' && \array_key_exists('response_schema', $variables)) {
            throw new \InvalidArgumentException('response_schema cannot describe a text segment transport payload');
        }
        if ($htmlSegmentBatchSize !== null && ($htmlSegmentBatchSize < 1 || $htmlTransport !== 'segments')) {
            throw new \InvalidArgumentException(
                'htmlSegmentBatchSize requires segments transport and a positive integer'
            );
        }
        if (!\in_array($htmlTransport, ['document', 'segments'], true)) {
            throw new \InvalidArgumentException('htmlTransport must be document or segments');
        }
        $jsonHtmlSegments = $htmlTransport === 'segments' && \in_array($format, ['json', 'linedJson'], true);
        if ($htmlTransport === 'segments' && !\in_array($format, ['html', 'json', 'linedJson'], true)) {
            throw new \InvalidArgumentException('htmlTransport segments requires format html, json, or linedJson');
        }
        if ($htmlJsonPointers !== [] && !$jsonHtmlSegments) {
            throw new \InvalidArgumentException('htmlJsonPointers requires JSON format and htmlTransport segments');
        }
        if ($jsonHtmlSegments && $htmlJsonPointers === []) {
            throw new \InvalidArgumentException('JSON HTML segments require explicit htmlJsonPointers');
        }
        if ($htmlTransport === 'segments' && \array_key_exists('response_schema', $variables)) {
            throw new \InvalidArgumentException('response_schema cannot describe an HTML segment transport payload');
        }
        ModelVariables::validate($variables);
        $diagnosticMasker = new SecretMasker([
            $apiKey,
            $proxy ?? '',
            \is_string($variables['folder_id'] ?? null) ? $variables['folder_id'] : '',
        ]);

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

        if ($validateHtmlJsonPointers !== [] && !\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
            throw new \InvalidArgumentException('validateHtmlJsonPointers requires a JSON format');
        }

        if ($validateEmbeddedJson && !\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
            throw new \InvalidArgumentException('validateEmbeddedJson requires a JSON format');
        }

        if ($format === 'structured_json' || \array_key_exists('response_schema', $variables)) {
            if (!\in_array($model['vendor'] ?? null, ['openai', 'google'], true)) {
                throw new \InvalidArgumentException('Native JSON schema output is supported only by OpenAI and Google');
            }
            if (!\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
                throw new \InvalidArgumentException('response_schema requires a JSON format');
            }
        }

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

        $jsonHtmlValidator = null;

        // Pre-validation (before filters)
        if ($validate) {
            $say($onFeedback, "Pre-validation started ({$format})", 'info');
            if (\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
                $jsonValidator = new JsonValidator();
                $preResult = $jsonValidator->validate($text);
                if (! $preResult->isValid()) {
                    throw new \RuntimeException(
                        "JSON syntax failed: " . implode('; ', $preResult->getErrors())
                    );
                }
                $htmlChecks = $jsonHtmlSegments
                    ? array_merge($validateHtmlJsonPointers, $htmlJsonPointers) : $validateHtmlJsonPointers;
                if ($htmlChecks !== []) {
                    $jsonHtmlValidator = new JsonHtmlValidator($text, $htmlChecks);
                }
                $parsedIn = json_decode($text, false);
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
        $jsonHtmlDocument = $jsonHtmlSegments ? new JsonHtmlDocument($text, $htmlJsonPointers, $filtersList) : null;
        $filterManager = new FilterManager($jsonHtmlSegments ? [] : $filtersList);
        $say($onFeedback, "Applying filters: " . (empty($filters) ? '(none)' : implode(', ', $filters)), 'info');
        $prepared = $jsonHtmlDocument !== null ? $jsonHtmlDocument->getPrepared() : $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);
        $say($onFeedback, "Filters applied (preparedLength={$preparedLength})", 'info');
        if ($verbose) {
            self::dumpPlaceholdersVerbose($jsonHtmlDocument ?? $filterManager, $onFeedback, $diagnosticMasker);
        }

        $segmentDocument = $jsonHtmlDocument
            ?? ($htmlTransport === 'segments' ? new HtmlTextDocument($prepared)
                : ($textTransport === 'segments' ? new TextDocument($prepared) : null));
        $requestText = $segmentDocument !== null ? $segmentDocument->getJson() : $prepared;
        $segmentBatchSize = $textSegmentBatchSize ?? $htmlSegmentBatchSize;
        $segmentLabel = $textTransport === 'segments' ? 'Text' : 'HTML';
        $batchConfigured = $segmentBatchSize !== null || $segmentBatchMaxBytes !== null;
        $batching = $batchConfigured && $segmentDocument !== null && $segmentDocument->hasTexts();
        $segmentPlan = $segmentBatchMaxBytes !== null
            ? new SegmentPlan($requestText, $segmentBatchSize, $segmentBatchMaxBytes) : null;
        $payloads = $segmentPlan !== null
            ? ($batching ? $segmentPlan->getPayloads() : [$requestText])
            : ($batching ? SegmentBatches::split($requestText, $segmentBatchSize ?? PHP_INT_MAX) : [$requestText]);
        if ($payloads === []) {
            throw new \LogicException('A nonempty segment document requires at least one packet');
        }

        // Length guard: make sure the actual translation payload fits model constraints
        $say($onFeedback, "Checking model length limits", 'info');
        $lengthValidator = TextLengthValidator::fromModelConfig($model);
        foreach ($payloads as $index => $payload) {
            $lenResult = $lengthValidator->validate($payload);
            if (!$lenResult->isValid()) {
                $batchLabel = $batching ? ' (batch ' . ($index + 1) . '/' . count($payloads) . ')' : '';
                throw new \RuntimeException(
                    'Input length exceeds model limits' . $batchLabel . ': ' . implode('; ', $lenResult->getErrors())
                );
            }
        }
        $say($onFeedback, "Length check passed", 'info');

        // Prepare options for driver, merging in any CLI-provided variables
        $options = array_merge(
            [
                'format' => $segmentDocument !== null ? 'json' : $format,
                'dryRun' => $dryRun,
                'promptKey' => $promptKey,
                'verbose' => $verbose,
                'operationDeadline' => $deadline,
            ],
            $variables
        );

        if ($context !== null) {
            $options['context'] = $context;
        }

        // Build every packet before sending any: a later configuration/body-limit
        // failure must not follow an already paid successful packet.
        $requests = [];
        if ($batching) {
            foreach ($payloads as $payload) {
                $requests[] = $driver->buildRequest($model, $payload, $options);
            }
        }
        $checkpoint = null;
        if ($checkpointPath !== null && $batching && !$dryRun) {
            $identity = CheckpointIdentity::create([
                'source' => $text, 'format' => $format, 'modelKey' => $modelKey, 'model' => $model,
                'variables' => $variables, 'filters' => $filtersList, 'promptKey' => $promptKey,
                'validate' => $validate, 'validateEmbeddedJson' => $validateEmbeddedJson,
                'validateHtmlJsonPointers' => $validateHtmlJsonPointers, 'htmlJsonPointers' => $htmlJsonPointers,
                'htmlTransport' => $htmlTransport, 'textTransport' => $textTransport, 'payloads' => $payloads,
                'htmlSegmentBatchSize' => $htmlSegmentBatchSize, 'textSegmentBatchSize' => $textSegmentBatchSize,
                'segmentBatchMaxBytes' => $segmentBatchMaxBytes,
            ], $promptKey);
            $deadline?->check();
            $checkpoint = new BatchCheckpoint($checkpointPath, $identity, count($payloads));
            // Validate every retained packet before issuing any new paid request.
            foreach ($payloads as $savedIndex => $savedPayload) {
                $deadline?->check();
                $saved = $checkpoint->get($savedIndex);
                if ($saved === null) {
                    break;
                }
                $savedPacket = json_decode($savedPayload, false, 512, JSON_THROW_ON_ERROR);
                if (!$savedPacket instanceof \stdClass) {
                    throw new \LogicException('Checkpoint packet must be an object');
                }
                $savedIds = array_keys(get_object_vars($savedPacket));
                SegmentTranslations::decode($saved['text'], $savedIds, $segmentLabel, $textTransport !== 'segments');
            }
        }
        $emptySegments = $segmentDocument !== null && !$segmentDocument->hasTexts();
        $providers = [];
        $batchSummaries = [];
        $batchUsages = [];
        $freshUsages = [];
        $reusedBatches = 0;
        $combined = [];
        $debugRequest = '';
        $debugResponse = '';
        foreach ($payloads as $index => $payload) {
            $batchLabel = $segmentLabel . ' segment batch ' . ($index + 1) . '/' . count($payloads);
            if ($batching) {
                $say($onFeedback, 'Starting ' . $batchLabel, 'info');
            }
            $protocolDebug = '';
            try {
                $deadline?->check();
                $saved = $checkpoint?->get($index);
                $reused = $saved !== null;
                if ($saved !== null) {
                    $provider = [
                        'text' => $saved['text'], 'usage' => null, 'httpStatus' => $saved['httpStatus'],
                        'debugRequest' => '', 'debugResponse' => '', 'rawResponseBody' => '',
                    ];
                    ++$reusedBatches;
                    $say($onFeedback, 'Reusing ' . $batchLabel, 'info');
                } else {
                    $provider = self::executePrepared(
                        $driver,
                        $model,
                        $modelKey,
                        $payload,
                        $options,
                        $apiKey,
                        $proxy,
                        static fn (string $message, string $level = 'info') => $say($onFeedback, $message, $level),
                        $index === 0 ? $valLogPre : '',
                        $emptySegments ? ($jsonHtmlDocument !== null ? $text : $prepared) : null,
                        $requests[$index] ?? null,
                        $deadline
                    );
                }
                if ($batching) {
                    $protocolDebug = $verbose ? "\n\n" . $provider['debugRequest'] . $provider['debugResponse'] : '';
                    $packet = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
                    if (!$packet instanceof \stdClass) {
                        throw new \LogicException($segmentLabel . ' segment packet must be an object');
                    }
                    $expected = array_keys(get_object_vars($packet));
                    $combined += SegmentTranslations::decode(
                        $provider['text'],
                        $expected,
                        $segmentLabel,
                        $textTransport !== 'segments'
                    );
                    $usage = $saved !== null
                        ? $saved['consumed'] : UsageExtractor::extract($model, $provider['usage']);
                    if (!$reused) {
                        $freshUsages[] = $usage;
                        if ($checkpoint !== null) {
                            $checkpoint->append($index, [
                                'text' => $provider['text'], 'httpStatus' => $provider['httpStatus'],
                                'consumed' => $usage,
                            ]);
                        }
                    }
                    $batchUsages[] = $usage;
                    $batchSummary = [
                        'index' => $index + 1, 'segments' => count($expected),
                        'httpStatus' => $provider['httpStatus'], 'consumed' => $usage,
                    ];
                    if ($checkpointPath !== null) {
                        $batchSummary['reused'] = $reused;
                    }
                    $batchSummaries[] = $batchSummary;
                    $say($onFeedback, 'Completed ' . $batchLabel, 'info');
                }
            } catch (\RuntimeException $exception) {
                if (!$batching) {
                    throw $exception;
                }
                throw new \RuntimeException($diagnosticMasker->redact(
                    $batchLabel . ' failed; ' . $index . ' earlier batch(es) completed: '
                    . $exception->getMessage() . $protocolDebug
                ), $exception->getCode(), $exception);
            }
            $providers[] = $provider;
            $logLabel = $batching && ($verbose || $dryRun) ? '[' . $batchLabel . "]\n" : '';
            $debugRequest .= $logLabel . $provider['debugRequest'];
            $debugResponse .= $logLabel . $provider['debugResponse'];
        }
        $httpStatus = $provider['httpStatus'];
        $raw = $batchConfigured
            ? json_encode(array_column($batching ? $providers : [], 'rawResponseBody'), JSON_THROW_ON_ERROR)
            : $provider['rawResponseBody'];
        $rawUsage = $provider['usage'];
        if ($batching) {
            $provider['text'] = json_encode((object) $combined, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if ($segmentPlan !== null) {
                $provider['text'] = $segmentPlan->restore($provider['text']);
            }
        }
        if ($dryRun || $emptySegments) {
            $translated = $jsonHtmlDocument !== null ? $text : $prepared;
        } else {
            try {
                $translated = $segmentDocument !== null
                    ? $segmentDocument->restore($provider['text']) : $provider['text'];
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException((new SecretMasker([$apiKey]))->redact(
                    $exception->getMessage() . "\n\n" . $debugRequest . $debugResponse
                ));
            }
        }

        $consumed = $batching ? UsageExtractor::sum($batchUsages) : UsageExtractor::extract($model, $rawUsage);

        // Integrity is required for safe restoration, independently of container validation.
        if ($filterManager->getStats() !== []) {
            $placeholderResult = (new PlaceholderValidator($prepared))->validate($translated);
            if (!$placeholderResult->isValid()) {
                throw new \RuntimeException(
                    "Placeholder mismatch: " . implode('; ', $placeholderResult->getErrors())
                );
            }
        }

        // Restore placeholders
        $say($onFeedback, "Restoring placeholders", 'info');
        $result = $filterManager->restore($translated);
        $finalLength = mb_strlen($result);
        $say($onFeedback, "Placeholders restored (translatedLength={$finalLength})", 'info');

        // Collect stats
        $filterStats = $jsonHtmlDocument !== null ? $jsonHtmlDocument->getFilterStats() : $filterManager->getStats();

        // Post-validation (after translation)
        if ($validate) {
            $say($onFeedback, "Post-validation started ({$format})", 'info');

            if (\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
                // Ensure we have input schema captured (head branch safeguard)
                if ($schemaIn === null) {
                    $schemaIn = Schema::capture(\json_decode($text, false));
                }

                // JSON syntax check
                $postResult = (new JsonValidator())->validate($result);
                if (! $postResult->isValid()) {
                    if ($verbose) {
                        $debugResponse .= "\n[Restored JSON (invalid) dump]\n";
                        $debugResponse .= self::ellipsize($diagnosticMasker->redact($result), 2000) . "\n";
                    }
                    throw new \RuntimeException(
                        "JSON syntax broken: " . implode('; ', $postResult->getErrors())
                        . "\n\n" . $debugRequest . $debugResponse
                    );
                }

                // Decode produced JSON
                /** @var mixed $parsedOut */
                $parsedOut = \json_decode($result, false);

                // Validate the structure of the JSON that will actually be returned.
                $schemaOut = Schema::capture($parsedOut);
                $schemaValidation = Schema::validate($schemaIn, $schemaOut);

                if (! $schemaValidation->isValid()) {
                    throw new \RuntimeException(
                        "Schema mismatch: "
                        . implode('; ', $schemaValidation->getErrors())
                        . "\n\n" . $debugRequest . $debugResponse
                    );
                }
                $scalarValidation = (new JsonScalarValidator($text))->validate($result);
                if (!$scalarValidation->isValid()) {
                    throw new \RuntimeException(
                        "JSON scalar values changed: " . implode('; ', $scalarValidation->getErrors())
                    );
                }
                $stringValidation = (new JsonStringContentValidator($text))->validate($result);
                if (!$stringValidation->isValid()) {
                    throw new \RuntimeException(
                        "JSON string content lost: " . implode('; ', $stringValidation->getErrors())
                    );
                }
                if ($jsonHtmlValidator !== null) {
                    $htmlResult = $jsonHtmlValidator->validate($result);
                    if (!$htmlResult->isValid()) {
                        throw new \RuntimeException(
                            "HTML inside JSON mismatch: " . implode('; ', $htmlResult->getErrors())
                        );
                    }
                }
                if ($validateEmbeddedJson) {
                    $embeddedResult = (new EmbeddedJsonValidator($text))->validate($result);
                    if (!$embeddedResult->isValid()) {
                        throw new \RuntimeException(
                            "Embedded JSON mismatch: " . implode('; ', $embeddedResult->getErrors())
                        );
                    }
                }
                if ($verbose) {
                    $valLogPost .= "[JSON schema validated]\n";
                }
            } elseif ($format === 'html') {
                $htmlValidator = new HtmlValidator();
                $postResult = $htmlValidator->validate($result);
                if (! $postResult->isValid()) {
                    if ($verbose) {
                        $debugResponse .= "\n[Restored HTML (invalid) dump]\n";
                        $debugResponse .= self::ellipsize($diagnosticMasker->redact($result), 2000) . "\n";
                    }
                    throw new \RuntimeException(
                        "HTML validation failed: "
                        . implode('; ', $postResult->getErrors())
                        . "\n\n" . $debugRequest . $debugResponse
                    );
                }
                $structureResult = (new HtmlStructureValidator($text))->validate($result);
                if (!$structureResult->isValid()) {
                    if ($verbose) {
                        $debugResponse .= "\n[Restored HTML (invalid) dump]\n";
                        $debugResponse .= self::ellipsize($diagnosticMasker->redact($result), 2000) . "\n";
                    }
                    throw new \RuntimeException(
                        "HTML structure mismatch: " . implode('; ', $structureResult->getErrors())
                        . ($verbose ? "\n\n" . $debugRequest . $debugResponse : '')
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

        $resultData = [
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
        if ($batchConfigured) {
            $resultData['batches'] = $batchSummaries;
        }
        if ($checkpointPath !== null) {
            $resultData['reusedBatches'] = $reusedBatches;
            $resultData['consumedThisRun'] = UsageExtractor::sum($freshUsages);
        }
        $deadline?->check();
        return $resultData;
    }

    /**
     * Execute one provider request, including its request-local continuation.
     *
     * @param array<string,mixed> $model
     * @param array<string,mixed> $options
     * @param callable(string,string=):void $feedback
     * @param array<string,mixed>|null $prebuiltRequest
     * @return array{text:string,usage:array<string,mixed>|null,httpStatus:int,debugRequest:string,
     *     debugResponse:string,rawResponseBody:string}
     */
    private static function executePrepared(
        ModelDriverInterface $driver,
        array $model,
        string $modelKey,
        string $requestText,
        array $options,
        string $apiKey,
        ?string $proxy,
        callable $feedback,
        string $validationLog,
        ?string $localOverride = null,
        ?array $prebuiltRequest = null,
        ?OperationDeadline $deadline = null
    ): array {
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $verbose = (bool) ($options['verbose'] ?? false);
        $rawUsage = null;
        $endpoint = is_string($model['endpoint'] ?? null) ? $model['endpoint'] : '';
        // Build request
        $feedback("Building request for endpoint: {$endpoint}", 'info');
        /** @var array{url:mixed,body:mixed,headers:mixed,responseContext?:mixed,localResult?:string,workflow?:HttpWorkflowInterface,timeoutSeconds?:int} $reqRaw */
        $reqRaw = $prebuiltRequest ?? $driver->buildRequest(
            $model,
            $requestText,
            $options
        );

        if ($localOverride !== null) {
            $reqRaw['localResult'] = $localOverride;
        }
        $options['responseContext'] = $reqRaw['responseContext'] ?? null;
        $localResult = $reqRaw['localResult'] ?? null;

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
            $feedback("Injecting auth credentials", 'info');
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

        $maskPatterns = [
            $apiKey,
            $proxy ?? '',
            \is_string($options['folder_id'] ?? null) ? $options['folder_id'] : '',
        ];

        // Perform HTTP request
        $requestMessage = $dryRun ? "Dry-run: skipping HTTP request" : "Sending HTTP request";
        if ($localResult !== null) {
            $requestMessage = "No translatable string values: skipping HTTP request";
        }
        $feedback($requestMessage, 'info');
        try {
            $http = HttpClient::request(
                method: 'POST',
                url: $reqUrl,
                body: $reqBody,
                dryRun: $dryRun || $localResult !== null,
                headers: $reqHeaders,
                maskPatterns: $maskPatterns,
                proxy: $proxy,
                verbose: $verbose,
                timeoutSeconds: $reqRaw['timeoutSeconds'] ?? null,
                timeoutMilliseconds: $deadline?->remainingMilliseconds()
            );
        } catch (\RuntimeException $exception) {
            $deadline?->check();
            throw $exception;
        }

        $deadline?->check();
        $workflow = $reqRaw['workflow'] ?? null;
        if (!$dryRun && $localResult === null && $workflow instanceof HttpWorkflowInterface) {
            $http = $workflow->complete(
                $http,
                /**
                 * @param array{url:string,body:string,headers:array<int,string>,
                 *     maskPatterns:array<int,string>,timeoutSeconds:int} $next
                 * @return array{status:int,body:string,headers:array<string,array<int,string>>,
                 *     debugRequest:string,debugResponse:string}
                 */
                static function (array $next) use ($reqHeaders, $maskPatterns, $proxy, $verbose, $deadline): array {
                    $headers = array_values(array_filter(
                        $reqHeaders,
                        static fn (string $header): bool => stripos($header, 'Content-Type:') !== 0
                    ));
                    try {
                        return HttpClient::request(
                            method: 'POST',
                            url: $next['url'],
                            body: $next['body'],
                            headers: array_merge($headers, $next['headers']),
                            maskPatterns: array_merge($maskPatterns, $next['maskPatterns']),
                            proxy: $proxy,
                            verbose: $verbose,
                            timeoutSeconds: $next['timeoutSeconds'],
                            timeoutMilliseconds: $deadline?->remainingMilliseconds()
                        );
                    } catch (\RuntimeException $e) {
                        $deadline?->check();
                        throw new \RuntimeException(
                            (new SecretMasker($maskPatterns))->redact($e->getMessage()),
                            $e->getCode()
                        );
                    }
                }
            );
        }

        $deadline?->check();
        $httpStatus = $http['status'];
        $debugRequest = $validationLog . $http['debugRequest'];
        $debugResponse = $http['debugResponse'];
        $raw = $http['body'];

        $errorMasker = new SecretMasker($maskPatterns);

        // Parse response
        if ($dryRun || $localResult !== null) {
            $translated = $localResult ?? $requestText;
            $rawUsage   = null;
            $feedback($localResult !== null ? "Local translation completed" : "Dry-run completed", 'info');
        } else {
            $feedback("HTTP response received (status={$httpStatus})", 'info');
            $hasCustomHttpHandling = isset($model['http_error_handling']) && !empty($model['http_error_handling']);
            if ($httpStatus >= 400 && !$hasCustomHttpHandling) {
                throw new \RuntimeException(
                    $errorMasker->redact(
                        "HTTP {$httpStatus} error from {$reqUrl}: {$raw}\n\n" . $debugRequest . $debugResponse
                    )
                );
            }
            $feedback("Parsing provider response", 'info');
            try {
                /** @var array{text:string, usage?:array<string,mixed>|null} $parsed */
                $parsed     = $driver->parseResponse($model, $raw, $options);
                $translated = $parsed['text'];
                /** @var array<string,mixed>|null $rawUsage */
                $rawUsage   = $parsed['usage'] ?? null;
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    $errorMasker->redact(
                        $e->getMessage() . "\n\n" . $debugRequest . $debugResponse
                    )
                );
            }
            if (
                preg_match('/\A\s*\z/u', $requestText) !== 1
                && preg_match('/\A\s*\z/u', $translated) === 1
            ) {
                throw new \RuntimeException('Provider returned an empty translation for nonempty input');
            }
            $feedback("Provider response parsed", 'info');
        }

        $deadline?->check();
        return [
            'text' => $translated,
            'usage' => $rawUsage,
            'httpStatus' => $httpStatus,
            'debugRequest' => $debugRequest,
            'debugResponse' => $debugResponse,
            'rawResponseBody' => $raw,
        ];
    }

    /** @param callable(string,string=):void|null $onFeedback */
    private static function dumpPlaceholdersVerbose(
        FilterManager|JsonHtmlDocument $filterManager,
        ?callable $onFeedback,
        SecretMasker $masker
    ): void {
        if ($onFeedback === null) {
            return;
        }
        $maps = $filterManager->getDebugMaps();
        if ($maps === []) {
            return;
        }
        $lines = ['Verbose: placeholders captured'];
        foreach ($maps as $entry) {
            $total = count($entry['map']);
            $lines[] = "- {$entry['filter']}: {$total} item(s)";
            $i = 0;
            foreach ($entry['map'] as $placeholder => $value) {
                if ($i++ >= 50) {
                    $lines[] = '  … (+' . ($total - 50) . ' more)';
                    break;
                }
                // Mask the complete slice before truncation can expose a partial credential.
                $preview = self::ellipsize($masker->redact($value), 200);
                $encoded = json_encode(
                    $preview,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
                $lines[] = "  {$placeholder} => {$encoded}";
            }
        }
        try {
            $onFeedback(implode("\n", $lines), 'info');
        } catch (\Throwable $e) {
            // Feedback must not prevent translation.
        }
    }

    private static function ellipsize(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }
}
