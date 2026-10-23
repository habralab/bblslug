<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugReservedVariablesTest extends TestCase
{
    /** @return array<string,array{0:string,1:mixed}> */
    public static function reservedVariables(): array
    {
        return [
            'format' => ['format', 'text'],
            'dryRun' => ['dryRun', true],
            'promptKey' => ['promptKey', 'translator'],
            'verbose' => ['verbose', true],
            'context' => ['context', 'context'],
            'responseContext' => ['responseContext', 'context'],
        ];
    }

    /** @dataProvider reservedVariables */
    public function testReservedVariableValuesAreRejectedBeforeFiltersAndHttp(string $name, mixed $value): void
    {
        $result = $this->runFixture([
            'format' => 'json',
            'text' => '<a href="https://example.com">Hello</a>',
            'validate' => false,
            'dryRun' => true,
            'variables' => [$name => $value],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame("Reserved option in variables: {$name}", $result['message']);
        self::assertSame(0, $result['requestCount']);
        self::assertSame('', $result['requestBody']);
    }

    public function testSensitiveReservedValueIsNotIncludedInError(): void
    {
        $sensitive = 'sk-live-secret-value';
        $result = $this->runFixture([
            'validate' => false,
            'variables' => ['format' => $sensitive],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame('Reserved option in variables: format', $result['message']);
        self::assertStringNotContainsString($sensitive, $result['message']);
        self::assertSame(0, $result['requestCount']);
    }

    /** @dataProvider reservedVariables */
    public function testReservedVariableNullIsRejectedWithoutHttp(string $name, mixed $value): void
    {
        $result = $this->runFixture([
            'validate' => false,
            'variables' => [$name => null],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame("Reserved option in variables: {$name}", $result['message']);
        self::assertSame(0, $result['requestCount']);
    }

    public function testFormatOverrideCannotBypassStructuredJsonSchemaAndFormatGuards(): void
    {
        $result = $this->runFixture([
            'format' => 'text',
            'variables' => ['format' => 'structured_json'],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertSame('Reserved option in variables: format', $result['message']);
        self::assertSame(0, $result['requestCount']);
    }

    public function testProviderSpecificVariableReachesOpenAiRequest(): void
    {
        $result = $this->runFixture(['variables' => ['max_completion_tokens' => 123]]);

        self::assertSame('success', $result['type']);
        self::assertSame('Translated', $result['result']);
        self::assertSame(1, $result['requestCount']);
        $request = json_decode($result['requestBody'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(123, $request['max_completion_tokens']);
    }

    public function testDryRunStillSkipsHttpForAllowedProviderVariable(): void
    {
        $result = $this->runFixture([
            'dryRun' => true,
            'variables' => ['temperature' => 0.2],
        ]);

        self::assertSame('success', $result['type']);
        self::assertSame('Hello', $result['result']);
        self::assertSame(0, $result['requestCount']);
        self::assertSame('', $result['requestBody']);
    }

    public function testStructuredJsonStillRequiresResponseSchema(): void
    {
        $result = $this->runFixture([
            'format' => 'structured_json',
            'text' => '{}',
            'variables' => [],
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
        self::assertStringContainsString('requires response_schema', $result['message']);
        self::assertSame(0, $result['requestCount']);
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
