<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

final class UnavailableModelsTest extends TestCase
{
    public function testEveryPreviouslyRegisteredKeyIsActiveOrHasAnUnavailableDiagnostic(): void
    {
        $registry = new ModelRegistry();
        $keys = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/models/previous-model-keys.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertCount(159, $keys);
        self::assertSame($keys, array_values(array_unique($keys)));
        foreach ($keys as $key) {
            if ($registry->has($key)) {
                self::assertNull($registry->getUnavailableMessage($key), $key);
                continue;
            }
            $message = $registry->getUnavailableMessage($key);
            self::assertIsString($message, $key);
            self::assertStringContainsString("Model key '{$key}' is unavailable in this library.", $message);
            self::assertStringContainsString('Reason:', $message);
            self::assertNull($registry->get($key), $key);
            self::assertNotContains($key, $registry->list(), $key);
        }
    }

    public function testCatalogueEntriesAreDisjointFromActiveModelsAndReplacementsExist(): void
    {
        $registry = new ModelRegistry();
        $data = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/resources/unavailable-models.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertNotEmpty($data);
        foreach ($data as $key => $entry) {
            self::assertFalse($registry->has($key), $key);
            self::assertNotEmpty($entry['reason'], $key);
            if (isset($entry['replacement'])) {
                self::assertTrue($registry->has($entry['replacement']), $key);
            }
        }
    }

    public function testRepresentativeHistoricalKeysAndUnknownKey(): void
    {
        $registry = new ModelRegistry();
        $alias = $registry->getUnavailableMessage('openai:gpt-5-mini');
        self::assertIsString($alias);
        self::assertStringContainsStringIgnoringCase('alias', $alias);
        self::assertStringContainsString('openai:gpt-5.6-terra', $alias);
        self::assertStringContainsStringIgnoringCase(
            'snapshot',
            (string) $registry->getUnavailableMessage('openai:gpt-5-2025-08-07')
        );
        self::assertIsString($registry->getUnavailableMessage('gemini:1.5-pro'));
        self::assertIsString($registry->getUnavailableMessage('yandex:llama'));
        self::assertStringNotContainsStringIgnoringCase(
            'yandex has retired',
            (string) $registry->getUnavailableMessage('yandex:llama')
        );
        self::assertNull($registry->getUnavailableMessage('unknown:never-published-model'));
    }

    public function testCustomRegistryCanReintroduceAnOldKey(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bblslug-models-');
        self::assertIsString($path);
        try {
            file_put_contents($path, "'openai:gpt-5-mini':\n  vendor: openai\n  endpoint: 'https://example.test'\n");
            $registry = new ModelRegistry($path);
            self::assertTrue($registry->has('openai:gpt-5-mini'));
            self::assertNull($registry->getUnavailableMessage('openai:gpt-5-mini'));
            $message = $registry->getUnavailableMessage('openai:gpt-5-pro');
            self::assertIsString($message);
            self::assertStringNotContainsString('Suggested replacement:', $message);
            self::assertStringNotContainsString('separate Pro reasoning mode', $message);
        } finally {
            unlink($path);
        }
    }

    public function testDriverAndFacadeReportHistoricalAndUnknownKeys(): void
    {
        $registry = new ModelRegistry();
        foreach (['openai:gpt-5-mini', 'unknown:never-published-model'] as $key) {
            $expected = $registry->getUnavailableMessage($key) ?? "Unknown model key: {$key}";
            $operations = [
                [static fn () => $registry->getDriver($key),
                    $registry->getUnavailableMessage($key) ?? "Unknown model key '{$key}'."],
                [static fn () => Bblslug::translate('dummy-secret', 'text', $key, 'hello', proxy: 'http://127.0.0.1:1'),
                    $expected],
            ];
            foreach ($operations as [$operation, $expectedMessage]) {
                try {
                    $operation();
                    self::fail('Expected an invalid model key error');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame($expectedMessage, $exception->getMessage());
                }
            }
        }
    }

    public function testFacadeRejectsHistoricKeyBeforeHttp(): void
    {
        $code = <<<'CODE'
namespace Bblslug {
    class HttpClient {
        public static function request(...$arguments): array {
            throw new \RuntimeException('HTTP unexpectedly reached');
        }
    }
}
namespace {
    require $argv[1];
    try {
        \Bblslug\Bblslug::translate('dummy-secret', 'text', 'openai:gpt-5-mini', 'hello', proxy: 'http://127.0.0.1:1');
        exit(2);
    } catch (\InvalidArgumentException $e) {
        fwrite(STDOUT, $e->getMessage());
    }
}
CODE;
        $process = proc_open(
            [PHP_BINARY, '-r', $code, dirname(__DIR__, 3) . '/vendor/autoload.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        self::assertSame((new ModelRegistry())->getUnavailableMessage('openai:gpt-5-mini'), $output);
    }
}
