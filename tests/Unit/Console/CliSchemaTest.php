<?php

declare(strict_types=1);

namespace Bblslug\Tests\Console;

use PHPUnit\Framework\TestCase;

final class CliSchemaTest extends TestCase
{
    public function testSchemaFileIsAcceptedForStructuredJson(): void
    {
        $result = $this->runCli('{"type":"object","properties":{"message":{"type":"string"}}}');
        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertSame('{"message":"Hello"}', $result['stdout']);
    }

    public function testSchemaFileIsAcceptedByAdditionalNativeDrivers(): void
    {
        foreach (['xai:grok-4.7', 'yandex:gpt-lite'] as $model) {
            $result = $this->runCli('{"type":"object"}', 'structured_json', $model);
            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertSame('{"message":"Hello"}', $result['stdout']);
        }
    }

    public function testMalformedSchemaFileIsRejected(): void
    {
        $result = $this->runCli('{broken');
        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('Invalid JSON in --response-schema', $result['stderr']);
    }

    public function testListAndEmptySchemasAreRejected(): void
    {
        foreach (['[]', '{}', '[{"type":"string"}]'] as $schema) {
            $result = $this->runCli($schema);
            self::assertNotSame(0, $result['exitCode']);
            self::assertStringContainsString('nonempty JSON Schema object', $result['stderr']);
        }
    }

    public function testStructuredJsonRequiresSchemaFile(): void
    {
        $result = $this->runCli(null);
        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('requires --response-schema', $result['stderr']);
    }

    public function testSchemaCannotBeAppliedToTextFormat(): void
    {
        $result = $this->runCli('{"type":"object"}', 'text');
        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('requires a JSON format', $result['stderr']);
    }

    /** @return array{exitCode:int,stdout:string,stderr:string} */
    private function runCli(
        ?string $schema,
        string $format = 'structured_json',
        string $model = 'openai:gpt-5-mini'
    ): array {
        $schemaFile = tempnam(sys_get_temp_dir(), 'bblslug-schema-');
        self::assertIsString($schemaFile);
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 3) . '/bblslug.php',
            '--model=' . $model,
            '--format=' . $format,
            '--dry-run',
        ];
        if ($schema !== null) {
            file_put_contents($schemaFile, $schema);
            $command[] = '--response-schema=' . $schemaFile;
        }
        try {
            $process = proc_open(
                $command,
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                ['BBLSLUG_OPENAI_API_KEY' => 'fixture-key', 'BBLSLUG_XAI_API_KEY' => 'fixture-key',
                    'BBLSLUG_YANDEX_API_KEY' => 'fixture-key', 'BBLSLUG_YANDEX_FOLDER_ID' => 'fixture-folder']
            );
            self::assertIsResource($process);
            fwrite($pipes[0], '{"message":"Hello"}');
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            return [
                'exitCode' => proc_close($process),
                'stdout' => (string) $stdout,
                'stderr' => (string) $stderr,
            ];
        } finally {
            unlink($schemaFile);
        }
    }
}
