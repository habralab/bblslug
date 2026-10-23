<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;

final class BblslugHtmlOutputTest extends TestCase
{
    /** @test */
    public function changedHtmlStructureIsRejected(): void
    {
        $cases = [
            'Markdown opening fence with otherwise matching markup' => [
                '<div><p>Hello</p></div>', "```\n<div><p>Привет</p></div>",
            ],
            'dropped tag' => ['<p>Hello <strong>world</strong></p>', '<p>Hola mundo</p>'],
            'added tag' => ['<p>Hello world</p>', '<p><strong>Hola mundo</strong></p>'],
            'reordered tags' => [
                '<p><em>One</em><strong>Two</strong></p>',
                '<p><strong>Uno</strong><em>Dos</em></p>',
            ],
            'renamed tag' => ['<p>Hello world</p>', '<div>Hola mundo</div>'],
            'changed nesting' => [
                '<p><strong>Hello</strong></p>',
                '<strong><p>Hola</p></strong>',
            ],
        ];

        foreach ($cases as $name => [$input, $translated]) {
            $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

            self::assertSame('RuntimeException', $result['type'], $name);
            self::assertStringContainsString('HTML structure mismatch', $result['message'], $name);
        }
    }

    /** @test */
    public function requiredAndNonTranslatableAttributesArePreserved(): void
    {
        $cases = [
            'missing href' => ['<a href="https://example.com">Link</a>', '<a>Ссылка</a>'],
            'missing id' => ['<p id="intro">Text</p>', '<p>Текст</p>'],
            'missing class' => ['<p class="lead">Text</p>', '<p>Текст</p>'],
            'missing data attribute' => ['<p data-kind="note">Text</p>', '<p>Текст</p>'],
            'changed href' => [
                '<a href="https://example.com/old">Link</a>',
                '<a href="https://example.com/new">Ссылка</a>',
            ],
            'changed id' => ['<p id="intro">Text</p>', '<p id="outro">Текст</p>'],
            'changed class' => ['<p class="lead">Text</p>', '<p class="body">Текст</p>'],
            'changed data attribute' => [
                '<p data-kind="note">Text</p>',
                '<p data-kind="warning">Текст</p>',
            ],
        ];

        foreach ($cases as $name => [$input, $translated]) {
            $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

            self::assertSame('RuntimeException', $result['type'], $name);
            self::assertStringContainsString('HTML structure mismatch', $result['message'], $name);
        }
    }

    /** @test */
    public function commentsAndDoctypeArePreserved(): void
    {
        $cases = [
            'removed comment' => [
                '<!DOCTYPE html><!-- keep --><p>Text</p>',
                '<!DOCTYPE html><p>Текст</p>',
            ],
            'changed comment' => [
                '<!DOCTYPE html><!-- keep --><p>Text</p>',
                '<!DOCTYPE html><!-- changed --><p>Текст</p>',
            ],
            'changed doctype' => [
                '<!DOCTYPE html><p>Text</p>',
                '<!DOCTYPE svg><p>Текст</p>',
            ],
        ];

        foreach ($cases as $name => [$input, $translated]) {
            $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

            self::assertSame('RuntimeException', $result['type'], $name);
            self::assertStringContainsString('HTML structure mismatch', $result['message'], $name);
        }
    }

    /** @test */
    public function textAndTranslatableAttributesMayChange(): void
    {
        $input = '<p title="Original title"><img alt="Original alt" aria-label="Original label">Text</p>';
        $translated = '<p title="Переведённый заголовок"><img alt="Переведённый alt" '
            . 'aria-label="Переведённая метка">Текст</p>';
        $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame($translated, $result['result']);
    }

    /** @test */
    public function equivalentDomStructurePassesAndReturnsProviderBytes(): void
    {
        $input = '<!DOCTYPE html><DIV ID="intro" class="lead"><a '
            . 'href="https://example.com?a=1&amp;b=2">Text</a></DIV>';
        $translated = '<!doctype html><div class="lead" id="intro"><a '
            . 'href="https://example.com?a=1&#38;b=2">Текст</a></div>';
        $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame($translated, $result['result']);
    }

    /** @test */
    public function fullDocumentStructureIsChecked(): void
    {
        $input = '<!DOCTYPE html><html><head><title>Title</title></head><body><main>Text</main></body></html>';
        $translated = '<!DOCTYPE html><html><head><title>Заголовок</title></head>'
            . '<body><main>Текст</main></body></html>';
        $result = $this->runFixture(['input' => $input, 'translated' => $translated]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame($translated, $result['result']);
    }

    /** @test */
    public function validationCanBeDisabledForLegacyBehavior(): void
    {
        $input = '<p>Text</p>';
        $translated = '<div>Текст</div>';
        $result = $this->runFixture([
            'input' => $input,
            'translated' => $translated,
            'validate' => false,
        ]);

        self::assertSame('success', $result['type'], (string) ($result['message'] ?? ''));
        self::assertSame($translated, $result['result']);
    }

    /** @param array{input:string,translated:string,validate?:bool} $scenario */
    private function runFixture(array $scenario): array
    {
        $scenario['format'] = 'html';
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/translation/json_output.php'],
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
        self::assertSame(0, proc_close($process), $stderr);

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result, $stdout);
        return $result;
    }
}
