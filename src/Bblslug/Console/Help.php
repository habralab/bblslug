<?php

declare(strict_types=1);

namespace Bblslug\Console;

use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;

class Help
{
    public static function alert(string $label, string $message, string $color = "\033[0m", ?int $exitCode = null): void
    {
        if (defined('STDERR') && is_resource(STDERR)) {
            @fwrite(STDERR, "{$color}{$label}:\033[0m {$message}\n");
        }
        if ($exitCode !== null) {
            exit($exitCode);
        }
    }

    /**
     * Print error and terminate the process.
     *
     * @return never
     */
    public static function error(string $message, int $exitCode = 1)
    {
        self::alert("Error", $message, "\033[31m", $exitCode); // Red
        exit($exitCode);
    }

    public static function warning(string $message, ?int $exitCode = null): void
    {
        self::alert("Warning", $message, "\033[33m"); // Yellow
    }

    public static function info(string $message, ?int $exitCode = null): void
    {
        self::alert("Info", $message, "\033[36m"); // Cyan
    }

    // for execution with --help or without args
    public static function printHelp(?int $exitCode = 1): void
    {
        $bold = "\033[1m";
        $reset = "\033[0m";

        echo $reset;
        echo "Usage:\n";
        echo "\tvendor/bin/bblslug [options]\n";

        echo "\nOptions:\n";
        echo "\t{$bold}--context=TEXT{$reset}       Add translation context prompt\n";
        echo "\t{$bold}--checkpoint=FILE{$reset}    Resume segment batches from this optional checkpoint file\n";
        echo "\t{$bold}--dry-run{$reset}            Apply filters only; save SOURCE.prepared or write STDOUT\n";
        echo "\t{$bold}--filters=F1,F2,...{$reset}  Comma-separated filters to (e.g. url, html_pre, html_code)\n";
        echo "\t{$bold}--format=FORMAT{$reset}      Required: text, html, json, structured_json, or linedJson\n";
        echo "\t{$bold}--html-transport=MODE{$reset} HTML transport: document (default) or segments\n";
        echo "\t{$bold}--html-segment-batch-size=N{$reset} Segments per request "
            . "(positive integer; segments transport only)\n";
        echo "\t{$bold}--segment-batch-max-bytes=N{$reset} Maximum serialized segment packet size "
            . "in bytes (positive integer; segments transport only)\n";
        echo "\t{$bold}--html-json-pointer=POINTER{$reset} Translate HTML at a JSON Pointer "
            . "(repeatable; segments only)\n";
        echo "\t{$bold}--help{$reset}               Show this help message\n";
        echo "\t{$bold}--list-models{$reset}        Show available translation models grouped by vendor\n";
        echo "\t{$bold}--list-prompts{$reset}       Show available prompt templates\n";
        echo "\t{$bold}--model=MODEL_ID{$reset}     Required model (see --list-models)\n";
        echo "\t{$bold}--operation-timeout=N{$reset} Total translation operation deadline in seconds\n";
        echo "\t{$bold}--no-validate{$reset}        Disable container syntax and structure checks\n";
        echo "\t{$bold}--prompt-key=KEY{$reset}     Specify prompt template key (prompts.yaml)\n";
        echo "\t{$bold}--response-schema=FILE{$reset} JSON Schema for OpenAI/Google native JSON output\n";
        echo "\t{$bold}--proxy=URI{$reset}          Optional proxy URI (see examples) or set BBLSLUG_PROXY\n";
        echo "\t{$bold}--source=FILE{$reset}        Input file to translate (omit to read from STDIN)\n";
        echo "\t{$bold}--source-lang=LANG{$reset}   Source language code (e.g. EN, DE) - default autodetect\n";
        echo "\t{$bold}--text-transport=MODE{$reset} Text transport: document (default) or segments (text only)\n";
        echo "\t{$bold}--text-segment-batch-size=N{$reset} Segments per request "
            . "(positive integer; text segments transport only)\n";
        echo "\t{$bold}--target-lang=LANG{$reset}   Target language (e.g. EN, DE) - default EN\n";
        echo "\t{$bold}--translated=FILE{$reset}    Output file for translated content (omit to write to STDOUT)\n";
        echo "\t{$bold}--validate-embedded-json{$reset} Check JSON objects/arrays encoded inside string values\n";
        echo "\t{$bold}--validate-html-json-pointer=POINTER{$reset} Check HTML at JSON Pointer paths (repeatable)\n";
        echo "\t{$bold}--variables=K=V,...{$reset}  Comma-separated model-specific overrides (string values)\n";
        echo "\t{$bold}--verbose{$reset}            Show extra debug info after processing\n";

        echo "\nEnvironment:\n";
        echo "\tSet API keys via environment variables depending on the model:\n";
        echo "\t  export ANTHROPIC_API_KEY=\"...\" # for Anthropic (Claude)\n";
        echo "\t  export DEEPL_FREE_API_KEY=\"...\" # for DeepL Free\n";
        echo "\t  export DEEPL_PRO_API_KEY=\"...\" # for DeepL Pro\n";
        echo "\t  export GOOGLE_API_KEY=\"...\" # for Google (Gemini)\n";
        echo "\t  export OPENAI_API_KEY=\"...\" # for OpenAI (GPT)\n";
        echo "\t  export YANDEX_API_KEY=\"...\" && export YANDEX_FOLDER_ID=\"...\" # for Yandex (FM)\n";
        echo "\t  export XAI_API_KEY=\"...\" # for X.AI (Grok)\n";
        echo "\tA non-empty BBLSLUG_ prefixed credential takes precedence over its unprefixed name.\n";
        echo "\t  Example: BBLSLUG_OPENAI_API_KEY or BBLSLUG_YANDEX_FOLDER_ID\n";
        echo "\tTranslation and dry-run require the selected model's API key.\n";
        echo "\tModel-specific variables can be passed via --variables or read from env\n";
        echo "\n\tYou may also set a proxy globally via the BBLSLUG_PROXY environment variable:\n";
        echo "\t  export BBLSLUG_PROXY=\"http://localhost:3128\" # HTTP proxy\n";
        echo "\t  export BBLSLUG_PROXY=\"socks5h://127.0.0.1:9050\" # SOCKS5 proxy\n";
        echo "\n\tHTTP timeouts (positive integer seconds):\n";
        echo "\t  BBLSLUG_HTTP_CONNECT_TIMEOUT=10; BBLSLUG_HTTP_TIMEOUT=120 (defaults)\n";
        echo "\tHTTP requests are not automatically retried. CLI dry-run does not use HTTP.\n";
        echo "\tThe operation timeout covers all packets, including DeepL upload, polling, and download;\n";
        echo "\trequests are not retried.\n";

        echo "\nExamples:\n";
        echo "\tList available models:\n";
        echo "\tvendor/bin/bblslug --list-models\n";

        echo "\n\tTranslate HTML from file to file\n";
        echo "\tvendor/bin/bblslug --model=deepl:pro --format=html \\\n";
        echo "\t    --source=doc.html --translated=out.html --operation-timeout=120 \\\n";
        echo "\t    --filters=url,html_code,html_pre\n";

        echo "\n\tTranslate HTML using structured text segments\n";
        echo "\tvendor/bin/bblslug --model=google:gemini-2.5-flash --format=html --html-transport=segments \\\n";
        echo "\t    --source=doc.html --translated=out.html\n";

        echo "\n\tTranslate selected HTML strings inside JSON\n";
        echo "\tvendor/bin/bblslug --model=google:gemini-2.5-flash --format=json --html-transport=segments \\\n";
        echo "\t    --html-json-pointer=/body --source=doc.json --translated=out.json\n";

        echo "\n\tTranslate plain text via STDIN → STDOUT\n";
        echo "\tcat in.txt | vendor/bin/bblslug --model=openai:gpt-4o --format=text > out.txt\n";

        echo "\n\tTranslate plain text using structured text segments\n";
        echo "\tvendor/bin/bblslug --model=openai:gpt-4o --format=text --text-transport=segments \\\n";
        echo "\t    --source=in.txt --translated=out.txt\n";

        echo "\n\tTranslate plain text segments in batches\n";
        echo "\tvendor/bin/bblslug --model=openai:gpt-4o --format=text --text-transport=segments \\\n";
        echo "\t    --text-segment-batch-size=20 --source=in.txt --translated=out.txt\n";

        echo "\n\tBound segment request payloads by serialized JSON bytes\n";
        echo "\tvendor/bin/bblslug --model=openai:gpt-4o --format=text --text-transport=segments \\\n";
        echo "\t    --segment-batch-max-bytes=4096 --source=in.txt --translated=out.txt\n";

        echo "\n\tResume a failed segment-batch translation\n";
        echo "\tvendor/bin/bblslug --model=openai:gpt-4o --format=text --text-transport=segments \\\n";
        echo "\t    --text-segment-batch-size=20 --checkpoint=translation.checkpoint \\\n";
        echo "\t    --source=in.txt --translated=out.txt\n";

        echo "\n\tDry-run to inspect placeholders in a file\n";
        echo "\tvendor/bin/bblslug --model=deepl:free --format=text --filters=url,html_pre \\\n";
        echo "\t    --source=in.txt --dry-run\n";

        echo "\n\tVerbose mode (shows request preview)\n";
        echo "\tvendor/bin/bblslug --model=deepl:pro --format=html --verbose \\\n";
        echo "\t    --source=doc.html --translated=out.html\n";

        echo "\n\tUse filters\n";
        echo "\tvendor/bin/bblslug --model=deepl:pro --format=html \\\n";
        echo "\t    --source=doc.html --translated=out.html --filters=url,html_code,html_pre\n";

        echo "\n\tUsing model-specific variables (comma-separated):\n";
        echo "\tvendor/bin/bblslug --model=yandex:gpt-lite --format=text \\\n";
        echo "\t    --variables=folder_id=... \\\n";
        echo "\t    --source=in.txt --translated=out.txt\n";

        echo "\n\tUse HTTP proxy\n";
        echo "\tvendor/bin/bblslug --model=openai:gpt-4o --format=text \\\n";
        echo "\t    --source=in.txt --translated=out.txt \\\n";
        echo "\t    --proxy=\"http://localhost:3128\"\n";

        echo $reset;
        if ($exitCode !== null) {
            exit($exitCode);
        }
    }

