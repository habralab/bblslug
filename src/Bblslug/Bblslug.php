<?php

namespace Bblslug;

use Bblslug\Filters\FilterManager;
use Bblslug\Help;
use Bblslug\HttpClient;
use Bblslug\Models\ModelRegistry;

class Bblslug
{
    /**
     * Translate text or HTML via any registered model.
     * @param string      $text       The source text or HTML.
     * @param string      $modelKey   Model ID (e.g. "deepl:pro").
     * @param string      $format     "text" or "html".
     * @param string      $apiKey     API key for the model.
     * @param string[]    $filters    Placeholder filters to apply.
     * @param bool        $dryRun     If true: prepare placeholders only.
     * @param bool        $verbose    If true: include request/response logs.
     * @param string|null $sourceLang Optional source language code.
     * @param string|null $targetLang Optional target language code.
     * @param string|null $context    Optional context prompt.
     *
     * @return array{
     *     original: string,
     *     prepared: string,
     *     result: string,
     *     httpStatus: int,
     *     debugRequest: string,
     *     debugResponse: string,
     *     rawResponseBody: string,
     *     lengths: array{original:int,prepared:int,translated:int},
     *     filterStats: array<array{filter:string,count:int}>
     * }
     *
     * @throws \InvalidArgumentException If inputs are invalid.
     * @throws \RuntimeException On HTTP or parsing errors.
     */
    public static function translate(
        string  $text,
        string  $modelKey,
        string  $format,
        string  $apiKey,
        array   $filters    = [],
        bool    $dryRun     = false,
        bool    $verbose    = false,
        ?string $sourceLang = null,
        ?string $targetLang = null,
        ?string $context    = null
    ): array {
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

        // Apply placeholder filters
        $filterManager = new FilterManager($filters);
        $prepared      = $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);

        // Build request
        $req = $driver->buildRequest(
            $model,
            $prepared,
            ['format'=>$format, 'dryRun'=>$dryRun, 'verbose'=>$verbose]
        );

        // API auth key handling
        $auth = $model['requirements']['auth'] ?? null;
        if ($auth) {
            if (!$apiKey) {
               throw new \InvalidArgumentException("API key is required for {$modelKey}");
            }
            $keyName = $auth['key_name'];
            $prefix  = $auth['prefix'] ? $auth['prefix'].' ' : '';
            switch ($auth['type'] ?? 'form') {
                case 'header':
                    $req['headers'][] = "{$keyName}: {$prefix}{$apiKey}";
                    break;
                case 'form':
                    $req['body'] .= '&'.urlencode($keyName).'='.urlencode($apiKey);
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
            method:  'POST',
            url:     $req['url'],
            headers: $req['headers'],
            body:    $req['body'],
            verbose: $verbose,
            dryRun:  $dryRun,
            maskPatterns: [
                $apiKey,
            ]
        );

        $httpStatus     = $http['status'];
        $debugRequest   = $http['debugRequest'];
        $debugResponse  = $http['debugResponse'];
        $raw            = $http['body'];

        // Parse response
        if ($dryRun) {
            $translated = $prepared;
        } else {
            if ($httpStatus >= 400) {
                $msg  = "HTTP {$httpStatus} error from {$req['url']}: {$raw}\n\n";
                $msg .= $debugRequest . $debugResponse;
                throw new \RuntimeException($msg);
            } else {
                $translated = $driver->parseResponse($model, $raw);
            }
        }

        // Restore placeholders
        $result = $filterManager->restore($translated);
        $finalLength = mb_strlen($result);

        // Collect stats
        $filterStats = $filterManager->getStats();

        return [
            'original'     => $text,
            'prepared'     => $prepared,
            'result'       => $result,
            'httpStatus'        => $httpStatus,
            'debugRequest'      => $debugRequest,
            'debugResponse'     => $debugResponse,
            'rawResponseBody'   => $raw,
            'lengths'      => [
                'original'   => $originalLength,
                'prepared'   => $preparedLength,
                'translated' => $finalLength,
            ],
            'filterStats'  => $filterStats,
        ];
    }

