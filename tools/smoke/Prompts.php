<?php

declare(strict_types=1);

namespace Bblslug\Tools\Smoke;

use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\Prompts as PromptRegistry;
use Bblslug\SecretMasker;
use Symfony\Component\Yaml\Yaml;

/** Paid, opt-in prompt checks, isolated by case. */
final class Prompts
{
    public static function run(): int
    {
        if (getenv('BBLSLUG_SMOKE_OFFLINE') === '1' || getenv('BBLSLUG_SMOKE_LIVE') !== '1') {
            throw new \InvalidArgumentException('Prompts suite requires live mode and does not support offline mode');
        }
        $rawModels = getenv('BBLSLUG_SMOKE_MODELS');
        if (!is_string($rawModels) || trim($rawModels) === '') {
            throw new \InvalidArgumentException('Prompts suite requires explicit BBLSLUG_SMOKE_MODELS');
        }
        $models = array_map('trim', explode(',', $rawModels));
        $environment = getenv();
        $secrets = [];
        foreach (
            ['OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GOOGLE_API_KEY', 'XAI_API_KEY', 'YANDEX_API_KEY',
            'YANDEX_FOLDER_ID', 'DEEPL_FREE_API_KEY', 'DEEPL_PRO_API_KEY', 'PROXY'] as $name
        ) {
            $secrets[] = $environment[$name] ?? '';
            $secrets[] = $environment['BBLSLUG_' . $name] ?? '';
        }
        $masker = new SecretMasker($secrets);
        $environment['BBLSLUG_HTTP_TIMEOUT'] = $environment['BBLSLUG_HTTP_TIMEOUT'] ?? '120';
        $environment['BBLSLUG_HTTP_CONNECT_TIMEOUT'] = $environment['BBLSLUG_HTTP_CONNECT_TIMEOUT'] ?? '10';
        $directory = getenv('BBLSLUG_SMOKE_REPORT_DIR')
            ?: sys_get_temp_dir() . '/bblslug-prompts-' . bin2hex(random_bytes(5));
        if (is_link($directory) || (is_dir($directory) && (scandir($directory) ?: []) !== ['.', '..'])) {
            throw new \InvalidArgumentException('Prompts report directory must be new or empty');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Cannot create prompts report directory');
        }
        if (!chmod($directory, 0700)) {
            throw new \RuntimeException('Cannot secure prompts report directory');
        }
        $directory = realpath($directory);
        if ($directory === false) {
            throw new \RuntimeException('Cannot resolve prompts report directory');
        }
        $registry = new ModelRegistry();
        $rows = [];
        foreach ($models as $model) {
            if (!$registry->has($model)) {
                $rows[] = ['model' => $model, 'status' => 'unknown_model'];
            } elseif (($registry->get($model)['vendor'] ?? '') === 'deepl') {
                $rows[] = ['model' => $model, 'status' => 'unsupported_deepl'];
            } else {
                $keyName = $registry->getAuthEnv($model) ?? '';
                $key = ($environment['BBLSLUG_' . $keyName] ?? '') ?: ($environment[$keyName] ?? '');
                if ($key === '') {
                    $rows[] = ['model' => $model, 'status' => 'missing_key'];
                } else {
                    foreach (['context', 'group', 'custom'] as $case) {
                        $job = $directory . '/job.json';
                        $jobData = json_encode(['model' => $model, 'case' => $case], JSON_THROW_ON_ERROR);
                        self::put($job, $jobData);
                        $result = Process::run(
                            [PHP_BINARY, __DIR__ . '/worker.php', $job, 'prompts'],
                            $environment,
                            270
                        );
                        $row = ['model' => $model, 'case' => $case];
                        $data = json_decode($result['stdout'], true);
                        if ($result['exit'] === 0 && !$result['timedOut'] && is_array($data)) {
                            $row += $data;
                        } else {
                            // Never persist raw diagnostics, which may include request headers.
                            $category = $result['stderr'];
                            $categories = ['account_or_model', 'output_budget', 'timeout', 'checkpoint',
                                'empty_response', 'response_or_assertion'];
                            if ($result['timedOut']) {
                                $category = 'timeout';
                            } elseif (!in_array($category, $categories, true)) {
                                $category = 'response_or_assertion';
                            }
                            $row += ['status' => 'failed', 'category' => $category, 'reason' => 'Prompt worker failed'];
                        }
                        $rows[] = $row;
                        self::write($directory, $rows, $masker);
                        echo $masker->redact(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), "\n";
                    }
                }
            }
            self::write($directory, $rows, $masker);
            $last = end($rows);
            if (!isset($last['case'])) {
                echo $masker->redact(json_encode($last, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), "\n";
            }
        }
        return count(array_filter($rows, static fn (array $row): bool => $row['status'] !== 'passed')) ? 1 : 0;
    }

    private static function write(string $directory, array $rows, SecretMasker $masker): void
    {
        self::put($directory . '/summary.json', $masker->redact(json_encode(
            $rows,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )));
    }

    private static function put(string $path, string $content): void
    {
        if (file_put_contents($path, $content) !== strlen($content)) {
            throw new \RuntimeException('Cannot write prompt smoke file');
        }
    }

    public static function worker(array $job): void
    {
        $registry = new ModelRegistry();
        $model = $job['model'];
        $case = $job['case'];
        $env = static fn (string $name): string => (string) (getenv('BBLSLUG_' . $name) ?: getenv($name) ?: '');
        $key = $env($registry->getAuthEnv($model) ?? '');
        $secrets = [$key, $env('YANDEX_FOLDER_ID'), $env('PROXY')];
        $masker = new SecretMasker($secrets);
        try {
            $vendor = $registry->get($model)['vendor'] ?? '';
            $variables = match ($vendor) {
                'openai', 'xai' => ['max_completion_tokens' => 1024],
                'google' => ['maxOutputTokens' => 1024],
                'anthropic', 'yandex' => ['maxTokens' => 1024],
                default => throw new \RuntimeException('Unsupported prompt vendor'),
            };
            foreach ($registry->getVariables($model) as $name => $envName) {
                $value = $env($envName);
                if ($value === '') {
                    throw new \RuntimeException('Missing required model configuration');
                }
                $variables[$name] = $value;
            }
            $promptKey = $case === 'group' ? 'experimental_xml' : 'translator';
            if ($case === 'custom') {
                $reflection = new \ReflectionClass(PromptRegistry::class);
                $path = dirname($reflection->getFileName(), 4) . '/resources/prompts.yaml';
                $prompts = Yaml::parseFile($path);
                $prompts['app_owned'] = $prompts['translator'];
                foreach (['json', 'structured_json'] as $format) {
                    $prompts['app_owned'][$format] = str_replace(
                        'You are a professional translator',
                        'You are an application translator. Always translate workspace using the exact German term '
                            . 'Projektbereich',
                        $prompts['app_owned'][$format],
                        $count
                    );
                    if ($count !== 1) {
                        throw new \RuntimeException('Could not customize bundled prompt');
                    }
                }
                $file = tempnam(sys_get_temp_dir(), 'bblslug-prompts-');
                if ($file === false) {
                    throw new \RuntimeException('Cannot create prompt file');
                }
                try {
                    self::put($file, Yaml::dump($prompts, 10, 2));
                    PromptRegistry::load($file);
                } finally {
                    unlink($file);
                }
                $promptKey = 'app_owned';
            }
            $terms = $case === 'context' ? ['Arbeitsbereich', 'Projektbereich']
                : [$case === 'custom' ? 'Projektbereich' : 'Arbeitsbereich'];
            $calls = [];
            foreach ($terms as $term) {
                $context = $case === 'custom' ? 'Use formal German address (Sie) when an address is needed.'
                    : 'Glossary: translate the English word workspace as the exact German term '
                        . $term . ' in every occurrence.';
                $response = Bblslug::translate(
                    apiKey: $key,
                    format: 'json',
                    modelKey: $model,
                    text: '{"message":"The workspace is ready.","count":2,"enabled":true,'
                        . '"nested":{"message":"Open the workspace."}}',
                    context: $context,
                    promptKey: $promptKey,
                    proxy: $env('PROXY') ?: null,
                    sourceLang: 'EN',
                    targetLang: 'DE',
                    variables: $variables,
                    operationTimeoutSeconds: 120
                );
                $value = json_decode($response['result'], true, 512, JSON_THROW_ON_ERROR);
                $valid = is_array($value) && count($value) === 4
                    && array_diff(['message', 'count', 'enabled', 'nested'], array_keys($value)) === []
                    && is_string($value['message']) && str_contains($value['message'], $term)
                    && $value['count'] === 2 && $value['enabled'] === true
                    && is_array($value['nested']) && count($value['nested']) === 1
                    && isset($value['nested']['message']) && is_string($value['nested']['message'])
                    && str_contains($value['nested']['message'], $term);
                if (!$valid) {
                    throw new \RuntimeException('Prompt result failed JSON or terminology checks');
                }
                $calls[] = ['expectedTerm' => $term, 'httpStatus' => $response['httpStatus'], 'result' => $value];
            }
            echo $masker->redact(json_encode(['status' => 'passed', 'checks' => ['jsonAndTerms' => true],
                'calls' => $calls], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            // Categorize only; provider exceptions can contain sensitive request data.
            fwrite(STDERR, Failure::category($masker->redact($exception->getMessage())));
            exit(1);
        }
    }
}
