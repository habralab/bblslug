<?php

namespace Bblslug;

use Bblslug\Filters\FilterManager;
use Bblslug\Help;
use Bblslug\LLMClient;
use Bblslug\Models\ModelRegistry;

class Bblslug
{
    /**
     * Programmatic translation entrypoint.
     *
     * @param string $text       Input text or HTML to translate.
     * @param string $modelKey   Identifier of the model, e.g. "deepl:free" or "openai:gpt-4o".
     * @param string $format     "text" or "html".
     * @param string $apiKey     API key for the selected model.
     * @param array  $filters    List of filter names, e.g. ['url','html_pre'].
     * @param bool   $dryRun     If true, skip actual API call and return prepared text.
     * @param bool   $verbose    If true, pass verbose flag to the LLM client.
     * @return array {
     *     @type string $original     Original input.
     *     @type string $prepared     Text after placeholder filters.
     *     @type string $result       Final translated text with placeholders restored.
     *     @type array  $lengths      Character counts: original, prepared, result.
     *     @type array  $filterStats  Stats per filter: [ ['filter'=>..., 'count'=>...], ... ].
     * }
     * @throws \InvalidArgumentException on bad inputs.
     * @throws \RuntimeException on translation failure.
     */
    public static function translate(
        string $text,
        string $modelKey,
        string $format,
        string $apiKey,
        array  $filters = [],
        bool   $dryRun  = false,
        bool   $verbose = false
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

        // Measure original length
        $originalLength = mb_strlen($text);

        // Apply placeholder filters
        $filterManager = new FilterManager($filters);
        $prepared      = $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);

        // Build payload
        $payload = [
            'auth_key'    => $apiKey,
            'text'        => $prepared,
            'target_lang' => 'EN',
            'formality'   => 'prefer_more',
        ]; // это надо упаковать в режистри
        if ($format === 'html') {
            $payload['tag_handling']     = 'html';
            $payload['preserve_formatting'] = '1';
            $payload['outline_detection']   = '1';
            // inject translator instruction
            $payload['text'] = "<!-- Translate as a professional technical translator. -->\n" //это надо бы упаковать в условия в режистри как промпт?
                              . $prepared;
        }

        // Perform (or skip) API call
        $response = LLMClient::send($model, $payload, $apiKey, $dryRun, $verbose);

        // Parse response
        if ($dryRun) {
            $translated = $prepared;
        } else {
            $data = json_decode($response, true);
            if (!isset($data['translations'][0]['text'])) {
                throw new \RuntimeException("Translation failed. Response: {$response}");
            }
            $translated = $data['translations'][0]['text'];
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
            'lengths'      => [
                'original'   => $originalLength,
                'prepared'   => $preparedLength,
                'translated' => $finalLength,
            ],
            'filterStats'  => $filterStats,
        ];
    }

    public static function runFromCli()
    {
        /**
         * Translation CLI Interface
         */

        // Load CLI arguments
        $options = getopt("", [
            "source:",      // optional: path to input file; if omitted, read from STDIN
            "translated:",  // optional: path to output file; if omitted, write to STDOUT
            "format:",      // required: "text" or "html"
            "filters:",     // optional: comma-separated list
            "dry-run",      // optional: prepare placeholders only
            "model:",       // required: model key
            "list-models",  // optional: show registry and exit
            "verbose",      // optional: pass-through to LLM client
            "help",         // optional: show help and exit
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
        $modelKey   = $options['model']      ?? null;
        $sourceFile = $options['source']     ?? null;
        $outFile    = $options['translated'] ?? null;
        $format     = $options['format']     ?? null;
        $filters    = isset($options['filters'])
                        ? array_map('trim', explode(',', $options['filters']))
                        : [];
        $dryRun     = isset($options['dry-run']);
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
        try {
            $res = self::translate(
                $text,
                $modelKey,
                $format,
                $apiKey,
                $filters,
                $dryRun,
                $verbose
            );
        } catch (\Throwable $e) {
            Help::error($e->getMessage());
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
