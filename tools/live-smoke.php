<?php

declare(strict_types=1);

use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\Prompts;
use Bblslug\SecretMasker;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Live, paid smoke requests. Credentials stay in the environment; reports are sanitized. */
$smokeEnv = static function (string $name): string {
    return (string) (getenv('BBLSLUG_' . $name) ?: getenv($name) ?: '');
};

/** @return array{exit:int,stdout:string,stderr:string} */
$smokeCli = static function (
    string $model,
    string $format,
    string $input,
    array $filters,
    array $variables,
    string $promptKey
): array {
    $command = [PHP_BINARY, dirname(__DIR__) . '/bin/bblslug', '--model=' . $model,
    '--format=' . $format, '--source-lang=EN', '--target-lang=DE', '--verbose', '--prompt-key=' . $promptKey];
    if ($filters !== []) {
        $command[] = '--filters=' . implode(',', $filters);
    }
    if (in_array($format, ['json', 'linedJson'], true)) {
        $command[] = '--validate-embedded-json';
    }
    // folder_id comes from the child's environment, never from its command line.
    unset($variables['folder_id']);
    if ($variables !== []) {
        $pairs = [];
        foreach ($variables as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $command[] = '--variables=' . implode(',', $pairs);
    }
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CLI');
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 150;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $state = proc_get_status($process);
        if (!$state['running']) {
            break;
        }
        if (microtime(true) >= $deadline) {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new RuntimeException('CLI smoke deadline exceeded');
        }
        usleep(50000);
    } while (true);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    return ['exit' => $state['exitcode'] >= 0 ? $state['exitcode'] : $closed,
    'stdout' => $stdout, 'stderr' => $stderr];
};

/** @return array<string,bool> */
$smokeCheck = static function (string $format, string $input, string $output, string $code): array {
    if (trim($output) === '' || $output === $input) {
        throw new RuntimeException('Empty or untranslated result');
    }
    if ($format === 'text') {
        return ['translated' => true];
    }
    if ($format === 'html') {
        if (!str_contains($output, $code) || !str_contains($output, 'https://example.org/docs?a=1&amp;b=2')) {
            throw new RuntimeException('Protected HTML code or URL changed');
        }
        if (str_contains(strip_tags($output), 'Hello world')) {
            throw new RuntimeException('HTML visible text was not translated');
        }
        return ['translated' => true, 'codePreserved' => true, 'urlPreserved' => true];
    }
    $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    $embedded = json_decode($decoded['embeddedJson'], true, 512, JSON_THROW_ON_ERROR);
    if (($decoded['title'] ?? '') === 'Hello world' || ($embedded['message'] ?? '') === 'Hello world') {
        throw new RuntimeException('Outer or embedded JSON text was not translated');
    }
    if (
        ($decoded['id'] ?? null) !== 123 || ($decoded['enabled'] ?? null) !== true
        || !array_key_exists('optional', $decoded) || $decoded['optional'] !== null
        || ($embedded['count'] ?? null) !== 7
    ) {
        throw new RuntimeException('Non-string JSON values changed');
    }
    if (!str_contains($decoded['body'], $code) || $embedded['link'] !== 'https://example.org/nested?q=1') {
        throw new RuntimeException('Protected JSON code or URL changed');
    }
    return ['translated' => true, 'embeddedTranslated' => true, 'codePreserved' => true,
    'urlPreserved' => true, 'unprotectedPathPreserved' => $embedded['path'] === 'C:\\tmp\\"x"'];
};

