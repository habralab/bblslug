<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugEmptyTranslationTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function emptyTranslations(): array
    {
        $cases = [];
        foreach (
            ['openai:gpt-5-mini', 'google:gemini-2.5-flash', 'anthropic:claude-haiku-5.5',
                'xai:grok-4.20-0309-non-reasoning', 'yandex:gpt-lite', 'deepl:free'] as $model
        ) {
            foreach (['' => 'empty', " \t\r\n\u{00a0}\u{2003}" => 'whitespace'] as $value => $label) {
                $cases[$model . ' ' . $label] = [$model, $value];
            }
        }
        return $cases;
    }

    /** @dataProvider emptyTranslations */
    public function testNonemptyInputRequiresNonemptyTranslationEvenWithoutValidation(string $model, string $text): void
    {
        $result = $this->runFixture($model, 'source text', $text);
        self::assertSame(\RuntimeException::class, $result['type']);
        self::assertStringContainsString('empty translation for nonempty input', $result['message']);
        self::assertSame(1, $result['requestCount']);
    }

    public function testEmptyAndWhitespaceInputMayProduceEmptyTranslation(): void
    {
        foreach (['', " \t\r\n\u{00a0}\u{2003}"] as $input) {
            $result = $this->runFixture('openai:gpt-5-mini', $input, '');
            self::assertSame('success', $result['type']);
            self::assertSame('', $result['result']);
        }
    }

    public function testLiteralZeroIsNonemptyInputAndOutput(): void
    {
        $empty = $this->runFixture('openai:gpt-5-mini', '0', '');
        self::assertSame(\RuntimeException::class, $empty['type']);
        $zero = $this->runFixture('openai:gpt-5-mini', 'source', '0');
        self::assertSame('success', $zero['type']);
        self::assertSame('0', $zero['result']);
    }

    /** @return array{type:string,message:string,requestCount:int,result?:string} */
    private function runFixture(string $model, string $input, string $text): array
    {
        $marked = "‹‹TRANSLATION››\n" . $text . "\n‹‹END››";
        $body = match (strstr($model, ':', true)) {
            'google' => ['candidates' => [['finishReason' => 'STOP',
                'content' => ['parts' => [['text' => $marked]]]]]],
            'anthropic' => ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $marked]]],
            'deepl' => ['translations' => [['text' => $text]]],
            default => ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $marked]]]],
        };
        $process = proc_open([
            PHP_BINARY,
            dirname(__DIR__) . '/Fixtures/translation/provider_error.php',
            '--model=' . $model,
            '--status=200',
            '--body=' . base64_encode(json_encode($body, JSON_THROW_ON_ERROR)),
            '--input=' . base64_encode($input),
            '--api-key=fixture-key',
            '--folder-id=fixture-folder',
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), []);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
