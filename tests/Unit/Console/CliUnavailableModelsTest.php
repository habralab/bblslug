<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

final class CliUnavailableModelsTest extends TestCase
{
    public function testHistoricAndUnknownKeysFailBeforeHttp(): void
    {
        $httpLog = tempnam(sys_get_temp_dir(), 'bblslug-http-');
        self::assertIsString($httpLog);
        try {
            $registry = new ModelRegistry();
            foreach (['openai:gpt-5-mini', 'unknown:never-published-model'] as $key) {
                $process = proc_open(
                    [
                        PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/console/unknown_options.php',
                        '--model=' . $key, '--format=text', '--proxy=http://127.0.0.1:1',
                    ],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 3),
                    ['OPENAI_API_KEY' => 'dummy-secret', 'BBLSLUG_TEST_HTTP_LOG' => $httpLog]
                );
                self::assertIsResource($process);
                fwrite($pipes[0], 'hello');
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                self::assertNotSame(0, proc_close($process));
                self::assertSame('', $stdout);
                $expected = $registry->getUnavailableMessage($key) ?? "Unknown model key: {$key}";
                self::assertStringContainsString($expected, $stderr);
                self::assertStringNotContainsString('dummy-secret', $stderr);
                self::assertSame('', file_get_contents($httpLog));
            }
        } finally {
            unlink($httpLog);
        }
    }
}
