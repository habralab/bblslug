<?php

namespace Babelium;

use Babelium\Filters\FilterManager;
use Babelium\Help;
use Babelium\LLMClient;
use Babelium\Models\ModelRegistry;

class Babelium
{
    public static function runFromCli()
    {
        /**
         * Translation CLI Interface
         */

        // Load CLI arguments
        $options = getopt("", [
            "source:", "translated:", "format:", "filters:", "dry-run", "model:", "list-models", "verbose", "help"
        ]);

        $isDryRun = isset($options["dry-run"]);
        $isVerbose = isset($options["verbose"]);
        $listModels = isset($options["list-models"]);
        $modelKey = $options["model"] ?? "deepl:free";

        if (isset($options["help"])) {
            Help::printHelp(0);
        }

        // Initialize registry
        $registry = new ModelRegistry();

        if ($listModels) {
            Help::printModelList($registry);
            exit(0);
        }

        if (!$registry->has($modelKey)) {
            Help::error("Unknown model key: $modelKey");
        }

        if ($modelKey !== "deepl:free") {
            Help::warning("Model $modelKey is defined but not yet supported.");
            exit(1);
        }

        $model = $registry->get($modelKey);
        $endpoint = $model['endpoint'] ?? null;
        $format = $options["format"] ?? null;
        $sourceFile = $options["source"] ?? null;
        $translatedFile = $options["translated"] ?? null;

        if (!$sourceFile || !$translatedFile || !$format) {
            Help::error("Missing required options.", null);
            Help::printHelp();
        }

        $apiKey = getenv($model['requirements']['auth']['env'] ?? '');
        if (!$apiKey) {
            $help = $model['requirements']['auth']['help_url'] ?? 'https://example.com';
            echo "❌ API key not found for $modelKey.\n";
            echo "   Please set environment variable: " . $model['requirements']['auth']['env'] . "\n";
            echo "   You can generate a key at: $help\n";
            exit(1);
        }

        // Determine if DeepL Free API is used (for endpoint selection)
        $isFree = $modelKey === 'deepl:free';

        // Read file
        $text = file_get_contents($sourceFile);
        if ($text === false) {
            Help::error("Failed to read file: $sourceFile");
        }
        $originalLength = mb_strlen($text);

        // Apply placeholder filters
        $filters = $options['filters'] ?? '';
        $filterList = array_filter(array_map('trim', explode(',', $filters)));

        $filterManager = new FilterManager($filterList);
        $prepared = $filterManager->apply($text);
        $preparedLength = mb_strlen($prepared);

        // Save prepared version if dry-run
        if ($isDryRun) {
            file_put_contents($sourceFile . ".prepared", $prepared);
            echo "💾 Dry-run: saved prepared file as {$sourceFile}.prepared\n";
        }

        // Build translation payload
        $payload = [
            "auth_key" => $apiKey,
            "text" => $prepared,
            "target_lang" => "EN",
            "formality" => "prefer_more"
        ];

        // HTML-specific translation flags
        if ($format === "html") {
            $payload["tag_handling"] = "html";
            $payload["preserve_formatting"] = "1";
            $payload["outline_detection"] = "1";
            $payload["text"] = "<!-- Translate as a professional technical translator. -->\n" . $payload["text"];
        }

        // Perform translation or simulate it
        $response = LLMClient::send($model, $payload, $apiKey, $isDryRun, $isVerbose);

        // Handle dry-run or parse response
        if ($isDryRun) {
            Help::info("Skipping translation (dry-run mode)");
            $translated = $prepared;
        } else {
            $result = json_decode($response, true);

            if (!isset($result["translations"][0]["text"])) {
                Help::error("Translation failed. Response: $response");
            }

            $translated = $result["translations"][0]["text"];
        }

        // Restore placeholders
        $translated = $filterManager->restore($translated);

        $finalLength = mb_strlen($translated);
        file_put_contents($translatedFile, $translated);

        // Final output
        Help::info("Translation complete: {$translatedFile}");

        $bold = "\033[1m";
        $reset = "\033[0m";

        echo $reset;
        echo "Characters processed:\n";
        echo "\t{$bold}Original:{$reset}    {$originalLength}\n";
        echo "\t{$bold}Prepared:{$reset}    {$preparedLength}\n";
        echo "\t{$bold}Translated:{$reset}  {$finalLength}\n";
        echo "\n";
        echo "Filter statistics:\n";
        foreach ($filterManager->getStats() as $stat) {
            echo "\t{$bold}{$stat['filter']}:{$reset}\t{$stat['count']} placeholder(s)\n";
        }

        echo $reset;
    }
}
