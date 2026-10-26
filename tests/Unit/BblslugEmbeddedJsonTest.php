<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugEmbeddedJsonTest extends TestCase
{
    /** @test */
    public function jsonFormatsRejectInvalidEmbeddedJsonWhenOptedIn(): void
    {
        $input = $this->outerWithPayload($this->innerJson([
            'count' => 1,
            'enabled' => true,
            'child' => $this->innerJson(['label' => 'Hello']),
        ]));
        $child = $this->innerJson(['label' => 'Hello']);
        $cases = [
            $this->outerWithPayload('{"count":1'),
            $this->outerWithPayload($this->innerJson(['count' => ['one'], 'enabled' => true, 'child' => $child])),
            $this->outerWithPayload($this->innerJson(['count' => 2, 'enabled' => true, 'child' => $child])),
            $this->outerWithPayload($this->innerJson(['count' => 1, 'enabled' => false, 'child' => $child])),
        ];

        foreach (['json', 'linedJson'] as $format) {
            foreach ($cases as $translated) {
                $result = $this->runJsonFixture([
                    'format' => $format,
                    'input' => $input,
                    'translated' => $translated,
                    'validateEmbeddedJson' => true,
                ]);

                self::assertSame(\RuntimeException::class, $result['type']);
                self::assertStringContainsString('Embedded JSON mismatch', $result['message']);
            }
        }
    }

    /** @test */
    public function optedInJsonFormatsAllowTranslatedNestedStringsAndReorderedKeys(): void
    {
        $input = $this->outerWithPayload($this->innerJson([
            'title' => 'Hello',
            'child' => $this->innerJson(['items' => ['One', 'Two']]),
        ]));
        $translated = $this->outerWithPayload($this->innerJson([
            'child' => $this->innerJson(['items' => ['Один', 'Два']]),
            'title' => 'Привет',
        ]));

        foreach (['json', 'linedJson'] as $format) {
            $result = $this->runJsonFixture([
                'format' => $format,
                'input' => $input,
                'translated' => $translated,
                'validateEmbeddedJson' => true,
            ]);

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($translated, $result['result']);
        }
    }

    /** @test */
    public function ordinaryAndMalformedStringsRemainTranslatable(): void
    {
        $input = $this->outer([
            'number' => '123',
            'boolean' => 'true',
            'null' => 'null',
            'malformed' => '{"broken":',
        ]);
        $translated = $this->outer([
            'number' => 'сто двадцать три',
            'boolean' => 'истина',
            'null' => 'пусто',
            'malformed' => 'перевод',
        ]);
        $result = $this->runJsonFixture([
            'input' => $input,
            'translated' => $translated,
            'validateEmbeddedJson' => true,
        ]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame($translated, $result['result']);
    }

    /** @test */
    public function embeddedJsonValidationIsOptInAndRespectsValidateFlag(): void
    {
        $input = $this->outerWithPayload($this->innerJson(['count' => 1]));
        $translated = $this->outerWithPayload($this->innerJson(['count' => ['one']]));

        $optionsList = [
            [],
            ['validateEmbeddedJson' => false],
            ['validate' => false, 'validateEmbeddedJson' => true],
        ];
        foreach ($optionsList as $options) {
            $result = $this->runJsonFixture(array_merge([
                'input' => $input,
                'translated' => $translated,
            ], $options));

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($translated, $result['result']);
        }
    }

    public function testClearedEmbeddedStringsAreRejectedOnlyWhenRequested(): void
    {
        $scenario = [
            'input' => $this->outerWithPayload('{"title":"Source"}'),
            'translated' => $this->outerWithPayload('{"title":""}'),
        ];
        $checked = $this->runJsonFixture($scenario + ['validateEmbeddedJson' => true]);
        self::assertSame(\RuntimeException::class, $checked['type']);
        self::assertStringContainsString('Embedded JSON mismatch', $checked['message']);
        $unchecked = $this->runJsonFixture($scenario);
        self::assertSame('success', $unchecked['type']);
        self::assertSame($scenario['translated'], $unchecked['result']);
    }

    /** @test */
    public function embeddedJsonValidationIsRejectedForNonJsonFormats(): void
    {
        $result = $this->runJsonFixture([
            'format' => 'text',
            'input' => 'Hello',
            'translated' => 'Привет',
            'validateEmbeddedJson' => true,
        ]);

        self::assertSame(\InvalidArgumentException::class, $result['type']);
    }

    /** @test */
    public function structuredJsonProvidersValidateEmbeddedJson(): void
    {
        $input = $this->outerWithPayload($this->innerJson([
            'title' => 'Hello',
            'details' => $this->innerJson(['enabled' => true]),
        ]));
        $translated = $this->outerWithPayload($this->innerJson([
            'title' => 'Привет',
            'details' => $this->innerJson(['enabled' => true]),
        ]));

        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runStructuredFixture([
                'model' => $model,
                'input' => $input,
                'translated' => $translated,
                'schema' => ['type' => 'object', 'additionalProperties' => true],
                'validateEmbeddedJson' => true,
            ]);

            self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
            self::assertSame($translated, $result['result']);
        }
    }

    /** @test */
    public function structuredJsonProvidersRejectChangedEmbeddedScalars(): void
    {
        $input = $this->outerWithPayload($this->innerJson(['count' => 1, 'enabled' => true]));
        $translated = $this->outerWithPayload($this->innerJson(['count' => 2, 'enabled' => false]));

        foreach (['openai:gpt-5-mini', 'google:gemini-2.5-flash'] as $model) {
            $result = $this->runStructuredFixture([
                'model' => $model,
                'input' => $input,
                'translated' => $translated,
                'schema' => ['type' => 'object', 'additionalProperties' => true],
                'validateEmbeddedJson' => true,
            ]);

            self::assertSame(\RuntimeException::class, $result['type']);
            self::assertStringContainsString('Embedded JSON mismatch', $result['message']);
        }
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runJsonFixture(array $scenario): array
    {
        return $this->runFixture('json_output.php', $scenario);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runStructuredFixture(array $scenario): array
    {
        return $this->runFixture('structured_output.php', $scenario);
    }

    /** @param array<string,mixed> $scenario @return array<string,mixed> */
    private function runFixture(string $fixture, array $scenario): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/' . $fixture],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            []
        );
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($scenario, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);

        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $value */
    private function outer(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $value */
    private function innerJson(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function outerWithPayload(string $payload): string
    {
        return $this->outer(['payload' => $payload]);
    }
}