    /**
     * Print a grouped list of available models to STDOUT.
     *
     * @return void
     */
    public static function printModelList(): void
    {
        /** @var array<string, array<string, array<string, mixed>>> $modelsByVendor */
        $modelsByVendor = Bblslug::listModels();

        $bold = "\033[1m";
        $reset = "\033[0m";

        echo $reset, "Available models:\n\n";

        foreach ($modelsByVendor as $vendor => $models) {
            echo "\t{$bold}{$vendor}{$reset}:\n";
            foreach ($models as $key => $conf) {
                $label = "{$bold}{$key}{$reset}";
                $pad   = str_pad($label, 32 + strlen($bold . $reset));
                $notes = '';
                if (isset($conf['notes']) && is_string($conf['notes'])) {
                    $notes = $conf['notes'];
                }
                echo "\t\t- {$pad}{$notes}\n";
            }
            echo "\n";
        }

        echo $reset;
    }

    /**
     * Print a flat list of available prompt templates to STDOUT.
     *
     * @return void
     */
    public static function printPromptList(): void
    {
        /** @var array<string, array{formats: array<int,string>, notes?: string|null}> $prompts */
        $prompts = Bblslug::listPrompts();
        $bold = "\033[1m";
        $reset = "\033[0m";

        // Header
        echo $reset, "Available prompts:\n\n";

        foreach ($prompts as $key => $info) {
            $formats = implode(', ', $info['formats']);
            $label = "- {$key} ({$formats})";
            $pad = str_pad($label, 40);
            $notes = '';
            if (array_key_exists('notes', $info) && is_string($info['notes'])) {
                $notes = $info['notes'];
            }
            echo "\t{$pad} {$notes}\n";
        }

        echo $reset;
    }
}