$models = explode(',', getenv('BBLSLUG_SMOKE_MODELS') ?: implode(',', [
'openai:gpt-5-mini', 'anthropic:claude-haiku-4.5-20251001', 'google:gemini-2.5-flash',
'xai:grok-4.20-0309-non-reasoning', 'deepl:free', 'deepl:pro', 'yandex:gpt-lite', 'yandex:gpt-pro',
]));
$directory = getenv('BBLSLUG_SMOKE_REPORT_DIR') ?: sys_get_temp_dir() . '/bblslug-live-smoke';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create smoke report directory');
}
putenv('BBLSLUG_HTTP_TIMEOUT=45');
putenv('BBLSLUG_HTTP_CONNECT_TIMEOUT=10');
$registry = new ModelRegistry();
$secrets = [];
foreach (
    ['OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GOOGLE_API_KEY', 'XAI_API_KEY',
    'YANDEX_API_KEY', 'YANDEX_FOLDER_ID', 'DEEPL_FREE_API_KEY', 'DEEPL_PRO_API_KEY', 'PROXY'] as $name
) {
    $secrets[] = $smokeEnv($name);
}
$masker = new SecretMasker($secrets);
$code = '<code>echo "Hello"; C:\\tmp\\"x"</code>';
$html = '<div class="wrap"><p>Hello <strong>world</strong>!</p>'
. '<a href="https://example.org/docs?a=1&amp;b=2">Documentation</a><pre>' . $code . '</pre></div>';
$embedded = '{"message":"Hello world","link":"https:\u002f\u002fexample.org\u002fnested?q=1",'
. '"count":7,"path":' . json_encode('C:\\tmp\\"x"', JSON_THROW_ON_ERROR) . '}';
$json = json_encode(['title' => 'Hello world', 'body' => 'Read ' . $code . ' and follow Documentation.',
'id' => 123, 'enabled' => true, 'optional' => null, 'embeddedJson' => $embedded], JSON_THROW_ON_ERROR);
$cases = ['text' => ['Hello world', []], 'html' => [$html, ['url', 'html_pre']],
'json' => [$json, ['url', 'html_code']]];
$formats = explode(',', getenv('BBLSLUG_SMOKE_FORMATS') ?: 'text,html,json');
$entries = explode(',', getenv('BBLSLUG_SMOKE_ENTRIES') ?: 'api,cli');
$cases = array_intersect_key($cases, array_flip($formats));
if (
    $cases === [] || array_diff($formats, array_keys($cases)) !== []
    || array_diff($entries, ['api', 'cli']) !== []
) {
    throw new InvalidArgumentException('Unsupported smoke formats or entries');
}
$googleJsonTransport = getenv('BBLSLUG_SMOKE_GOOGLE_JSON_TRANSPORT') ?: 'markers';
if (!in_array($googleJsonTransport, ['markers', 'native'], true)) {
    throw new InvalidArgumentException('Unsupported Google JSON smoke transport');
}
$promptKey = getenv('BBLSLUG_SMOKE_PROMPT_KEY') ?: 'translator';
if (!array_key_exists($promptKey, Prompts::list())) {
    throw new InvalidArgumentException('Unknown smoke prompt key');
}
$rows = [];
foreach ($models as $model) {
    $keyName = $registry->getAuthEnv($model);
    $key = $keyName !== null ? $smokeEnv($keyName) : '';
    if ($key === '') {
        $rows[] = ['model' => $model, 'status' => 'missing_key'];
        file_put_contents($directory . '/summary.json', $masker->redact(json_encode(
            $rows,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )));
        continue;
    }
    $keyMasker = new SecretMasker([$key]);
    $variables = str_starts_with($model, 'yandex:')
    ? ['folder_id' => $smokeEnv('YANDEX_FOLDER_ID'), 'maxTokens' => 2048]
    : (str_starts_with($model, 'openai:') ? ['max_completion_tokens' => 2048] : []);
    if (str_starts_with($model, 'deepl:')) {
        $variables['document_timeout'] = 45;
    }
    $blocked = null;
    foreach ($entries as $entry) {
        foreach ($cases as $format => [$input, $filters]) {
            $row = ['model' => $model, 'entry' => $entry, 'format' => $format, 'promptKey' => $promptKey];
            $started = microtime(true);
            if ($blocked !== null) {
                $row += ['status' => 'skipped_account_error', 'reason' => $blocked];
            } else {
                try {
                    $caseVariables = $variables;
                    if (str_starts_with($model, 'google:') && $format === 'json') {
                        $caseVariables['json_transport'] = $googleJsonTransport;
                        $row['jsonTransport'] = $googleJsonTransport;
                    }
                    if ($entry === 'api') {
                        $response = Bblslug::translate(
                            apiKey: $key,
                            format: $format,
                            modelKey: $model,
                            text: $input,
                            filters: $filters,
                            sourceLang: 'EN',
                            targetLang: 'DE',
                            variables: $caseVariables,
                            promptKey: $promptKey,
                            verbose: true,
                            validateEmbeddedJson: $format === 'json',
                            proxy: $smokeEnv('PROXY') ?: null
                        );
                        $output = $response['result'];
                        $diagnostics = $response['debugRequest'] . $response['debugResponse'];
                        $row['httpStatus'] = $response['httpStatus'];
                    } else {
                        $response = $smokeCli($model, $format, $input, $filters, $caseVariables, $promptKey);
                        $diagnostics = $response['stderr'];
                        if ($response['exit'] !== 0) {
                            throw new RuntimeException('CLI failure: ' . $diagnostics, $response['exit']);
                        }
                        $output = $response['stdout'];
                        $row['exitCode'] = 0;
                    }
                    if ($keyMasker->redact($diagnostics) !== $diagnostics) {
                        throw new RuntimeException('API key leaked in diagnostic logs');
                    }
                    $row['checks'] = $smokeCheck($format, $input, $output, $code);
                    $row['status'] = ($row['checks']['unprotectedPathPreserved'] ?? true)
                    ? 'passed' : 'passed_with_observation';
                    file_put_contents($directory . '/' . str_replace(':', '-', $model) . '-' . $entry . '-'
                    . $format . '.txt', $masker->redact($output . "\n\n" . $diagnostics));
                } catch (Throwable $e) {
                    $message = $masker->redact($e->getMessage());
                    $message = preg_replace('/\x1b\[[0-9;]*m/', '', $message) ?? 'Unprintable error';
                    $row['status'] = 'failed';
                    $row['errorType'] = $e::class;
                    $reason = strtok($message, "\n") ?: 'Unknown error';
                    if (preg_match('/(?:^|\n)Error: ([^\n]+)/', $message, $errorLine) === 1) {
                        $reason = 'CLI failure: ' . $errorLine[1];
                    }
                    $row['reason'] = substr($reason, 0, 300);
                    file_put_contents($directory . '/' . str_replace(':', '-', $model) . '-' . $entry . '-'
                    . $format . '-error.txt', $message);
                    $accountError = '/HTTP (?:401|402|403|404|456)|credit balance|quota exceeded|billing/i';
                    if (preg_match($accountError, $message)) {
                        $blocked = $row['reason'];
                    }
                }
            }
            $row['seconds'] = round(microtime(true) - $started, 2);
            $rows[] = $row;
            file_put_contents($directory . '/summary.json', $masker->redact(json_encode(
                $rows,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )));
            echo $masker->redact(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), "\n";
            flush();
        }
    }
}

exit(array_filter($rows, static fn (array $row): bool => $row['status'] !== 'passed'
&& $row['status'] !== 'passed_with_observation') !== [] ? 1 : 0);
