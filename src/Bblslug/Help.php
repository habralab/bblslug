<?php

namespace Bblslug;

use Bblslug\Models\ModelRegistry;

class Help
{
    public static function alert(string $label, string $message, string $color = "\033[0m", ?int $exitCode = null): void
    {
        fwrite(STDERR, "{$color}{$label}:\033[0m {$message}\n");
        if ($exitCode !== null) {
            exit($exitCode);
        }
    }

    public static function error(string $message, ?int $exitCode = 1): void
    {
        self::alert("Error", $message, "\033[31m", $exitCode); // Red
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
        echo "\tphp bblslug.php [options]\n";

        echo "\nOptions:\n";
        echo "\t{$bold}--context=TEXT{$reset}       Add translation context prompt\n";
        echo "\t{$bold}--dry-run{$reset}            Prepare and save placeholders, skip translation\n";
        echo "\t{$bold}--filters=F1,F2,...{$reset}  Comma-separated filters to (e.g. url, html_pre, html_code)\n";
        echo "\t{$bold}--format=text|html{$reset}   Input format: plain text or structured HTML\n";
        echo "\t{$bold}--help{$reset}               Show this help message\n";
        echo "\t{$bold}--list-models{$reset}        Show available translation models grouped by vendor\n";
        echo "\t{$bold}--model=MODEL_ID{$reset}     Translation model to use (see --list-models)\n";
        echo "\t{$bold}--proxy=URI{$reset}          Optional proxy URI (see examples) or set BBLSLUG_PROXY\n";
        echo "\t{$bold}--source=FILE{$reset}        Input file to translate (omit to read from STDIN)\n";
        echo "\t{$bold}--source-lang=LANG{$reset}   Source language code (e.g. EN, DE) - default autodetect\n";
        echo "\t{$bold}--target-lang=LANG{$reset}   Target language (e.g. EN, DE) - default EN\n";
        echo "\t{$bold}--translated=FILE{$reset}    Output file for translated content (omit to write to STDOUT)\n";
        echo "\t{$bold}--verbose{$reset}            Show extra debug info after processing\n";

        echo "\nEnvironment:\n";
        echo "\tSet API keys via environment variables depending on the model:\n";
        echo "\t  export ANTHROPIC_API_KEY=\"...\" # for Anthropic (Claude)\n";
        echo "\t  export DEEPL_FREE_API_KEY=\"...\" # for DeepL Free\n";
        echo "\t  export DEEPL_PRO_API_KEY=\"...\" # for DeepL Pro\n";
        echo "\t  export GOOGLE_API_KEY=\"...\" # for Google (Gemini)\n";
        echo "\t  export OPENAI_API_KEY=\"...\" # for OpenAI (GPT)\n";
        echo "\t  (See each model's required variable with --list-models)\n";
        echo "\tSome models may not require API keys at all.\n";
        echo "\n\tYou may also set a proxy globally via the BBLSLUG_PROXY environment variable:\n";
        echo "\t  export BBLSLUG_PROXY=\"http://localhost:3128\" # HTTP proxy\n";
        echo "\t  export BBLSLUG_PROXY=\"socks5h://127.0.0.1:9050\" # SOCKS5 proxy\n";

        echo "\nExamples:\n";
        echo "\tList available models:\n";
        echo "\tphp bblslug.php --list-models\n";

        echo "\n\tTranslate HTML from file to file\n";
        echo "\tphp bblslug.php --model=deepl:pro --format=html \\\n";
        echo "\t    --source=doc.html --translated=out.html --filters=url,html_code,html_pre\n";

        echo "\n\tTranslate plain text via STDIN → STDOUT\n";
        echo "\tcat in.txt | php bblslug.php --model=openai:gpt-4o --format=text > out.txt\n";

        echo "\n\tDry-run to inspect placeholders in a file\n";
        echo "\tphp bblslug.php --model=deepl:free --format=text --filters=url,html_pre \\\n";
        echo "\t    --source=in.txt --dry-run\n";

        echo "\n\tVerbose mode (shows request preview)\n";
        echo "\tphp bblslug.php --model=deepl:pro --format=html --verbose \\\n";
        echo "\t    --source=doc.html --translated=out.html\n";

        echo "\n\tUse filters\n";
        echo "\tphp bblslug.php --model=deepl:pro --format=html \\\n";
        echo "\t    --source=doc.html --translated=out.html --filters=url,html_code,html_pre\n";

        echo "\n\tUse HTTP proxy\n";
        echo "\tphp bblslug.php --model=openai:gpt-4o --format=text \\\n";
        echo "\t    --source=in.txt --translated=out.txt \\\n";
        echo "\t    --proxy=\"http://localhost:3128\"\n";

        echo $reset;
        if ($exitCode !== null) {
            exit($exitCode);
        }
    }

    // for execution with --list-models
    public static function printModelList(ModelRegistry $registry): void
    {
        $bold = "\033[1m";
        $reset = "\033[0m";

        echo $reset;
        echo "Available models:\n\n";

        $grouped = [];

        foreach ($registry->getAll() as $key => $model) {
            $vendor = $model['vendor'] ?? 'other';
            $grouped[$vendor][$key] = $model;
        }

        foreach ($grouped as $vendor => $models) {
            echo "\t{$bold}{$vendor}{$reset}:\n";

            foreach ($models as $key => $model) {
                $label = "{$bold}{$key}{$reset}";
                $line = "\t\t- " . str_pad($label, 32 + strlen($bold . $reset)) . ($model['notes'] ?? '') . "\n";
                echo $line;
            }

            echo "\n";
        }

        echo $reset;
    }
}
