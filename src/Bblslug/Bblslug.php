<?php

declare(strict_types=1);

namespace Bblslug;

use Bblslug\Diagnostics\ContentPreview;
use Bblslug\Filters\FilterManager;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\ModelVariables;
use Bblslug\Models\Prompts;
use Bblslug\Models\UsageExtractor;
use Bblslug\Translation\BatchCheckpoint;
use Bblslug\Translation\CheckpointIdentity;
use Bblslug\Translation\DocumentValidation;
use Bblslug\Translation\JsonHtmlDocument;
use Bblslug\Translation\ProviderExecutor;
use Bblslug\Translation\SegmentBatches;
use Bblslug\Translation\SegmentBatchState;
use Bblslug\Translation\SegmentDocumentFactory;
use Bblslug\Translation\SegmentPlan;
use Bblslug\Translation\SegmentTranslations;
use Bblslug\Translation\TransportValidation;
use Bblslug\Validation\PlaceholderValidator;
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

        ksort($grouped);
        foreach ($grouped as &$models) {
            ksort($models, SORT_NATURAL);
        }
        unset($models);

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
     * @param string|null           $proxy      Optional proxy URL.
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
     * @param list<string> $segmentAbbreviations Dotted tokens to keep with their following token when possible.
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
        // Optional translation controls
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
        ?string $checkpointPath = null,
        array $segmentAbbreviations = []
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
        if ($segmentAbbreviations !== [] && $segmentBatchMaxBytes === null) {
            throw new \InvalidArgumentException('segmentAbbreviations requires segmentBatchMaxBytes');
        }
        $hasResponseSchema = \array_key_exists('response_schema', $variables);
        TransportValidation::validateText($format, $textTransport, $textSegmentBatchSize, $hasResponseSchema);
        $jsonHtmlSegments = TransportValidation::validateHtml(
            $format,
            $htmlTransport,
            $htmlSegmentBatchSize,
            $htmlJsonPointers,
            $hasResponseSchema
        );
        ModelVariables::validate($variables);
        $diagnosticMasker = new SecretMasker([
            $apiKey,
            $proxy ?? '',
            \is_string($variables['folder_id'] ?? null) ? $variables['folder_id'] : '',
        ]);

        // Prepare validation diagnostics
        $valLogPre  = '';

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
            if (!\in_array($model['vendor'] ?? null, ['openai', 'google', 'yandex', 'xai'], true)) {
                throw new \InvalidArgumentException(
                    'Native JSON schema output is supported only by OpenAI, Google, Yandex and xAI'
                );
            }
            if (!\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
                throw new \InvalidArgumentException('response_schema requires a JSON format');
            }
        }

        // Override model defaults with explicit translation arguments
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

        $documentValidation = null;
        if ($validate) {
            $htmlChecks = $jsonHtmlSegments
                ? array_merge($validateHtmlJsonPointers, $htmlJsonPointers) : $validateHtmlJsonPointers;
            $documentValidation = new DocumentValidation(
                $format,
                $text,
                $validateEmbeddedJson,
                $htmlChecks,
                $verbose,
                $diagnosticMasker
            );
            $say($onFeedback, "Pre-validation started ({$format})", 'info');
            $valLogPre = $documentValidation->validateSource();
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
            ?? SegmentDocumentFactory::create($prepared, $htmlTransport, $textTransport);
        $requestText = $segmentDocument !== null ? $segmentDocument->getJson() : $prepared;
        $segmentBatchSize = $textSegmentBatchSize ?? $htmlSegmentBatchSize;
        $segmentLabel = $textTransport === 'segments' ? 'Text' : 'HTML';
        $batchConfigured = $segmentBatchSize !== null || $segmentBatchMaxBytes !== null;
        $batching = $batchConfigured && $segmentDocument !== null && $segmentDocument->hasTexts();
        $segmentPlan = $segmentBatchMaxBytes !== null
            ? new SegmentPlan($requestText, $segmentBatchSize, $segmentBatchMaxBytes, $segmentAbbreviations) : null;
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

        // Merge driver options with caller-provided variables
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

        $executor = new ProviderExecutor(
            $driver,
            $model,
            $modelKey,
            $apiKey,
            $proxy,
            static fn (string $message, string $level = 'info') => $say($onFeedback, $message, $level),
            $deadline
        );

        // Build every packet before sending any: a later configuration/body-limit
        // failure must not follow an already paid successful packet.
        $requests = [];
        $packetOptions = [];
        $capabilities = \is_array($model['capabilities'] ?? null) ? $model['capabilities'] : [];
        $nativeSegmentSchema = $segmentDocument !== null && $segmentDocument->hasTexts()
            && ($capabilities['native_json_schema'] ?? false) === true
            && $promptKey === 'translator'
            && in_array('structured_json', Prompts::list()['translator']['formats'] ?? [], true)
            && ($variables['json_transport'] ?? 'auto') !== 'markers';
        foreach ($payloads as $index => $payload) {
            $packetOptions[$index] = $options;
            if ($nativeSegmentSchema) {
                $packetOptions[$index]['response_schema'] = SegmentTranslations::schemaFor($payload);
            }
            if ($batching) {
                $requests[$index] = $driver->buildRequest($model, $payload, $packetOptions[$index]);
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
                'segmentBatchMaxBytes' => $segmentBatchMaxBytes, 'segmentAbbreviations' => $segmentAbbreviations,
            ], $promptKey);
            $deadline?->check();
            $checkpoint = new BatchCheckpoint($checkpointPath, $identity, count($payloads));
        }
        $batchState = $batching ? new SegmentBatchState(
            $model,
            $segmentLabel,
            $textTransport !== 'segments',
            $checkpoint,
            $deadline
        ) : null;
        $batchState?->validateRetained($payloads);
        $emptySegments = $segmentDocument !== null && !$segmentDocument->hasTexts();
        $rawResponses = [];
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
                if ($saved !== null) {
                    $provider = [
                        'text' => $saved['text'], 'usage' => null, 'httpStatus' => $saved['httpStatus'],
                        'debugRequest' => '', 'debugResponse' => '', 'rawResponseBody' => '',
                    ];
                    $say($onFeedback, 'Reusing ' . $batchLabel, 'info');
                } else {
                    $provider = $executor->execute(
                        $payload,
                        $packetOptions[$index],
                        $index === 0 ? $valLogPre : '',
                        $emptySegments ? ($jsonHtmlDocument !== null ? $text : $prepared) : null,
                        $requests[$index] ?? null
                    );
                }
                if ($batchState !== null) {
                    $protocolDebug = $verbose ? "\n\n" . $provider['debugRequest'] . $provider['debugResponse'] : '';
                    if ($saved !== null) {
                        $batchState->acceptRetained($index, $payload, $saved);
                    } else {
                        $batchState->acceptFresh($index, $payload, $provider);
                    }
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
            if ($batching) {
                $rawResponses[] = $provider['rawResponseBody'];
            }
            $logLabel = $batching && ($verbose || $dryRun) ? '[' . $batchLabel . "]\n" : '';
            $debugRequest .= $logLabel . $provider['debugRequest'];
            $debugResponse .= $logLabel . $provider['debugResponse'];
            // Each completed packet has consumed its prebuilt request and schema.
            unset($requests[$index], $packetOptions[$index]);
        }
        $httpStatus = $provider['httpStatus'];
        $raw = $batchConfigured
            ? json_encode($rawResponses, JSON_THROW_ON_ERROR)
            : $provider['rawResponseBody'];
        $rawUsage = $provider['usage'];
        unset($rawResponses, $requests, $packetOptions);
        $batchResult = $batchState?->result($checkpointPath !== null);
        // $checkpoint remains the lock owner until the operation exits.
        unset($batchState);
        if ($batchResult !== null) {
            $provider['text'] = $batchResult['text'];
            if ($segmentPlan !== null) {
                $provider['text'] = $segmentPlan->restore($provider['text']);
            }
            unset($batchResult['text']);
        }
        // Reconstruction no longer needs the planning records.
        unset($segmentPlan);
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

        $consumed = $batchResult !== null ? $batchResult['consumed'] : UsageExtractor::extract($model, $rawUsage);

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

        if ($documentValidation !== null) {
            $say($onFeedback, "Post-validation started ({$format})", 'info');
            $debugResponse = $documentValidation->validateTranslation($result, $debugRequest, $debugResponse);
            $say($onFeedback, "Post-validation passed ({$format})", 'info');
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
            $resultData['batches'] = $batchResult['batches'] ?? [];
        }
        if ($checkpointPath !== null) {
            $resultData['reusedBatches'] = $batchResult['reusedBatches'] ?? 0;
            $resultData['consumedThisRun'] = $batchResult['consumedThisRun'] ?? [];
        }
        $deadline?->check();
        return $resultData;
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
                $preview = ContentPreview::render($value, $masker, 200);
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
}
