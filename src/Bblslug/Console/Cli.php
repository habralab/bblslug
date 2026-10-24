<?php

declare(strict_types=1);

namespace Bblslug\Console;

use Bblslug\Bblslug;
use Bblslug\Console\Help;
use Bblslug\Filters\FilterManager;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\ModelVariables;
use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\JsonHtmlDocument;
use Bblslug\Translation\SegmentPlan;
use Bblslug\Translation\TextDocument;
use Bblslug\Validation\JsonHtmlValidator;

/**
 * CLI entrypoint for the Bblslug translation tool.
 *
 * Parses CLI arguments, initializes models, and dispatches translation requests.
 */
class Cli
{
    /**
     * CLI translation entrypoint.
     *
     * Parse CLI flags, read input, call {@see Bblslug::translate()}, and output results.
     * Also handles `--help`, `--list-models`, `--list-prompts`, `--dry-run`,
     * `--verbose` and emits summary stats to STDERR.
     *
     * @return void
     */
    public static function run(): void
    {
        // Load CLI arguments
        $options = getopt("", [
            "context:",      // extra context prompt
            "checkpoint:",   // resumable segment-batch checkpoint file
            "dry-run",       // placeholders only
            "filters:",      // comma-separated filter list
            "format:",       // text, HTML or a JSON format
            "html-transport:", // HTML transport: document or segments
            "html-segment-batch-size:", // positive number of HTML segments per request
            "segment-batch-max-bytes:", // positive serialized segment packet byte limit
            "html-json-pointer:", // JSON Pointer to HTML strings selected for segment translation (repeatable)
            "help",          // show help and exit
            "list-models",   // show models and exit
            "list-prompts",  // show available prompt templates and exit
            "model:",        // model key
            "operation-timeout:", // total translation operation deadline
            "no-validate",   // disable pre- and post-validation of container syntax
            "prompt-key:",   // prompt template key (from prompts.yaml)
            "response-schema:", // JSON Schema file for native JSON output
            "proxy:",        // optional proxy URI
            "source:",       // input file (default = STDIN)
            "source-lang:",  // override source language
            "text-transport:", // text transport: document or segments
            "text-segment-batch-size:", // positive number of text segments per request
            "target-lang:",  // override target language
            "translated:",   // output file (default = STDOUT)
            "validate-embedded-json", // opt-in checks for encoded JSON containers
            "validate-html-json-pointer:", // opt-in HTML checks at JSON Pointer paths (repeatable)
            "variables:",    // model-specific overrides
            "verbose",       // enable debug logs
        ]);

        // Initialize registry
        $registry = new ModelRegistry();

        // Help screen
        if (isset($options["help"])) {
            Help::printHelp(0);
        }

        // List models screen
        if (isset($options['list-models'])) {
            Help::printModelList();
            exit(0);
        }
        // List prompts screen
        if (isset($options['list-prompts'])) {
            Help::printPromptList();
            exit(0);
        }

        // Extract params
        $context    = isset($options['context']) && is_string($options['context'])
                        ? $options['context'] : null;
        $dryRun     = isset($options['dry-run']);
        $checkpointPath = null;
        $format     = isset($options['format']) && is_string($options['format'])
                        ? $options['format'] : null;
        $htmlTransportOption = $options['html-transport'] ?? null;
        if ($htmlTransportOption !== null && !is_string($htmlTransportOption)) {
            Help::error('--html-transport requires a single string value.');
        }
        $htmlTransport = $htmlTransportOption ?? 'document';
        $textTransportOption = $options['text-transport'] ?? null;
        if ($textTransportOption !== null && !is_string($textTransportOption)) {
            Help::error('--text-transport requires a single string value.');
        }
        $textTransport = $textTransportOption ?? 'document';
        $htmlSegmentBatchSize = null;
        $textSegmentBatchSize = null;
        $segmentBatchMaxBytes = null;
        $filters    = isset($options['filters']) && is_string($options['filters'])
                        && trim($options['filters']) !== ''
                        ? array_map('trim', explode(',', $options['filters']))
                        : [];
        $modelKey   = isset($options['model']) && is_string($options['model'])
                        ? $options['model']
                        : null;
        $outFile    = isset($options['translated']) && is_string($options['translated'])
                        ? $options['translated']
                        : null;
        $promptKey  = isset($options['prompt-key']) && is_string($options['prompt-key'])
                        ? $options['prompt-key']
                        : 'translator';
        $sourceFile = isset($options['source']) && is_string($options['source'])
                        ? $options['source']
                        : null;
        $sourceLang = isset($options['source-lang']) && is_string($options['source-lang'])
                        ? $options['source-lang']
                        : null;
        $targetLang = isset($options['target-lang']) && is_string($options['target-lang'])
                        ? $options['target-lang']
                        : null;
        $validate   = ! isset($options['no-validate']);
        $verbose    = isset($options['verbose']);

        // getopt returns a scalar for one occurrence and an array for repeated
        // occurrences. Preserve empty strings so that the empty JSON Pointer
        // (the document root) can be requested with --validate-html-json-pointer ''.
        $htmlJsonPointers = [];
        $htmlJsonSelectionPointers = [];
        $rawArgv = $_SERVER['argv'] ?? [];
        $argv = is_array($rawArgv) ? array_values($rawArgv) : [];
        $batchSizeOccurrences = 0;
        $textBatchSizeOccurrences = 0;
        $segmentBatchMaxBytesOccurrences = 0;
        $operationTimeoutOccurrences = 0;
        $checkpointOccurrences = 0;
        $textTransportOccurrences = 0;
        foreach ($argv as $index => $argument) {
            if ($argument === '--') {
                break;
            }
            if (!is_string($argument)) {
                continue;
            }
            if (
                $argument === '--html-segment-batch-size'
                || str_starts_with($argument, '--html-segment-batch-size=')
            ) {
                $batchSizeOccurrences++;
            }
            if (
                $argument === '--text-segment-batch-size'
                || str_starts_with($argument, '--text-segment-batch-size=')
            ) {
                $textBatchSizeOccurrences++;
            }
            if (
                $argument === '--segment-batch-max-bytes'
                || str_starts_with($argument, '--segment-batch-max-bytes=')
            ) {
                $segmentBatchMaxBytesOccurrences++;
            }
            if (
                $argument === '--operation-timeout'
                || str_starts_with($argument, '--operation-timeout=')
            ) {
                $operationTimeoutOccurrences++;
            }
            if ($argument === '--checkpoint' || str_starts_with($argument, '--checkpoint=')) {
                $checkpointOccurrences++;
            }
            if ($argument === '--text-transport=') {
                Help::error('--text-transport requires document or segments.');
            } elseif ($argument === '--text-transport') {
                $textTransportOccurrences++;
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    Help::error('--text-transport requires a value.');
                }
            } elseif (str_starts_with($argument, '--text-transport=')) {
                $textTransportOccurrences++;
            }
            if ($argument === '--validate-html-json-pointer=') {
                Help::error(
                    'Empty --validate-html-json-pointer value must be passed as a separate empty argument.'
                );
            } elseif ($argument === '--validate-html-json-pointer') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    Help::error('--validate-html-json-pointer requires a value.');
                }
            } elseif ($argument === '--html-json-pointer=') {
                Help::error(
                    'Empty --html-json-pointer value must be passed as a separate empty argument.'
                );
            } elseif ($argument === '--html-json-pointer') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    Help::error('--html-json-pointer requires a value.');
                }
            } elseif ($argument === '--html-transport=') {
                Help::error(
                    '--html-transport requires document or segments.'
                );
            } elseif ($argument === '--html-transport') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    Help::error('--html-transport requires a value.');
                }
            } elseif ($argument === '--html-segment-batch-size=') {
                Help::error('--html-segment-batch-size requires a positive integer value.');
            } elseif ($argument === '--html-segment-batch-size') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || ($next !== '' && str_starts_with($next, '--'))) {
                    Help::error('--html-segment-batch-size requires a value.');
                }
            } elseif ($argument === '--text-segment-batch-size=') {
                Help::error('--text-segment-batch-size requires a positive integer value.');
            } elseif ($argument === '--text-segment-batch-size') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    Help::error('--text-segment-batch-size requires a value.');
                }
            } elseif ($argument === '--segment-batch-max-bytes=') {
                Help::error('--segment-batch-max-bytes requires a positive integer value.');
            } elseif ($argument === '--segment-batch-max-bytes') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    Help::error('--segment-batch-max-bytes requires a value.');
                }
            } elseif ($argument === '--operation-timeout=') {
                Help::error('--operation-timeout requires a positive integer value.');
            } elseif ($argument === '--operation-timeout') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    Help::error('--operation-timeout requires a value.');
                }
            } elseif ($argument === '--checkpoint=') {
                Help::error('--checkpoint requires a non-empty path.');
            } elseif ($argument === '--checkpoint') {
                $next = $argv[$index + 1] ?? null;
                if (!is_string($next) || $next === '' || str_starts_with($next, '--')) {
                    Help::error('--checkpoint requires a path.');
                }
            }
        }

        if ($batchSizeOccurrences > 1) {
            Help::error('--html-segment-batch-size may only be specified once.');
        }
        if ($textTransportOccurrences > 1) {
            Help::error('--text-transport may only be specified once.');
        }
        if ($textBatchSizeOccurrences > 1) {
            Help::error('--text-segment-batch-size may only be specified once.');
        }
        if ($segmentBatchMaxBytesOccurrences > 1) {
            Help::error('--segment-batch-max-bytes may only be specified once.');
        }
        if ($operationTimeoutOccurrences > 1) {
            Help::error('--operation-timeout may only be specified once.');
        }
        if ($checkpointOccurrences > 1) {
            Help::error('--checkpoint may only be specified once.');
        }
        if (isset($options['checkpoint'])) {
            $rawCheckpointPath = $options['checkpoint'];
            if (is_array($rawCheckpointPath)) {
                Help::error('--checkpoint may only be specified once.');
            }
            if (
                !is_string($rawCheckpointPath) || $rawCheckpointPath === ''
                || str_contains($rawCheckpointPath, "\0")
                || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $rawCheckpointPath) === 1
            ) {
                Help::error('--checkpoint requires a non-empty path.');
            }
            $checkpointPath = $rawCheckpointPath;
        }

        // This option is intentionally singleton. getopt() returns an array for
        // repeated options, but silently accepting that would make the selected
        // batch size depend on implementation details.
        if (isset($options['html-segment-batch-size'])) {
            $rawBatchSize = $options['html-segment-batch-size'];
            if (is_array($rawBatchSize)) {
                Help::error('--html-segment-batch-size may only be specified once.');
            }
            if (!is_string($rawBatchSize) || !preg_match('/\A[1-9][0-9]*\z/', $rawBatchSize)) {
                Help::error('--html-segment-batch-size requires a positive integer value.');
            }
            $maxInt = (string) PHP_INT_MAX;
            if (
                strlen($rawBatchSize) > strlen($maxInt)
                || (strlen($rawBatchSize) === strlen($maxInt) && strcmp($rawBatchSize, $maxInt) > 0)
            ) {
                Help::error('--html-segment-batch-size is too large.');
            }
            $htmlSegmentBatchSize = (int) $rawBatchSize;
        }
        if (isset($options['text-segment-batch-size'])) {
            $rawTextBatchSize = $options['text-segment-batch-size'];
            if (is_array($rawTextBatchSize)) {
                Help::error('--text-segment-batch-size may only be specified once.');
            }
            if (!is_string($rawTextBatchSize) || !preg_match('/\A[1-9][0-9]*\z/', $rawTextBatchSize)) {
                Help::error('--text-segment-batch-size requires a positive integer value.');
            }
            $maxInt = (string) PHP_INT_MAX;
            if (
                strlen($rawTextBatchSize) > strlen($maxInt)
                || (strlen($rawTextBatchSize) === strlen($maxInt) && strcmp($rawTextBatchSize, $maxInt) > 0)
            ) {
                Help::error('--text-segment-batch-size is too large.');
            }
            $textSegmentBatchSize = (int) $rawTextBatchSize;
        }
        if (isset($options['segment-batch-max-bytes'])) {
            $rawMaxBytes = $options['segment-batch-max-bytes'];
            if (is_array($rawMaxBytes)) {
                Help::error('--segment-batch-max-bytes may only be specified once.');
            }
            if (!is_string($rawMaxBytes) || !preg_match('/\A[1-9][0-9]*\z/', $rawMaxBytes)) {
                Help::error('--segment-batch-max-bytes requires a positive integer value.');
            }
            $maxInt = (string) PHP_INT_MAX;
            if (
                strlen($rawMaxBytes) > strlen($maxInt)
                || (strlen($rawMaxBytes) === strlen($maxInt) && strcmp($rawMaxBytes, $maxInt) > 0)
            ) {
                Help::error('--segment-batch-max-bytes is too large.');
            }
            $segmentBatchMaxBytes = (int) $rawMaxBytes;
        }
        $operationTimeoutSeconds = null;
        if (isset($options['operation-timeout'])) {
            $rawOperationTimeout = $options['operation-timeout'];
            if (is_array($rawOperationTimeout)) {
                Help::error('--operation-timeout may only be specified once.');
            }
            if (
                !is_string($rawOperationTimeout)
                || !preg_match('/\A[1-9][0-9]*\z/', $rawOperationTimeout)
            ) {
                Help::error('--operation-timeout requires a positive integer value.');
            }
            $maxInt = (string) PHP_INT_MAX;
            if (
                strlen($rawOperationTimeout) > strlen($maxInt)
                || (
                    strlen($rawOperationTimeout) === strlen($maxInt)
                    && strcmp($rawOperationTimeout, $maxInt) > 0
                )
            ) {
                Help::error('--operation-timeout is too large.');
            }
            $operationTimeoutSeconds = (int) $rawOperationTimeout;
        }
        if (isset($options['validate-html-json-pointer'])) {
            $rawPointers = $options['validate-html-json-pointer'];
            $rawPointers = is_array($rawPointers) ? $rawPointers : [$rawPointers];
            foreach ($rawPointers as $pointer) {
                if (!is_string($pointer)) {
                    Help::error('--validate-html-json-pointer requires a string value.');
                }
                $htmlJsonPointers[] = $pointer;
            }
        }
        if (isset($options['html-json-pointer'])) {
            $rawPointers = $options['html-json-pointer'];
            $rawPointers = is_array($rawPointers) ? $rawPointers : [$rawPointers];
            foreach ($rawPointers as $pointer) {
                if (!is_string($pointer)) {
                    Help::error('--html-json-pointer requires a string value.');
                }
                $htmlJsonSelectionPointers[] = $pointer;
            }
        }

        if ($modelKey === null || $modelKey === '') {
            Help::error(
                "No model selected. Use --model to specify a model or --list-models to view available models."
            );
        }

        // From this point $modelKey is a string (Help::error() never returns)
        /** @var non-empty-string $modelKey */

        // Format must be provided explicitly and valid
        if ($format === null) {
            Help::error(
                "Missing required option --format. Allowed values: text, html, json, structured_json, linedJson."
            );
        }

        if (!in_array($format, ['text', 'html', 'json', 'structured_json', 'linedJson'], true)) {
            Help::error("Invalid format: '{$format}'. Allowed: text, html, json, structured_json, linedJson.");
        }
        /** @var 'text'|'html'|'json'|'structured_json'|'linedJson' $format */

        if (!in_array($htmlTransport, ['document', 'segments'], true)) {
            Help::error("Invalid HTML transport: '{$htmlTransport}'. Allowed: document, segments.");
        }
        if ($htmlTransport === 'segments') {
            if ($format === 'html' && $htmlJsonSelectionPointers !== []) {
                Help::error('--html-json-pointer requires a JSON format with --html-transport=segments.');
            }
            if ($format === 'structured_json') {
                Help::error('--html-transport=segments is incompatible with structured_json.');
            }
            if (in_array($format, ['json', 'linedJson'], true) && $htmlJsonSelectionPointers === []) {
                Help::error('--html-transport=segments with JSON requires at least one --html-json-pointer.');
            }
            if (!in_array($format, ['html', 'json', 'linedJson'], true)) {
                Help::error('--html-transport=segments requires --format=html, json, or linedJson.');
            }
        } elseif ($htmlJsonSelectionPointers !== []) {
            Help::error('--html-json-pointer requires --html-transport=segments.');
        }
        if ($htmlSegmentBatchSize !== null && $htmlTransport !== 'segments') {
            Help::error('--html-segment-batch-size requires --html-transport=segments.');
        }
        if ($textSegmentBatchSize !== null && $textTransport !== 'segments') {
            Help::error('--text-segment-batch-size requires --text-transport=segments.');
        }
        if (
            $segmentBatchMaxBytes !== null
            && $textTransport !== 'segments'
            && $htmlTransport !== 'segments'
        ) {
            Help::error(
                '--segment-batch-max-bytes requires --text-transport=segments or --html-transport=segments.'
            );
        }
        if ($textSegmentBatchSize !== null && $htmlSegmentBatchSize !== null) {
            Help::error('--text-segment-batch-size cannot be combined with --html-segment-batch-size.');
        }
        if ($checkpointPath !== null) {
            if ($textTransport !== 'segments' && $htmlTransport !== 'segments') {
                Help::error('--checkpoint requires --text-transport=segments or --html-transport=segments.');
            }
            if ($textSegmentBatchSize === null && $htmlSegmentBatchSize === null && $segmentBatchMaxBytes === null) {
                Help::error(
                    '--checkpoint requires --text-segment-batch-size, --html-segment-batch-size, '
                    . 'or --segment-batch-max-bytes.'
                );
            }
        }

        if ($htmlJsonPointers !== [] && !in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
            Help::error('--validate-html-json-pointer requires a JSON format.');
        }
        if ($htmlJsonSelectionPointers !== [] && !in_array($format, ['json', 'linedJson'], true)) {
            Help::error('--html-json-pointer requires json or linedJson format.');
        }
        if ($htmlTransport === 'segments' && isset($options['response-schema'])) {
            Help::error('--response-schema is incompatible with --html-transport=segments.');
        }

        if (!in_array($textTransport, ['document', 'segments'], true)) {
            Help::error("Invalid text transport: '{$textTransport}'. Allowed: document, segments.");
        }
        if ($textTransport === 'segments' && $format !== 'text') {
            Help::error('--text-transport=segments requires --format=text.');
        }
        if ($textTransport === 'segments' && isset($options['response-schema'])) {
            Help::error('--response-schema is incompatible with --text-transport=segments.');
        }

        if ($checkpointPath !== null) {
            $protected = [];
            if ($sourceFile !== null) {
                $protected['--source'] = $sourceFile;
                $protected['prepared output'] = $sourceFile . '.prepared';
            }
            if ($outFile !== null && $outFile !== '') {
                $protected['--translated'] = $outFile;
            }
            foreach ([$checkpointPath, $checkpointPath . '.lock'] as $statePath) {
                foreach ($protected as $label => $protectedPath) {
                    if (self::pathsAlias($statePath, $protectedPath)) {
                        Help::error('--checkpoint and its lock must not overwrite ' . $label . '.');
                    }
                }
            }
        }


        if (!$registry->has($modelKey)) {
            Help::error(
                "Unknown model key: {$modelKey}. Use --list-models to view available models."
            );
        }

        if (!$registry->getEndpoint($modelKey)) {
            Help::error(
                "Model {$modelKey} missing required configuration."
            );
        }

        // Load API key
        $envVar  = $registry->getAuthEnv($modelKey);
        $apiKey  = $envVar ? (self::getEnvironmentValue($envVar) ?: '') : '';
        $helpUrl = $registry->getHelpUrl($modelKey) ?? '';

        if (!$apiKey) {
            Help::error(
                "API key not found for {$modelKey}.\n" .
                "Please set environment variable: \${$envVar}\n" .
                "You can generate a key at: {$helpUrl}"
            );
        }

        // Parse --variables into key=value overrides
        $cliVars = [];
        if (isset($options['variables']) && is_string($options['variables']) && $options['variables'] !== '') {
            foreach (explode(',', $options['variables']) as $pair) {
                $pair = trim($pair);
                if ($pair == '') {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $pair, 2)) + [null, null];
                if ($k !== null && $v !== null) {
                    $cliVars[$k] = $v;
                } else {
                    Help::error("Invalid --variables format: '{$pair}'");
                }
            }
        }

        try {
            ModelVariables::validate($cliVars);
            $filterManager = new FilterManager($filters);
        } catch (\InvalidArgumentException $exception) {
            Help::error($exception->getMessage());
        }
        if ($htmlTransport === 'segments' && array_key_exists('response_schema', $cliVars)) {
            Help::error('--response-schema is incompatible with --html-transport=segments.');
        }
        if ($textTransport === 'segments' && array_key_exists('response_schema', $cliVars)) {
            Help::error('--response-schema is incompatible with --text-transport=segments.');
        }

        // Handle input

        // in interactive mode without --source, warn user about STDIN and EOF
        if ($sourceFile === null && function_exists('stream_isatty') && stream_isatty(STDIN)) {
            Help::warning("Reading from STDIN; press Ctrl-D to finish input and continue.");
        }

        if ($format === 'structured_json' && !isset($options['response-schema'])) {
            Help::error('structured_json requires --response-schema=FILE.');
        }
        if (isset($options['response-schema'])) {
            if (!\in_array($format, ['json', 'structured_json', 'linedJson'], true)) {
                Help::error('--response-schema requires a JSON format.');
            }
            if (!\in_array($registry->get($modelKey)['vendor'] ?? null, ['openai', 'google'], true)) {
                Help::error('Native JSON schema output is supported only by OpenAI and Google.');
            }
            $schemaPath = $options['response-schema'];
            $schemaJson = \is_string($schemaPath) ? @\file_get_contents($schemaPath) : false;
            if ($schemaJson === false) {
                Help::error('Cannot read --response-schema file.');
            }
            try {
                $schema = \json_decode($schemaJson, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                Help::error('Invalid JSON in --response-schema file: ' . $exception->getMessage());
            }
            if (!$schema instanceof \stdClass || \get_object_vars($schema) === []) {
                Help::error('--response-schema must contain a nonempty JSON Schema object.');
            }
            $cliVars['response_schema'] = \get_object_vars($schema);
        }

        // read input text (file or STDIN)
        if ($sourceFile !== null) {
            if (!is_readable($sourceFile)) {
                Help::error("Cannot read source file: {$sourceFile}");
            }
            $text = file_get_contents($sourceFile);
            if ($text === false) {
                Help::error("Failed to read source file: {$sourceFile}");
            }
        } else {
            // read whole STDIN safely
            $stdin = fopen('php://stdin', 'r');
            $text = $stdin !== false ? (stream_get_contents($stdin) ?: '') : '';
            if ($stdin !== false) {
                fclose($stdin);
            }
            if (trim($text) === '') {
                Help::error(
                    "No input provided.\n\n" .
                    "Please specify an input file via --source, or pipe text into stdin."
                );
            }
        }

        // Validate non-empty input
        if (trim($text) === "") {
            Help::error(
                "No input provided.\n\n" .
                "Please specify an input file via --source, or pipe text into stdin."
            );
        }

        // Dry-run: prepare placeholders and exit
        if ($dryRun) {
            $validationPointers = array_values(array_unique(array_merge(
                $htmlJsonPointers,
                $htmlJsonSelectionPointers
            )));
            if ($validate && $validationPointers !== []) {
                try {
                    new JsonHtmlValidator($text, $validationPointers);
                } catch (\Throwable $exception) {
                    Help::error($exception->getMessage());
                }
            }
            try {
                $segmentDocument = null;
                if ($htmlTransport === 'segments' && in_array($format, ['json', 'linedJson'], true)) {
                    $jsonDocument = new JsonHtmlDocument($text, $htmlJsonSelectionPointers, $filters);
                    $prepared = $jsonDocument->getPrepared();
                    $segmentDocument = $jsonDocument;
                } else {
                    $prepared = $filterManager->apply($text);
                    if ($htmlTransport === 'segments') {
                        $segmentDocument = new HtmlTextDocument($prepared);
                    }
                    if ($textTransport === 'segments') {
                        $segmentDocument = new TextDocument($prepared);
                    }
                }
                if ($segmentBatchMaxBytes !== null && $segmentDocument !== null) {
                    new SegmentPlan(
                        $segmentDocument->getJson(),
                        $textSegmentBatchSize ?? $htmlSegmentBatchSize,
                        $segmentBatchMaxBytes
                    );
                }
            } catch (\Throwable $exception) {
                Help::error($exception->getMessage());
            }
            if ($sourceFile !== null) {
                if (@file_put_contents($sourceFile . '.prepared', $prepared) !== strlen($prepared)) {
                    Help::error("Failed to write prepared file: {$sourceFile}.prepared");
                }
                Help::info("Dry-run: saved prepared file as {$sourceFile}.prepared");
            } else {
                echo $prepared;
            }
            exit(0);
        }

        // Read optional proxy setting (CLI flag has priority)
        $proxy = isset($options['proxy']) && is_string($options['proxy'])
                   ? $options['proxy']
                   : null;

        if ($proxy === null) {
            $envProxy = getenv('BBLSLUG_PROXY');
            $proxy = $envProxy === false ? null : (string)$envProxy;
        }

        // Collect any model-specific variables
        $variables = [];
        foreach ($registry->getVariables($modelKey) as $name => $envVar) {
            $val = self::getEnvironmentValue($envVar);
            if ($val === false) {
                Help::error("Missing required env var {$envVar} for model {$modelKey}");
            }
            $variables[$name] = $val;
        }
        // Override by CLI
        $variables = array_merge($variables, $cliVars);

        // Feedback handler for progress messages.
        /**
         * @param 'info'|'warning'|'error' $level
         * @return void
         */
        $feedback = function (string $message, string $level = 'info'): void {
            switch ($level) {
                case 'warning':
                    Help::warning($message);
                    break;
                case 'error':
                    Help::error($message);
                    // No break because exit already performed in Help::error
                default:
                    Help::info($message);
            }
        };

        // Perform translation
        /** @var array{
         *   original:string, prepared:string, result:string,
         *   httpStatus:int, debugRequest:string, debugResponse:string,
         *   rawResponseBody:string,
         *   consumed: array<string,mixed>,
         *   lengths: array{original:int, prepared:int, translated:int},
         *   filterStats: array<int, array{filter:string, count:int}>
         * } $res
         */
        $res = [
            'original' => '',
            'prepared' => '',
            'result' => '',
            'httpStatus' => 0,
            'debugRequest' => '',
            'debugResponse' => '',
            'rawResponseBody' => '',
            'consumed' => [],
            'lengths' => [
                'original' => 0,
                'prepared' => 0,
                'translated' => 0
            ],
            'filterStats' => [],
        ];
        try {
            $res = Bblslug::translate(
                apiKey: (string)$apiKey,
                format: $format,
                modelKey: $modelKey,
                text: $text,
                context: $context,
                dryRun: $dryRun,
                filters: $filters,
                onFeedback: $verbose ? $feedback : null,
                promptKey: $promptKey,
                proxy: $proxy,
                sourceLang: $sourceLang,
                targetLang: $targetLang,
                validate: $validate,
                validateEmbeddedJson: isset($options['validate-embedded-json']),
                variables: $variables,
                verbose: $verbose,
                validateHtmlJsonPointers: $htmlJsonPointers,
                htmlJsonPointers: $htmlJsonSelectionPointers,
                htmlTransport: $htmlTransport,
                htmlSegmentBatchSize: $htmlSegmentBatchSize,
                textTransport: $textTransport,
                textSegmentBatchSize: $textSegmentBatchSize,
                segmentBatchMaxBytes: $segmentBatchMaxBytes,
                operationTimeoutSeconds: $operationTimeoutSeconds,
                checkpointPath: $checkpointPath,
            );
        } catch (\Throwable $e) {
            Help::error($e->getMessage());
        }

        // at this point $dryRun already exited above, so only $verbose matters
        if (PHP_SAPI === 'cli' && ($verbose)) {
            file_put_contents('php://stderr', $res['debugRequest'], FILE_APPEND);
            file_put_contents('php://stderr', $res['debugResponse'], FILE_APPEND);
        }

        if ($res['httpStatus'] >= 400) {
            Help::error(
                "HTTP {$res['httpStatus']} error from request: {$res['rawResponseBody']}"
            );
        }

        // Write output (file or STDOUT)
        if ($outFile !== null) {
            if (@file_put_contents($outFile, $res['result']) !== strlen($res['result'])) {
                Help::error("Failed to write translation file: {$outFile}");
            }
            Help::info("Translation complete: {$outFile}");
        } else {
            echo $res["result"];
        }

        // emit stats
        $bold = "\033[1m";
        $reset = "\033[0m";
        $lh    = $res['lengths'];
        $stderr = fopen('php://stderr', 'w');
        if ($stderr === false) {
            // As a last resort, write to STDOUT (shouldn't normally happen)
            echo $reset;
            return;
        }
        // Characters processed visualization
        fwrite($stderr, "\n{$bold}Characters processed:{$reset}\n");
        fwrite($stderr, "\t{$bold}Original:{$reset}    {$lh['original']}\n");
        fwrite($stderr, "\t{$bold}Prepared:{$reset}    {$lh['prepared']}\n");
        fwrite($stderr, "\t{$bold}Translated:{$reset}  {$lh['translated']}\n");
        // Filter statistics visualization
        fwrite($stderr, "\n{$bold}Filter statistics:{$reset}\n");
        if (empty($res['filterStats'])) {
            fwrite($stderr, "\t(no filters applied)\n");
        } else {
            foreach ($res['filterStats'] as $stat) {
                fwrite($stderr, "\t{$bold}{$stat['filter']}:{$reset}\t{$stat['count']} placeholder(s)\n");
            }
        }
        // Usage visualization
        /** @var array<string, array{total?: int, breakdown?: array<string,int>}> $consumed */
        $consumed = $checkpointPath !== null ? ($res['consumedThisRun'] ?? []) : $res['consumed'];

        if ($checkpointPath !== null) {
            fwrite($stderr, 'Checkpoint packets reused: ' . ($res['reusedBatches'] ?? 0) . "\n");
        }
        $usageTitle = $checkpointPath !== null ? 'Usage metrics (this run)' : 'Usage metrics';
        fwrite($stderr, "\n{$bold}{$usageTitle}:{$reset}\n");
        if (empty($consumed)) {
            fwrite($stderr, "\t(not provided)\n");
        } else {
            foreach ($consumed as $category => $metrics) {
                // Category header, e.g. "Tokens:"
                fwrite($stderr, "\t" . ucfirst((string)$category) . ":\n");
                if (isset($metrics['total'])) {
                    $total = (int) $metrics['total'];
                    fwrite($stderr, sprintf("\t\t%-12s %d\n", 'Total:', $total));
                    $sepLen = 12 + 1 + strlen((string) $total);
                    $sep = str_repeat('-', $sepLen);
                    fwrite($stderr, "\t\t{$sep}\n");
                }
                // Breakdown lines, e.g. Prompt, Completion
                if (isset($metrics['breakdown'])) {
                    /** @var array<string,int> $bd */
                    $bd = $metrics['breakdown'];
                    foreach ($bd as $label => $cnt) {
                        fwrite($stderr, sprintf("\t\t%-12s %d\n", ucfirst((string)$label) . ':', (int)$cnt));
                    }
                }
            }
        }

        fwrite($stderr, $reset);
        fclose($stderr);
    }

    /**
     * Read a prefixed environment variable first, then its original name.
     *
     * @return string|false
     */
    private static function getEnvironmentValue(string $name)
    {
        $prefixed = getenv('BBLSLUG_' . $name);
        if ($prefixed !== false && $prefixed !== '') {
            return $prefixed;
        }

        return getenv($name);
    }

    /** Resolve an existing path, or resolve its parent and retain its basename. */
    private static function pathsAlias(string $left, string $right): bool
    {
        if (self::canonicalPath($left) === self::canonicalPath($right)) {
            return true;
        }
        $leftStat = @stat($left);
        $rightStat = @stat($right);
        return $leftStat !== false && $rightStat !== false
            && $leftStat['dev'] === $rightStat['dev'] && $leftStat['ino'] === $rightStat['ino'];
    }

    private static function canonicalPath(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved !== false) {
            return $resolved;
        }
        $directory = dirname($path);
        $parent = realpath($directory);
        if ($parent === false) {
            $parent = str_starts_with($directory, DIRECTORY_SEPARATOR)
                ? $directory
                : getcwd() . DIRECTORY_SEPARATOR . $directory;
        }
        return rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
    }
}
