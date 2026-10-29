<?php

declare(strict_types=1);

if (getenv('BBLSLUG_SMOKE_OFFLINE') === '1') {
    require __DIR__ . '/offline-http.php';
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Bblslug\Bblslug;
use Bblslug\Filters\FilterManager;
use Bblslug\Models\ModelRegistry;
use Bblslug\SecretMasker;
use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\JsonHtmlDocument;
use Bblslug\Translation\SegmentPlan;
use Bblslug\Translation\TextDocument;

if (($argv[2] ?? '') === 'prompts') {
    require_once __DIR__ . '/Failure.php';
    require_once __DIR__ . '/Prompts.php';
    \Bblslug\Tools\Smoke\Prompts::worker(json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR));
    exit(0);
}

$job = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$mode = $argv[2];
$env = static fn (string $name): string => (string) (getenv('BBLSLUG_' . $name) ?: getenv($name) ?: '');
$registry = new ModelRegistry();
$key = $env($registry->getAuthEnv($job['model']) ?? '');
$folder = $env('YANDEX_FOLDER_ID');
$proxy = $env('PROXY');
$masker = new SecretMasker([$key, $folder, $proxy]);
$input = file_get_contents($job['source']);
try {
    if ($mode === 'plan') {
        if ($job['format'] === 'json') {
            $document = new JsonHtmlDocument($input, ['/body'], $job['filters']);
        } else {
            $filters = new FilterManager($job['filters']);
            $prepared = $filters->apply($input);
            $document = $job['format'] === 'text' ? new TextDocument($prepared) : new HtmlTextDocument($prepared);
        }
        $plan = new SegmentPlan($document->getJson(), 25, $job['cap'], $job['hints']);
        file_put_contents($job['directory'] . '/plan.json', json_encode([
            'payloads' => $plan->getPayloads(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        exit(0);
    }
    $variables = $job['variables'];
    if (str_starts_with($job['model'], 'yandex:')) {
        $variables['folder_id'] = $folder;
    }
    $sends = 0;
    $feedback = static function (string $message, string $level = 'info') use (&$sends, $mode, $job): void {
        if ($message === 'Sending HTTP request') {
            ++$sends;
        }
        if ($mode === 'interrupt' && preg_match('/^Completed (?:Text|HTML) segment batch 1\//', $message)) {
            $lines = file($job['checkpoint'], FILE_IGNORE_NEW_LINES);
            $header = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
            if (($header['version'] ?? null) !== 2) {
                throw new \RuntimeException('Unexpected checkpoint version');
            }
            $ready = json_encode(
                ['prefix' => count($lines) - 1, 'sends' => $sends],
                JSON_THROW_ON_ERROR
            );
            $path = $job['directory'] . '/ready.json';
            if (file_put_contents($path . '.tmp', $ready) !== strlen($ready) || !rename($path . '.tmp', $path)) {
                exit(93);
            }
            // The parent kills the process while the callback cannot start another request.
            $until = microtime(true) + $job['operationTimeout'] + 30;
            while (microtime(true) < $until) {
                usleep(20000);
            }
            exit(92);
        }
    };
    $arguments = [
        'apiKey' => $key, 'modelKey' => $job['model'], 'format' => $job['format'], 'text' => $input,
        'sourceLang' => $job['sourceLang'], 'targetLang' => $job['targetLang'], 'filters' => $job['filters'],
        'variables' => $variables, 'verbose' => true, 'proxy' => $proxy !== '' ? $proxy : null,
        'operationTimeoutSeconds' => $job['operationTimeout'], 'segmentBatchMaxBytes' => $job['cap'],
        'segmentAbbreviations' => $job['hints'], 'checkpointPath' => $job['checkpoint'], 'onFeedback' => $feedback,
    ];
    if ($job['format'] === 'text') {
        $arguments += ['textTransport' => 'segments', 'textSegmentBatchSize' => 25];
    } else {
        $arguments += ['htmlTransport' => 'segments', 'htmlSegmentBatchSize' => 25];
        if ($job['format'] === 'json') {
            $arguments['htmlJsonPointers'] = ['/body'];
        }
    }
    $result = Bblslug::translate(...$arguments);
    file_put_contents($job['directory'] . '/api.output', $masker->redact($result['result']));
    $diagnostics = $result['debugRequest'] . $result['debugResponse'];
    if ($masker->redact($diagnostics) !== $diagnostics) {
        throw new RuntimeException('Credential leaked in API diagnostics');
    }
    unset(
        $result['original'],
        $result['prepared'],
        $result['result'],
        $result['debugRequest'],
        $result['debugResponse']
    );
    $result['sends'] = $sends;
    file_put_contents($job['directory'] . '/api-summary.json', $masker->redact(json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    )));
} catch (Throwable $exception) {
    file_put_contents($job['directory'] . '/api-error.txt', $masker->redact($exception->getMessage()));
    exit(1);
}
