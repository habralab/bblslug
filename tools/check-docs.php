#!/usr/bin/env php
<?php

/**
 * Offline documentation contract check.
 *
 * This intentionally executes only dry-runs. Every CLI translation command is
 * amended with --dry-run and every facade translation call with dryRun: true.
 * Fake credentials and a loopback proxy make an accidental HTTP request fail
 * without exposing credentials or contacting a provider.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$docs = array_merge(
    [$root . '/README.md', $root . '/tools/README.md'],
    glob($root . '/docs/*.md') ?: [],
    glob($root . '/samples/**/README.md') ?: [],
    glob($root . '/samples/README.md') ?: []
);
$docs = array_values(array_unique(array_filter($docs, 'is_file')));

$tmp = sys_get_temp_dir() . '/bblslug-doc-check-' . bin2hex(random_bytes(5));
if (!mkdir($tmp . '/samples/quickstart', 0700, true)) {
    fwrite(STDERR, "Cannot create temporary directory\n");
    exit(1);
}
$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? $cleanup($child) : @unlink($child);
        }
        @rmdir($path);
    }
};
register_shutdown_function(
    static function () use ($cleanup, $tmp): void {
        $cleanup($tmp);
    }
);

foreach (glob($root . '/samples/quickstart/*') ?: [] as $source) {
    copy($source, $tmp . '/samples/quickstart/' . basename($source));
    copy($source, $tmp . '/' . basename($source));
}

$counts = [
    'fences' => 0, 'shell' => 0, 'php' => 0, 'shell_run' => 0,
    'php_run' => 0, 'skipped' => 0, 'links' => 0,
];
$failures = [];
$helpRuns = 0;