    /**
     * CLI translation entrypoint.
     *
     * Parse CLI flags, read input, call {@see translate()}, and output results.
     * Also handles `--help`, `--list-models`, `--dry-run`, `--verbose` and emits summary stats to STDERR.
     *
     * @return void
     */
    public static function runFromCli()
    {
        // Load CLI arguments
        $options = getopt("", [
            "context:",      // extra context prompt
            "dry-run",       // placeholders only
            "filters:",      // comma-separated filter list
            "format:",       // "text" or "html"
            "help",          // show help and exit
            "list-models",   // show models and exit
            "model:",        // model key
            "source:",       // input file (default = STDIN)
            "source-lang:",  // override source language
            "target-lang:",  // override target language
            "translated:",   // output file (default = STDOUT)
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
            Help::printModelList($registry);
            exit(0);
        }

        // Extract params
        $context    = $options['context']     ?? null;
        $dryRun     = isset($options['dry-run']);
        $format     = $options['format']      ?? null;
        $filters    = isset($options['filters'])
                        ? array_map('trim', explode(',', $options['filters']))
                        : [];
        $modelKey   = $options['model']       ?? null;
        $outFile    = $options['translated']  ?? null;
        $sourceFile = $options['source']      ?? null;
        $sourceLang = $options['source-lang'] ?? null;
        $targetLang = $options['target-lang'] ?? null;
        $verbose    = isset($options['verbose']);

        // Validate model
        if (!$modelKey) {
            Help::error(
                "No model selected.\n\n" .
                "Please provide a model with --model.\n" .
                "Example:\n" .
                "  --model=vendor:name\n\n" .
                "Use --list-models to view available models."
            );
        }

        if (!$registry->has($modelKey)) {
            Help::error(
                "Unknown model key: {$modelKey}\n\n" .
                "Use --list-models to view available models."
            );
        }

        if (!$registry->getEndpoint($modelKey)) {
            Help::error(
                "Model {$modelKey} missing required configuration.\n\n" .
                "Check the registry with --list-models."
            );
        }

        // Validate format
        if (!in_array($format, ['text','html'], true)) {
            Help::error("Invalid format: '{$format}'. Allowed: text, html.");
        }

        // load API key from env
        $envVar   = $registry->getAuthEnv($modelKey);
        $apiKey   = $envVar ? getenv($envVar) : '';
        $helpUrl  = $registry->getHelpUrl($modelKey) ?? '';
        if (!$apiKey) {
            Help::error(
                "API key not found for {$modelKey}.\n\n" .
                "Please set environment variable: \${$envVar}\n" .
                "You can generate a key at: {$helpUrl}"
            );
        }

        // in interactive mode without --source, warn user about STDIN and EOF
        if ($sourceFile === null && function_exists('stream_isatty') && stream_isatty(STDIN)) {
            Help::warning("Reading from STDIN; press Ctrl-D to finish input and continue.");
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
            // read from stdin
            $text = '';
            // if stdin is a terminal and no --source, error immediately
            if (ftell(STDIN) === 0 && posix_isatty(STDIN)) {
                Help::error(
                    "No input provided.\n\n" .
                    "Please specify an input file via --source, or pipe text into stdin."
                );
            }
            while (!feof(STDIN)) {
                $text .= fgets(STDIN);
            }
            // still empty?
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
            $prepared = (new FilterManager($filters))->apply($text);
            if ($sourceFile !== null) {
                file_put_contents($sourceFile . '.prepared', $prepared);
                Help::info("Dry-run: saved prepared file as {$sourceFile}.prepared");
            } else {
                echo $prepared;
            }
            exit(0);
        }

        // Perform translation
        $res = [];
        try {
            $res = self::translate(
                text:       $text,
                modelKey:   $modelKey,
                format:     $format,
                apiKey:     $apiKey,
                filters:    $filters,
                dryRun:     $dryRun,
                verbose:    $verbose,
                targetLang: $targetLang,
                sourceLang: $sourceLang,
                context:    $context
            );
        } catch (\Throwable $e) {
            Help::error($e->getMessage());
        }

        if (PHP_SAPI==='cli' && ($verbose || $dryRun)) {
            file_put_contents('php://stderr', $res['debugRequest'],  FILE_APPEND);
            file_put_contents('php://stderr', $res['debugResponse'], FILE_APPEND);
        }

        if (!empty($res['httpStatus']) && $res['httpStatus'] >= 400) {
            Help::error(
                "HTTP {$res['httpStatus']} error from request: {$res['rawResponseBody']}"
            );
        }

        // Write output (file or STDOUT)
        if ($outFile !== null) {
            file_put_contents($outFile, $res['result']);
            Help::info("Translation complete: {$outFile}");
        } else {
            echo $res["result"];
        }

        // emit stats
        $bold = "\033[1m";
        $reset = "\033[0m";
        $lh   = $res['lengths'];
        $stderr = fopen('php://stderr','w');
        fwrite($stderr, "{$reset}Characters processed:\n");
        fwrite($stderr, "\t{$bold}Original:{$reset}    {$lh['original']}\n");
        fwrite($stderr, "\t{$bold}Prepared:{$reset}    {$lh['prepared']}\n");
        fwrite($stderr, "\t{$bold}Translated:{$reset}  {$lh['translated']}\n\n");
        fwrite($stderr, "Filter statistics:\n");
        if (empty($res['filterStats'])) {
            fwrite($stderr, "\t(no filters applied)\n");
        } else {
            foreach ($res['filterStats'] as $stat) {
                fwrite($stderr, "\t{$bold}{$stat['filter']}:{$reset}\t{$stat['count']} placeholder(s)\n");
            }
        }
        fwrite($stderr, $reset);
        fclose($stderr);
    }
}
