<?php

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
        $context    = $options['context'] ?? null;
        $dryRun     = isset($options['dry-run']);
        $format     = $options['format'] ?? null;
        $filters    = isset($options['filters'])
                        ? array_map('trim', explode(',', $options['filters']))
                        : [];
        $modelKey   = $options['model'] ?? null;
        $outFile    = $options['translated'] ?? null;
        $promptKey  = $options['prompt-key'] ?? 'translator';
        $sourceFile = $options['source'] ?? null;
        $sourceLang = $options['source-lang'] ?? null;
        $targetLang = $options['target-lang'] ?? null;
        $validate   = ! isset($options['no-validate']);
        $verbose    = isset($options['verbose']);

        if (!$modelKey) {
            Help::error(
                "No model selected. Use --model to specify a model or --list-models to view available models."
            );
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

        if (!in_array($format, ['text','html','json'], true)) {
            Help::error("Invalid format: '{$format}'. Allowed: text, html, json.");
        }

        // Load API key
        $envVar  = $registry->getAuthEnv($modelKey);
        $apiKey  = $envVar ? getenv($envVar) : '';
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
        if (!empty($options['variables'])) {
            foreach (explode(',', $options['variables']) as $pair) {
                list($k, $v) = array_map('trim', explode('=', $pair, 2)) + [null, null];
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

        // Read optional proxy setting (CLI flag has priority)
        $proxy = $options['proxy'] ?? getenv('BBLSLUG_PROXY') ?: null;

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
        $feedback = function (string $message, string $level = 'info'): void {
            switch ($level) {
                case 'warning':
                    Help::warning($message);
                    break;
                case 'error':
                    Help::error($message);
                    break;
                default:
                    Help::info($message);
            }
        };

        // Perform translation
        $res = [];
        try {
            $res = Bblslug::translate(
                apiKey: $apiKey,
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

        if (PHP_SAPI === 'cli' && ($verbose || $dryRun)) {
            file_put_contents('php://stderr', $res['debugRequest'], FILE_APPEND);
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
        $stderr = fopen('php://stderr', 'w');
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
        $consumed = $res['consumed'] ?? [];

        fwrite($stderr, "\n{$bold}Usage metrics:{$reset}\n");
        if (empty($consumed)) {
            fwrite($stderr, "\t(not provided)\n");
        } else {
            foreach ($consumed as $category => $metrics) {
                // Category header, e.g. "Tokens:"
                fwrite($stderr, "\t" . ucfirst($category) . ":\n");
                // Total line
                $total = $metrics['total'] ?? 0;
                fwrite($stderr, sprintf("\t\t%-12s %d\n", 'Total:', $total));
                // Separator line of appropriate length
                $sepLen = 12 + 1 + strlen((string)$total);
                $sep    = str_repeat('-', $sepLen);
                fwrite($stderr, "\t\t{$sep}\n");
                // Breakdown lines, e.g. Prompt, Completion
                if (!empty($metrics['breakdown']) && is_array($metrics['breakdown'])) {
                    foreach ($metrics['breakdown'] as $label => $cnt) {
                        fwrite($stderr, sprintf("\t\t%-12s %d\n", ucfirst($label) . ':', $cnt));
                    }
                }
            }
        }

        fwrite($stderr, $reset);
        fclose($stderr);
    }
}
