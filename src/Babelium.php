<?php

namespace Babelium;

class Babelium
{
    public static function runFromCli()
    {
        /**
         * DeepL Translator Wrapper
         * Supports: --format=text|html, --source, --translated, [--dry-run], [--free], [--verbose]
         * Uses placeholders to preserve structural elements and links.
         */

        function print_help() {
            echo "Usage: php translate.php --format=(text|html) --source=filename --translated=filename [--dry-run] [--free] [--verbose]\n";
            exit(1);
        }

        // Load CLI arguments
        $options = getopt("", ["source:", "translated:", "format:", "dry-run", "free", "verbose"]);

        $sourceFile = $options["source"] ?? null;
        $translatedFile = $options["translated"] ?? null;
        $format = $options["format"] ?? null;

        $isDryRun = isset($options["dry-run"]);
        $isFree = isset($options["free"]);
        $isVerbose = isset($options["verbose"]);

        if (!$sourceFile || !$translatedFile || !$format) {
            echo "❌ Missing required options.\n";
            print_help();
        }

        // Load API key from env
        $apiKey = getenv("DEEPL_API_KEY");
        if (!$apiKey) {
            die("❌ DEEPL_API_KEY environment variable not set.\n");
        }

        // Read file
        $text = file_get_contents($sourceFile);
        if ($text === false) {
            die("❌ Failed to read file: $sourceFile\n");
        }
        $originalLength = mb_strlen($text);

        // Placeholders for various elements
        $preBlocks = $codeBlocks = $linkAttrs = $urls = [];
        $prepared = $text;

        // HTML format filters
        if ($format === "html") {
            // <pre> blocks
            preg_match_all('/<pre.*?>.*?<\/pre>/is', $prepared, $matches);
            foreach ($matches[0] as $i => $block) {
                $key = "@P{$i}@";
                $preBlocks[$key] = $block;
                $prepared = str_replace($block, $key, $prepared);
            }

            // <code> blocks without Cyrillic
            preg_match_all('/<code>(.*?)<\/code>/is', $prepared, $matches);
            foreach ($matches[0] as $i => $full) {
                $inner = $matches[1][$i];
                if (!preg_match('/[а-яА-ЯёЁ]/u', $inner)) {
                    $key = "@C{$i}@";
                    $codeBlocks[$key] = $full;
                    $prepared = str_replace($full, $key, $prepared);
                }
            }

            // link attributes (href, src, etc.)
            $prepared = preg_replace_callback('/\s+(href|src|action|poster|formaction|data)\s*=\s*"[^"]*"/i', function($m) use (&$linkAttrs) {
                $key = "@L" . count($linkAttrs) . "@";
                $linkAttrs[$key] = $m[0];
                return $key;
            }, $prepared);
        }

        // In all formats: extract raw URLs
        $prepared = preg_replace_callback('/\b(?:https?|ftp|mailto):\/\/[^\s"<>()]+/i', function($m) use (&$urls) {
            $key = "@U" . count($urls) . "@";
            $urls[$key] = $m[0];
            return $key;
        }, $prepared);

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

        // Perform translation or skip in dry-run
        if ($isDryRun) {
            echo "🧪 Skipping translation (dry-run mode)\n";
            $translated = $prepared;
        } else {
            $endpoint = $isFree ? "https://api-free.deepl.com/v2/translate" : "https://api.deepl.com/v2/translate";
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);

            if (!isset($result["translations"][0]["text"])) {
                die("❌ Translation failed. Response: $response\n");
            }
            $translated = $result["translations"][0]["text"];
        }

        // Restore placeholders
        foreach ($preBlocks as $k => $v) {
            $translated = str_replace([$k, htmlspecialchars($k)], $v, $translated);
        }
        foreach ($codeBlocks as $k => $v) {
            $translated = str_replace([$k, htmlspecialchars($k)], $v, $translated);
        }
        foreach ($linkAttrs as $k => $v) {
            $translated = str_replace([$k, htmlspecialchars($k)], $v, $translated);
        }
        foreach ($urls as $k => $v) {
            $translated = str_replace([$k, htmlspecialchars($k)], $v, $translated);
        }

        $finalLength = mb_strlen($translated);
        file_put_contents($translatedFile, $translated);

        // Final output
        echo "✅ Translation complete: $translatedFile\n";
        echo "📊 Stats:\n";
        echo "  Original:   {$originalLength} characters\n";
        echo "  Prepared:   {$preparedLength} characters\n";
        echo "  Translated: {$finalLength} characters\n";
        if ($isVerbose) {
            echo "🔖 Placeholders replaced:\n";
            echo "  <pre>:     " . count($preBlocks) . "\n";
            echo "  <code>:    " . count($codeBlocks) . "\n";
            echo "  Links:     " . count($linkAttrs) . "\n";
            echo "  Raw URLs:  " . count($urls) . "\n";
        }
    }
}
