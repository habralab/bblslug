<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugInvalidFiltersTest extends TestCase
{
    /** @dataProvider invalidFilters */
    public function testInvalidFiltersAreRejectedBeforeAnyHttpRequest(array $filters, string $message): void
    {
        $result = $this->runFixture([
            'format' => 'text',
            'text' => 'Hello',
            'validate' => false,
            'filters' => $filters,
            'variables' => [],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame($message, $result['message']);
        self::assertSame(0, $result['requestCount']);
        self::assertSame('', $result['requestBody']);
    }

    public function testEmptyFilterListIsPermittedAndDoesNotChangeInput(): void
    {
        $result = $this->runFixture([
            'format' => 'text',
            'text' => 'Hello',
            'validate' => false,
            'filters' => [],
            'variables' => [],
            'dryRun' => true,
        ]);

        self::assertSame('success', $result['type']);
        self::assertSame('Hello', $result['result']);
        self::assertSame(0, $result['requestCount']);
        self::assertSame('', $result['requestBody']);
    }

    /** @return array<string,array{array<int,string>,string}> */
    public static function invalidFilters(): array
    {
        return [
            'unknown filter' => [['urll'], 'Unknown filter: urll'],
            'empty middle token' => [['url', '', 'html_code'], 'Unknown filter: '],
            'empty trailing token' => [['url', ''], 'Unknown filter: '],
            'malformed html filter' => [['html_pre bad'], 'Invalid HTML tag name for filter'],
        ];
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/reserved_variables.php'],
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

        /** @var array<string,mixed> */
        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
