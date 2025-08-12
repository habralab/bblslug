<?php

declare(strict_types=1);

namespace Bblslug\Console;

use Bblslug\Bblslug;
use Bblslug\Console\Help;
use Bblslug\Filters\FilterManager;
use Bblslug\Models\ModelRegistry;

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
            "dry-run",       // placeholders only
            "filters:",      // comma-separated filter list
            "format:",       // "text", "html" or "json"
            "help",          // show help and exit
            "list-models",   // show models and exit
            "list-prompts",  // show available prompt templates and exit
            "model:",        // model key
            "no-validate",   // disable pre- and post-validation of container syntax
            "prompt-key:",   // prompt template key (from prompts.yaml)
            "proxy:",        // optional proxy URI
            "source:",       // input file (default = STDIN)
            "source-lang:",  // override source language
            "target-lang:",  // override target language
            "translated:",   // output file (default = STDOUT)
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
        $format     = isset($options['format']) && is_string($options['format'])
                        ? $options['format'] : null;
        $filters    = isset($options['filters']) && is_string($options['filters'])
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

        if ($modelKey === null || $modelKey === '') {
            Help::error(
                "No model selected. Use --model to specify a model or --list-models to view available models."
            );
        }

        // From this point $modelKey is a string (Help::error() never returns)
        /** @var non-empty-string $modelKey */

        // Format must be provided explicitly and valid
        if ($format === null) {
            Help::error("Missing required option --format. Allowed values: text, html, json.");
        }

        if (!in_array($format, ['text', 'html', 'json'], true)) {
            Help::error("Invalid format: '{$format}'. Allowed: text, html, json.");
        }
        /** @var 'text'|'html'|'json' $format */


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
        $apiKey  = $envVar ? (getenv($envVar) ?: '') : '';
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

        // Handle input

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
            $prepared = (new FilterManager($filters))->apply($text);
            if ($sourceFile !== null) {
                file_put_contents($sourceFile . '.prepared', $prepared);
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
            $val = getenv($envVar);
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
                variables: $variables,
                verbose: $verbose,
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
            file_put_contents($outFile, $res['result']);
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
        $consumed = $res['consumed'];

        fwrite($stderr, "\n{$bold}Usage metrics:{$reset}\n");
        if (empty($consumed)) {
            fwrite($stderr, "\t(not provided)\n");
        } else {
            foreach ($consumed as $category => $metrics) {
                // Category header, e.g. "Tokens:"
                fwrite($stderr, "\t" . ucfirst((string)$category) . ":\n");
                // Total line
                $total = isset($metrics['total']) ? (int)$metrics['total'] : 0;
                fwrite($stderr, sprintf("\t\t%-12s %d\n", 'Total:', $total));
                // Separator line of appropriate length
                $sepLen = 12 + 1 + strlen((string)$total);
                $sep    = str_repeat('-', $sepLen);
                fwrite($stderr, "\t\t{$sep}\n");
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
}
