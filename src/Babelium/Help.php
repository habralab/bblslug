<?php

namespace Babelium;

use Babelium\Models\ModelRegistry;

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
        echo "\tphp babelium.php [options]\n\n";

        echo "Options:\n";
        echo "\t{$bold}--source=FILE{$reset}           Input file to be translated\n";
        echo "\t{$bold}--translated=FILE{$reset}       Output file for translated content\n";
        echo "\t{$bold}--format=text|html{$reset}      Input format: plain text or structured HTML\n";
        echo "\t{$bold}--filters=F1,F2,...{$reset}     Filters to apply (e.g. url, html_pre, html_code)\n";
        echo "\t{$bold}--model=MODEL_ID{$reset}        Translation model to use (see --list-models)\n";
        echo "\t{$bold}--dry-run{$reset}               Prepare and save placeholders, skip translation\n";
        echo "\t{$bold}--verbose{$reset}               Show extra debug info after processing\n";
        echo "\t{$bold}--list-models{$reset}           Show available translation models grouped by vendor\n";

        echo "\nExamples:\n";
        echo "\tphp babelium.php --format=html --source=doc.html --translated=out.html\n";
        echo "\tphp babelium.php --source=in.txt --translated=out.txt --model=openai:gpt-4o --format=text\n";

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
