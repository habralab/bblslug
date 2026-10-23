<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugUnclosedFilterTest extends TestCase
{
    /** @dataProvider facadeScenarioProvider */
    public function testUnclosedFilterFailsBeforeHttpForValidationModes(array $scenario): void
    {
        $result = $this->runFixture($scenario);

        self::assertSame('RuntimeException', $result['type']);
        self::assertStringContainsString('div', $result['message']);
        self::assertStringNotContainsString('synthetic-secret', $result['message']);
        self::assertSame(0, $result['requests']);
    }

    /** @return array<string,array{array<string,mixed>}> */
    public static function facadeScenarioProvider(): array
    {
        $source = '<div>synthetic-secret';
        return [
            'html validation enabled' => [[
                'format' => 'html', 'input' => $source, 'filters' => ['html_div'], 'validate' => true,
            ]],
            'html validation disabled' => [[
                'format' => 'html', 'input' => $source, 'filters' => ['html_div'], 'validate' => false,
            ]],
            'html dry run' => [[
                'format' => 'html', 'input' => $source, 'filters' => ['html_div'], 'validate' => true, 'dryRun' => true,
            ]],
            'json validation enabled' => [[
                'format' => 'json', 'input' => '{"body":"<div>synthetic-secret"}',
                'filters' => ['html_div'], 'validate' => true, 'validateEmbeddedJson' => true,
            ]],
            'json validation disabled' => [[
                'format' => 'json', 'input' => '{"body":"<div>synthetic-secret"}',
                'filters' => ['html_div'], 'validate' => false, 'validateEmbeddedJson' => false,
            ]],
        ];
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/unclosed_filter.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