require $root . '/vendor/autoload.php';
$registry = new Bblslug\Models\ModelRegistry();
$defaultModels = [];
foreach ($registry->getAll() as $modelKey => $config) {
    if (Bblslug\Models\ModelRegistry::isDefaultListing($config)) {
        $defaultModels[(string) $modelKey] = true;
    }
}

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};
$run = static function (string $command, string $cwd, array $env): array {
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(['/bin/bash', '-c', $command], $descriptor, $pipes, $cwd, $env);
    if (!is_resource($process)) {
        return [127, '', 'proc_open failed'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 15;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $state = proc_get_status($process);
        if (!$state['running']) {
            break;
        }
        if (microtime(true) >= $deadline) {
            proc_terminate($process, 9);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return [124, $stdout, $stderr . "Documentation command timed out\n"];
        }
        usleep(10000);
    } while (true);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    return [$state['exitcode'] >= 0 ? $state['exitcode'] : $closed, $stdout, $stderr];
};
$injectCli = static function (string $command) use ($root): string {
    return (string) preg_replace_callback(
        '/(?:vendor\/)?bin\/bblslug\b/',
        static fn(): string => escapeshellarg($root . '/bin/bblslug') . ' --dry-run',
        $command
    );
};
$cliSource = (string) file_get_contents($root . '/src/Bblslug/Console/Cli.php');
preg_match('/\$longOptions = \[(.*?)\];/s', $cliSource, $optionBlock);
preg_match_all('/"([a-z][a-z0-9-]*):*"/', $optionBlock[1] ?? '', $optionNames);
$knownOptions = array_fill_keys($optionNames[1], true);
$checkModels = static function (
    string $command,
    string $label
) use (
    &$failures,
    $defaultModels,
    $knownOptions
): void {
    preg_match_all('/(?:^|\s)--([a-z][a-z0-9-]*)(?:=|\b)/', $command, $flags);
    foreach ($flags[1] as $flag) {
        if (!isset($knownOptions[$flag])) {
            $failures[] = "{$label} uses unknown CLI option --{$flag}";
        }
    }
    preg_match_all('/--model=([^\s]+)/', $command, $models);
    foreach ($models[1] as $model) {
        if (!isset($defaultModels[$model])) {
            $failures[] = "{$label} uses non-default or unknown example model {$model}";
        }
    }
};

$env = [
    'PATH' => dirname(PHP_BINARY) . ':/usr/bin:/bin:/usr/sbin:/sbin',
    'BBLSLUG_GOOGLE_API_KEY' => 'offline-dummy',
    'BBLSLUG_OPENAI_API_KEY' => 'offline-dummy',
    'BBLSLUG_ANTHROPIC_API_KEY' => 'offline-dummy',
    'BBLSLUG_XAI_API_KEY' => 'offline-dummy',
    'BBLSLUG_DEEPL_FREE_API_KEY' => 'offline-dummy',
    'BBLSLUG_DEEPL_PRO_API_KEY' => 'offline-dummy',
    'BBLSLUG_YANDEX_API_KEY' => 'offline-dummy',
    'BBLSLUG_YANDEX_FOLDER_ID' => 'offline-dummy',
    'BBLSLUG_PROXY' => 'http://127.0.0.1:1',
    'http_proxy' => 'http://127.0.0.1:1',
    'https_proxy' => 'http://127.0.0.1:1',
    'HTTP_PROXY' => 'http://127.0.0.1:1',
    'HTTPS_PROXY' => 'http://127.0.0.1:1',
    'NO_PROXY' => '',
];

$lineOf = static function (string $text, int $offset): int {
    return substr_count(substr($text, 0, $offset), "\n") + 1;
};

foreach ($docs as $file) {
    $text = (string) file_get_contents($file);
    $relative = ltrim(str_replace($root, '', $file), '/');

    // Validate local Markdown links and anchors.
    preg_match_all('/!?\[[^]]*\]\(([^) ]+)(?:\s+"[^"]*")?\)/', $text, $links, PREG_OFFSET_CAPTURE);
    foreach ($links[1] as [$target, $offset]) {
        if (preg_match('/^(?:https?:|mailto:)/', $target) === 1) {
            continue;
        }
        $counts['links']++;
        [$path, $anchor] = array_pad(explode('#', $target, 2), 2, '');
        $linkedFile = $path === '' ? $file : dirname($file) . '/' . $path;
        if ($path !== '' && !is_file($linkedFile) && !is_dir($linkedFile)) {
            $fail("{$relative}:{$lineOf($text, $offset)} missing link target {$target}");
        } elseif ($anchor !== '' && is_file($linkedFile)) {
            $counts['anchors'] = ($counts['anchors'] ?? 0) + 1;
            $linkedText = (string) file_get_contents($linkedFile);
            preg_match_all('/^#{1,6}\s+(.+)$/m', $linkedText, $headings);
            $slugs = array_map(static function (string $heading): string {
                $heading = strtolower(trim(strip_tags($heading)));
                $heading = preg_replace('/[`*_]/', '', $heading) ?? $heading;
                return trim((string) preg_replace('/[^a-z0-9\s-]/', '', str_replace(' ', '-', $heading)), '-');
            }, $headings[1]);
            if (!in_array(strtolower(rawurldecode($anchor)), $slugs, true)) {
                $fail("{$relative}:{$lineOf($text, $offset)} missing anchor {$target}");
            }
        }
    }

    preg_match_all('/^[ ]{0,3}```([^\r\n]*)\R(.*?)^[ ]{0,3}```\s*$/ms', $text, $fences, PREG_OFFSET_CAPTURE);
    foreach ($fences[0] as $index => [$whole, $offset]) {
        $lang = strtolower(trim($fences[1][$index][0]));
        $body = $fences[2][$index][0];
        $label = "{$relative}:{$lineOf($text, $offset)}";
        $counts['fences']++;
        if (in_array($lang, ['bash', 'sh', 'shell'], true)) {
            $counts['shell']++;
            $shellFile = $tmp . '/snippet.sh';
            file_put_contents($shellFile, $body);
            [$status, , $stderr] = $run('bash -n ' . escapeshellarg($shellFile), $tmp, $env);
            if ($status !== 0) {
                $fail("{$label} shell syntax: " . trim($stderr));
                continue;
            }
            if (
                str_contains($body, 'tools/live-smoke.php')
                || str_contains($body, 'RUN_LIVE_TESTS=1')
                || str_contains($body, 'composer require')
                || str_contains($body, 'vendor/bin/phpunit')
            ) {
                $counts['skipped']++;
                continue;
            }
            if (!preg_match('/(?:^|\s)(?:vendor\/)?bin\/bblslug\b/', $body)) {
                $counts['skipped']++;
                continue;
            }
            $command = trim((string) preg_replace('/\\\\\r?\n[ \t]*/', ' ', $body));
            $checkModels($command, $label);
            $command = $injectCli($command);
            $counts['shell_run']++;
            [$status, , $stderr] = $run($command, $tmp, $env);
            if ($status !== 0) {
                $fail("{$label} command exited {$status}: " . trim($stderr));
            }
            continue;
        }
        if ($lang !== 'php') {
            $counts['skipped']++;
            continue;
        }
        $counts['php']++;
        $originalSnippet = preg_replace("/^<\?php\s*/", '', $body);
        $lintFile = $tmp . '/snippet.php';
        file_put_contents($lintFile, "<?php\n" . $originalSnippet);
        [$status, , $stderr] = $run('php -l ' . escapeshellarg($lintFile), $tmp, $env);
        if ($status !== 0) {
            $fail("{$label} PHP syntax: " . trim($stderr));
            continue;
        }
        $translateCalls = preg_match_all(
            '/(?:\\\\Bblslug\\\\Bblslug|Bblslug)::translate\(/',
            (string) $originalSnippet
        );
        if ($translateCalls === false || $translateCalls === 0) {
            $counts['skipped']++;
            continue;
        }
        preg_match_all("/modelKey:\s*['\"]([^'\"]+)['\"]/", (string) $originalSnippet, $phpModels);
        foreach ($phpModels[1] as $model) {
            if (!isset($defaultModels[$model])) {
                $fail("{$label} uses non-default or unknown example model {$model}");
            }
        }
        if (str_contains((string) $originalSnippet, 'dryRun:')) {
            $fail("{$label} contains an existing dryRun argument; checker requires an injected guard");
            continue;
        }
        $snippet = preg_replace_callback(
            '/((?:\\\\Bblslug\\\\Bblslug|Bblslug)::translate\()/',
            static fn(array $match): string => $match[1] . "\n    dryRun: true,",
            (string) $originalSnippet
        );
        if (
            preg_match_all(
                '/(?:\\\\Bblslug\\\\Bblslug|Bblslug)::translate\(/',
                (string) $snippet
            ) !== $translateCalls
            || substr_count((string) $snippet, 'dryRun: true') !== $translateCalls
        ) {
            $fail("{$label} translate-call injection count mismatch");
            continue;
        }
        $imports = [];
        $snippet = preg_replace_callback(
            '/^\s*use\s+[^;]+;\s*$/m',
            static function (array $match) use (&$imports): string {
                $imports[] = trim($match[0]);
                return '';
            },
            (string) $snippet
        );
        if (!in_array('use Bblslug\\Bblslug;', $imports, true)) {
            $imports[] = 'use Bblslug\\Bblslug;';
        }
        $snippet = preg_replace("/^\s*require ['\"]vendor\/autoload\.php['\"];\s*/m", '', (string) $snippet);
        $prefix = <<<'PHP'
<?php
declare(strict_types=1);
require %s;
%s
$apiKey = $key = $folderId = 'offline-dummy';
$html = file_get_contents('samples/quickstart/input.html');
$json = file_get_contents('samples/quickstart/input.json');
$text = file_get_contents('samples/quickstart/input.txt');
PHP;
        $script = sprintf(
            $prefix,
            var_export($root . '/vendor/autoload.php', true),
            implode("\n", $imports)
        ) . "\n" . $snippet;
        $apiFile = $tmp . '/api.php';
        file_put_contents($apiFile, $script);
        $counts['php_run']++;
        [$status, , $stderr] = $run('php ' . escapeshellarg($apiFile), $tmp, $env);
        if ($status !== 0) {
            $fail("{$label} PHP execution exited {$status}: " . trim($stderr));
        }
    }
}

// Rendered CLI help must retain and execute every documented example.
[$status, $help, $stderr] = $run('php ' . escapeshellarg($root . '/bin/bblslug') . ' --help', $tmp, $env);
if ($status !== 0) {
    $fail('rendered CLI help exited ' . $status . ': ' . trim($stderr));
} else {
    $requiredFlags = [
        '--model=', '--format=', '--dry-run', '--html-transport=',
        '--text-transport=', '--checkpoint=', '--response-schema=', '--variables=',
    ];
    foreach ($requiredFlags as $flag) {
        if (!str_contains($help, $flag)) {
            $fail("rendered CLI help missing {$flag}");
        }
    }
    $examples = explode('Examples:', $help, 2)[1] ?? '';
    $lines = preg_split('/\R/', $examples) ?: [];
    $commands = [];
    for ($i = 0; $i < count($lines); $i++) {
        if (preg_match('/^\s*(.*\bvendor\/bin\/bblslug\s+.+)$/', $lines[$i], $match) !== 1) {
            continue;
        }
        $command = $match[0];
        while ($i + 1 < count($lines) && preg_match('/^\s+--/', $lines[$i + 1])) {
            $command .= "\n" . trim($lines[++$i]);
        }
        $commands[] = trim($command);
    }
    foreach ($commands as $command) {
        $checkModels($command, 'rendered CLI help');
        $command = trim((string) preg_replace('/\\\\\r?\n[ \t]*/', ' ', $command));
        $command = $injectCli($command);
        $helpRuns++;
        [$exampleStatus, , $exampleStderr] = $run($command, $tmp, $env);
        if ($exampleStatus !== 0) {
            $fail("rendered CLI help example exited {$exampleStatus}: " . trim($exampleStderr));
        }
    }
    if ($helpRuns === 0) {
        $fail('rendered CLI help exposed no executable examples');
    }
}

$summary = sprintf(
    "Documentation check: %d fences (%d shell, %d PHP), %d shell runs, %d PHP runs, "
        . "%d help runs, %d skipped, %d links/%d anchors checked.\n",
    $counts['fences'],
    $counts['shell'],
    $counts['php'],
    $counts['shell_run'],
    $counts['php_run'],
    $helpRuns,
    $counts['skipped'],
    $counts['links'],
    $counts['anchors'] ?? 0
);
echo $summary;
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}
echo "Offline documentation checks passed; live commands were skipped.\n";
