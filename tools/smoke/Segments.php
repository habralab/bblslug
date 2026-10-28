<?php

declare(strict_types=1);

namespace Bblslug\Tools\Smoke;

use Bblslug\Models\ModelRegistry;
use Bblslug\SecretMasker;

/** Explicit, bounded API-to-CLI acceptance; no provider requests are retried. */
final class Segments
{
    private array $environment;
    private SecretMasker $masker;
    private bool $offline;
    private int $operationTimeout;
    private string $directory;
    private array $overrides;

    public static function run(): int
    {
        return (new self())->execute();
    }

    private function __construct()
    {
        $this->offline = getenv('BBLSLUG_SMOKE_OFFLINE') === '1';
        if (!$this->offline && getenv('BBLSLUG_SMOKE_LIVE') !== '1') {
            throw new \InvalidArgumentException(
                'Segments suite requires BBLSLUG_SMOKE_OFFLINE=1 or BBLSLUG_SMOKE_LIVE=1'
            );
        }
        if ($this->offline && getenv('BBLSLUG_SMOKE_LIVE') === '1') {
            throw new \InvalidArgumentException('Choose either offline or live smoke mode');
        }
        if ((getenv('BBLSLUG_SMOKE_ENTRIES') ?: 'api,cli') !== 'api,cli') {
            throw new \InvalidArgumentException('Segments suite checks the composite api,cli workflow');
        }
        if ((getenv('BBLSLUG_SMOKE_PROMPT_KEY') ?: 'translator') !== 'translator') {
            throw new \InvalidArgumentException('Segments fixtures use the standard translator prompt');
        }
        $this->environment = getenv();
        $secrets = [];
        $names = ['OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GOOGLE_API_KEY', 'XAI_API_KEY',
            'YANDEX_API_KEY', 'YANDEX_FOLDER_ID', 'DEEPL_FREE_API_KEY', 'DEEPL_PRO_API_KEY', 'PROXY'];
        foreach ($names as $name) {
            foreach ([$name, 'BBLSLUG_' . $name] as $variable) {
                $secrets[] = $this->environment[$variable] ?? '';
                if ($this->offline) {
                    $this->environment[$variable] = $name === 'PROXY'
                        ? 'http://127.0.0.1:1' : 'offline-' . strtolower($name);
                }
            }
        }
        $this->masker = new SecretMasker($secrets);
        $this->environment['BBLSLUG_SMOKE_OFFLINE'] = $this->offline ? '1' : '0';
        $this->environment['BBLSLUG_HTTP_TIMEOUT'] = getenv('BBLSLUG_HTTP_TIMEOUT') ?: '120';
        $this->environment['BBLSLUG_HTTP_CONNECT_TIMEOUT'] = getenv('BBLSLUG_HTTP_CONNECT_TIMEOUT') ?: '10';
        $rawTimeout = getenv('BBLSLUG_SMOKE_OPERATION_TIMEOUT') ?: '360';
        if (!ctype_digit($rawTimeout) || (int) $rawTimeout < 1 || (int) $rawTimeout > 3600) {
            throw new \InvalidArgumentException('Smoke operation timeout must be an integer from 1 to 3600');
        }
        $this->operationTimeout = (int) $rawTimeout;
        $this->overrides = json_decode(getenv('BBLSLUG_SMOKE_VARIABLES') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($this->overrides) || ($this->overrides !== [] && array_is_list($this->overrides))) {
            throw new \InvalidArgumentException('Smoke variable overrides must be a model-to-settings object');
        }
        $allowed = ['maxTokens', 'max_completion_tokens', 'maxOutputTokens', 'reasoning_effort', 'effort',
            'json_transport', 'document_timeout', 'temperature'];
        foreach ($this->overrides as $model => $settings) {
            if (!(new ModelRegistry())->has((string) $model)) {
                throw new \InvalidArgumentException('Unknown model in smoke variable overrides');
            }
            if (!is_array($settings) || ($settings !== [] && array_is_list($settings))) {
                throw new \InvalidArgumentException('Smoke model settings must be an object');
            }
            foreach ($settings as $key => $value) {
                if (!in_array($key, $allowed, true) || !is_string($value)) {
                    throw new \InvalidArgumentException(
                        'Smoke overrides accept documented string-valued settings only'
                    );
                }
            }
        }
        $this->directory = getenv('BBLSLUG_SMOKE_REPORT_DIR')
            ?: sys_get_temp_dir() . '/bblslug-segments-' . bin2hex(random_bytes(5));
        if (is_dir($this->directory) && (scandir($this->directory) ?: []) !== ['.', '..']) {
            throw new \InvalidArgumentException('Segments report directory must be new or empty');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true)) {
            throw new \RuntimeException('Cannot create segments report directory');
        }
    }

    private function execute(): int
    {
        $registry = new ModelRegistry();
        $models = explode(',', getenv('BBLSLUG_SMOKE_MODELS') ?: ($this->offline ? 'google:gemini-flash-latest'
            : 'openai:gpt-6-luna,anthropic:claude-haiku-5.5,google:gemini-flash-latest,xai:grok-4.7,'
                . 'deepl:free,deepl:pro,yandex:gpt-lite,yandex:gpt-pro'));
        foreach ($models as $model) {
            if (!$registry->has($model) || ($this->offline && $model !== 'google:gemini-flash-latest')) {
                throw new \InvalidArgumentException('Unknown model or unsupported offline model: ' . $model);
            }
        }
        $formats = explode(',', getenv('BBLSLUG_SMOKE_FORMATS') ?: 'text,html,json');
        $names = explode(',', getenv('BBLSLUG_SMOKE_CASES') ?: 'large,abbreviations');
        if (array_diff($formats, ['text', 'html', 'json']) || array_diff($names, ['large', 'abbreviations'])) {
            throw new \InvalidArgumentException('Unsupported segments cases or formats');
        }
        $fixtures = Fixtures::cases();
        $hashes = [];
        foreach ([$this->root() . '/src', __DIR__] as $path) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative = substr($file->getPathname(), strlen($this->root()) + 1);
                    $hashes[$relative] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        foreach (['tools/live-smoke.php', 'resources/models.yaml', 'resources/prompts.yaml'] as $file) {
            $hashes[$file] = hash_file('sha256', $this->root() . '/' . $file);
        }
        ksort($hashes);
        $this->write($this->directory . '/manifest.json', [
            'suite' => 'segments', 'offline' => $this->offline, 'models' => $models, 'formats' => $formats,
            'cases' => $names, 'operationTimeout' => $this->operationTimeout,
            'httpTimeout' => $this->environment['BBLSLUG_HTTP_TIMEOUT'], 'codeHashes' => $hashes,
            'scope' => 'Controlled fixture facts and byte integrity; not universal semantic fidelity.',
        ]);
        $rows = [];
        foreach ($models as $model) {
            $keyName = $registry->getAuthEnv($model) ?? '';
            $key = ($this->environment['BBLSLUG_' . $keyName] ?? '')
                ?: ($this->environment[$keyName] ?? '');
            if ($key === '') {
                $rows[] = ['model' => $model, 'status' => 'missing_key'];
                $this->write($this->directory . '/summary.json', $rows);
                continue;
            }
            $blocked = null;
            foreach ($names as $name) {
                foreach ($formats as $format) {
                    $row = ['model' => $model, 'case' => $name, 'format' => $format, 'entry' => 'api,cli'];
                    $start = microtime(true);
                    if ($blocked !== null) {
                        $row += ['status' => 'skipped_account_error', 'reason' => $blocked];
                    } else {
                        try {
                            $case = $fixtures[$name][$format];
                            $row['checks'] = $this->scenario($model, $name, $case);
                            $row['status'] = 'passed';
                        } catch (\Throwable $exception) {
                            $message = $this->masker->redact($exception->getMessage());
                            $row += ['status' => 'failed', 'category' => Failure::category($message),
                                'reason' => substr(Failure::primary($message), 0, 400)];
                            if ($row['category'] === 'account_or_model') {
                                $blocked = $row['reason'];
                            }
                        }
                    }
                    $row['seconds'] = round(microtime(true) - $start, 2);
                    $rows[] = $row;
                    $this->write($this->directory . '/summary.json', $rows);
                    echo $this->masker->redact(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), "\n";
                    flush();
                }
            }
        }
        return count(array_filter($rows, static fn ($row) => $row['status'] !== 'passed')) > 0 ? 1 : 0;
    }

    private function scenario(string $model, string $name, array $case): array
    {
        $directory = $this->directory . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '-', $model)
            . '-' . $name . '-' . $case['format'];
        mkdir($directory, 0700);
        file_put_contents($directory . '/source.txt', $case['input']);
        file_put_contents($directory . '/translated.txt', 'EXISTING_DESTINATION');
        $variables = match (explode(':', $model, 2)[0]) {
            'google' => ['maxOutputTokens' => '16384'],
            'anthropic', 'yandex' => ['maxTokens' => '8192'],
            'deepl' => ['document_timeout' => '120'],
            default => ['max_completion_tokens' => '8192'],
        };
        $variables = array_replace($variables, $this->overrides[$model] ?? []);
        $job = ['directory' => $directory, 'model' => $model, 'format' => $case['format'],
            'source' => $directory . '/source.txt', 'checkpoint' => $directory . '/checkpoint.json',
            'sourceLang' => $case['sourceLang'], 'targetLang' => $case['targetLang'], 'filters' => $case['filters'],
            'hints' => $case['hints'], 'cap' => $case['cap'], 'variables' => $variables,
            'operationTimeout' => $this->operationTimeout];
        $this->write($directory . '/job.json', $job);
        $this->child($job, 'plan', 'plan');
        $plan = json_decode(file_get_contents($directory . '/plan.json'), true, 512, JSON_THROW_ON_ERROR)['payloads'];
        self::need(count($plan) > 1, 'Fixture must exercise multiple packets');
        foreach ($plan as $packet) {
            self::need(
                strlen($packet) <= $job['cap'] && count(json_decode($packet, true)) <= 25,
                'Planned packet exceeds configured limits'
            );
        }
        $this->child($job, 'interrupt', 'interrupt', false, $directory . '/ready.json');
        $ready = json_decode(file_get_contents($directory . '/ready.json'), true, 512, JSON_THROW_ON_ERROR);
        $prefix = $this->records($job);
        self::need(
            $ready === ['prefix' => 1, 'sends' => 1] && count($prefix) === 1,
            'Interrupted API did not save exactly one packet'
        );
        self::need(
            file_get_contents($directory . '/translated.txt') === 'EXISTING_DESTINATION',
            'Interruption replaced destination'
        );
        $checks = $this->guards($job);
        if ($this->offline) {
            $failed = $this->cli($job, 'decline', false, ['BBLSLUG_SMOKE_FAIL_AT' => '1']);
            self::need(
                $failed['exit'] !== 0 && str_contains($failed['stderr'], '402'),
                'Controlled decline was not rejected'
            );
            self::need(
                $this->records($job) === $prefix
                && file_get_contents($directory . '/translated.txt') === 'EXISTING_DESTINATION',
                'Controlled decline changed saved prefix or destination'
            );
            $checks['controlledDeclinePreservedState'] = true;
        }
        $resumed = $this->cli($job, 'resume');
        self::need($resumed['exit'] === 0, 'CLI resume failed' . "\n" . $resumed['stderr']);
        self::need(str_contains($resumed['stderr'], 'Checkpoint packets reused: 1'), 'CLI did not reuse API prefix');
        self::need(
            substr_count($resumed['stderr'], 'Sending HTTP request') === count($plan) - 1,
            'CLI resent a completed packet or skipped a pending packet'
        );
        $records = $this->records($job);
        self::need(
            count($records) === count($plan) && array_slice($records, 0, 1) === $prefix,
            'Resume did not preserve historical records'
        );
        $output = file_get_contents($directory . '/translated.txt');
        $checks += Fixtures::check($case, $output);
        $fresh = self::usage(array_slice($records, 1));
        $currentUsage = explode('Usage metrics (this run):', $resumed['stderr'], 2)[1] ?? '';
        foreach ($fresh as $category => $metrics) {
            if (isset($metrics['total'])) {
                $matched = preg_match(
                    '/\b' . preg_quote(ucfirst($category), '/') . ':\s+Total:\s+(\d+)/',
                    $currentUsage,
                    $match
                );
                self::need($matched === 1 && (int) $match[1] === $metrics['total'], 'Current-run usage mismatch');
            }
        }
        $this->child($job, 'cache', 'cache', true);
        $cached = json_decode(file_get_contents($directory . '/api-summary.json'), true, 512, JSON_THROW_ON_ERROR);
        self::need(
            $cached['sends'] === 0 && $cached['reusedBatches'] === count($plan)
            && $cached['consumedThisRun'] === [] && $cached['consumed'] === self::usage($records),
            'Completed API cache sends HTTP or has incorrect usage'
        );
        self::need(json_decode($cached['rawResponseBody'], true, 512, JSON_THROW_ON_ERROR)
            === array_fill(0, count($plan), ''), 'Cached raw responses were fabricated');
        self::need(file_get_contents($directory . '/api.output') === $output, 'API cache result differs');
        $state = file_get_contents($job['checkpoint']);
        file_put_contents($directory . '/translated.txt', 'CACHE_DESTINATION');
        $cachedCli = $this->cli($job, 'cache', true);
        self::need($cachedCli['exit'] === 0 && !str_contains($cachedCli['stderr'], 'Sending HTTP request')
            && file_get_contents($directory . '/translated.txt') === $output
            && file_get_contents($job['checkpoint']) === $state, 'Completed CLI cache changed state or used HTTP');
        $checks += ['apiInterrupted' => true, 'cliResumedOnlyPending' => true,
            'currentRunUsage' => true, 'wholeDocumentUsage' => true, 'completedCacheNoHttp' => true];
        $this->write($directory . '/result.json', ['checks' => $checks, 'packets' => count($plan),
            'variables' => $variables, 'consumed' => $cached['consumed']]);
        return $checks;
    }

    private function guards(array $job): array
    {
        $checks = [];
        foreach (['source', 'cap', 'hints', 'checksum'] as $kind) {
            $directory = $job['directory'] . '/guard-' . $kind;
            mkdir($directory, 0700);
            $guard = array_replace($job, ['directory' => $directory, 'source' => $directory . '/source.txt',
                'checkpoint' => $directory . '/checkpoint.json']);
            $input = file_get_contents($job['source']);
            file_put_contents($guard['source'], $kind === 'source' ? ' ' . $input : $input);
            $state = file_get_contents($job['checkpoint']);
            if ($kind === 'cap') {
                ++$guard['cap'];
            } elseif ($kind === 'hints') {
                $guard['hints'][] = 'UnusedHint.';
            } elseif ($kind === 'checksum') {
                $lines = explode("\n", $state);
                $row = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
                $row['record']['text'] .= ' corrupted';
                $lines[1] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $state = implode("\n", $lines);
            }
            file_put_contents($guard['checkpoint'], $state);
            $saved = file_get_contents($guard['checkpoint']);
            file_put_contents($directory . '/translated.txt', 'EXISTING_DESTINATION');
            $result = $this->cli($guard, 'reject', true);
            $reason = $kind === 'checksum' ? 'checksum mismatch' : 'identity mismatch';
            self::need(
                $result['exit'] !== 0 && str_contains($result['stderr'], $reason)
                && !str_contains($result['stderr'], 'Sending HTTP request')
                && file_get_contents($guard['checkpoint']) === $saved
                && file_get_contents($directory . '/translated.txt') === 'EXISTING_DESTINATION',
                'Checkpoint guard failed: ' . $kind . "\n" . $result['stderr']
            );
            $checks[$kind . 'RejectedBeforeHttp'] = true;
        }
        return $checks;
    }

    private function child(
        array $job,
        string $mode,
        string $phase,
        bool $guard = false,
        ?string $stopFile = null
    ): array {
        $this->write($job['directory'] . '/job.json', $job);
        $result = Process::run(
            [PHP_BINARY, __DIR__ . '/worker.php', $job['directory'] . '/job.json', $mode],
            $this->env($job, $phase, $guard),
            $this->operationTimeout + 30,
            $stopFile
        );
        $this->logs($job, $phase, $result);
        if ($stopFile !== null) {
            self::need($result['interrupted'] && !$result['timedOut'], $this->apiError($job, $result));
        } else {
            self::need($result['exit'] === 0 && !$result['timedOut'], $this->apiError($job, $result));
        }
        return $result;
    }

    private function cli(array $job, string $phase, bool $guard = false, array $extra = []): array
    {
        $command = [PHP_BINARY, $this->offline ? __DIR__ . '/cli.php' : $this->root() . '/bin/bblslug',
            '--model=' . $job['model'], '--format=' . $job['format'], '--source=' . $job['source'],
            '--translated=' . $job['directory'] . '/translated.txt', '--source-lang=' . $job['sourceLang'],
            '--target-lang=' . $job['targetLang'], '--segment-batch-max-bytes=' . $job['cap'],
            '--operation-timeout=' . $job['operationTimeout'], '--checkpoint=' . $job['checkpoint'], '--verbose'];
        if ($job['filters'] !== []) {
            $command[] = '--filters=' . implode(',', $job['filters']);
        }
        foreach ($job['hints'] as $hint) {
            $command[] = '--segment-abbreviation=' . $hint;
        }
        $pairs = [];
        foreach ($job['variables'] as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        if ($pairs !== []) {
            $command[] = '--variables=' . implode(',', $pairs);
        }
        if ($job['format'] === 'text') {
            array_push($command, '--text-transport=segments', '--text-segment-batch-size=25');
        } else {
            array_push($command, '--html-transport=segments', '--html-segment-batch-size=25');
            if ($job['format'] === 'json') {
                $command[] = '--html-json-pointer=/body';
            }
        }
        $result = Process::run(
            $command,
            array_replace($this->env($job, $phase, $guard), $extra),
            $this->operationTimeout + 30
        );
        $this->logs($job, $phase, $result);
        self::need(!$result['timedOut'] && $result['stdout'] === '', 'CLI timed out or polluted stdout');
        return $result;
    }

    private function env(array $job, string $phase, bool $guard): array
    {
        $env = $this->environment;
        $env['BBLSLUG_SMOKE_CAPTURE'] = $job['directory'] . '/' . $phase . '.wire.jsonl';
        $env['BBLSLUG_SMOKE_FAIL_AT'] = '0';
        $env['BBLSLUG_SMOKE_NO_HTTP'] = $guard ? '1' : '0';
        if ($guard) {
            foreach ($env as $key => $value) {
                if (str_ends_with($key, '_API_KEY')) {
                    $env[$key] = 'invalid-cache-only-key';
                }
            }
            $env['BBLSLUG_PROXY'] = 'http://127.0.0.1:1';
            $env['BBLSLUG_HTTP_CONNECT_TIMEOUT'] = '1';
        }
        return $env;
    }

    private function logs(array $job, string $phase, array $result): void
    {
        $text = $result['stdout'] . $result['stderr'];
        file_put_contents($job['directory'] . '/' . $phase . '.log', $this->masker->redact($text));
        self::need($this->masker->redact($text) === $text, 'Credential leaked in child diagnostics');
        if ($this->offline) {
            $capture = $job['directory'] . '/' . $phase . '.wire.jsonl';
            foreach (is_file($capture) ? file($capture, FILE_IGNORE_NEW_LINES) : [] as $line) {
                $packet = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                self::need(
                    strlen(json_encode((object) $packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR)) <= $job['cap'] && count($packet) <= 25,
                    'Actual HTTP packet exceeds limits'
                );
            }
        }
    }

    private function apiError(array $job, array $result): string
    {
        $file = $job['directory'] . '/api-error.txt';
        return is_file($file) ? file_get_contents($file) : 'API worker did not complete: ' . $result['stderr'];
    }

    private function records(array $job): array
    {
        $lines = file($job['checkpoint'], FILE_IGNORE_NEW_LINES);
        $header = json_decode(array_shift($lines), true, 512, JSON_THROW_ON_ERROR);
        self::need(($header['version'] ?? null) === 2, 'Unexpected checkpoint version');
        $records = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $records[] = $row['record'];
        }
        return $records;
    }

    private static function usage(array $records): array
    {
        $total = [];
        foreach ($records as $record) {
            foreach ($record['consumed'] as $category => $metrics) {
                if (isset($metrics['total'])) {
                    $total[$category]['total'] = ($total[$category]['total'] ?? 0) + $metrics['total'];
                }
                foreach ($metrics['breakdown'] ?? [] as $key => $value) {
                    $total[$category]['breakdown'][$key] = ($total[$category]['breakdown'][$key] ?? 0) + $value;
                }
            }
        }
        return $total;
    }

    private function write(string $file, mixed $data): void
    {
        file_put_contents($file, $this->masker->redact(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        )));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function need(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}
