<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use Bblslug\Bblslug;
use Bblslug\Models\Drivers\GoogleDriver;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\Drivers\YandexDriver;
use PHPUnit\Framework\TestCase;

final class ExperimentalXmlPromptTest extends TestCase
{
    private const CONFIG = ['endpoint' => 'https://example.test/generate', 'defaults' => [
        'model' => 'fixture-model', 'source_lang' => 'EN', 'target_lang' => 'DE',
    ]];

    public function testExperimentalXmlIsListedAndDefaultJsonRemainsPlainText(): void
    {
        $prompts = Bblslug::listPrompts();
        self::assertSame(['json'], $prompts['experimental_xml']['formats']);
        self::assertStringContainsString('Opt-in XML-style JSON prompt', $prompts['experimental_xml']['notes']);
        self::assertStringStartsWith(
            'You are a professional translator for JSON content.',
            \Bblslug\Models\Prompts::render('translator', 'json', [
                'source' => 'EN', 'target' => 'DE', 'start' => '‹‹TRANSLATION››',
                'end' => '‹‹END››', 'context' => '',
            ])
        );
    }

    /** @dataProvider driverProvider */
    public function testDriversSelectXmlTemplateAndPreserveMarkedUserPayload(string $driverClass): void
    {
        $options = [
            'format' => 'json', 'promptKey' => 'experimental_xml', 'context' => 'Keep paths byte exact',
        ];
        $driver = new $driverClass();
        if ($driver instanceof YandexDriver) {
            $options['folder_id'] = 'fixture-folder';
        }
        $source = '{"message":"Hello","path":"C:\\tmp"}';
        $request = $driver->buildRequest(self::CONFIG, $source, $options);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        $prompt = $this->extractPrompt($driver, $body);
        self::assertStringContainsString('<poml>', $prompt);
        self::assertStringContainsString('Translate from EN to DE.', $prompt);
        self::assertStringContainsString('Context: Keep paths byte exact', $prompt);
        self::assertStringContainsString('‹‹TRANSLATION››', $prompt);
        self::assertStringContainsString('‹‹END››', $prompt);
        $payload = $this->extractUserPayload($driver, $body);
        self::assertSame("‹‹TRANSLATION››\n{$source}\n‹‹END››", $payload);
    }

    /** @return array<string,array{string}> */
    public static function driverProvider(): array
    {
        return [
            'openai' => [OpenAiDriver::class], 'google' => [GoogleDriver::class], 'yandex' => [YandexDriver::class],
        ];
    }

    /** @dataProvider unsupportedFormatProvider */
    public function testExperimentalXmlOnlySupportsJson(string $format): void
    {
        $driver = new OpenAiDriver();
        $this->expectException(\InvalidArgumentException::class);
        $driver->buildRequest(self::CONFIG, 'Hello', ['format' => $format, 'promptKey' => 'experimental_xml']);
    }

    /** @return array<string,array{string}> */
    public static function unsupportedFormatProvider(): array
    {
        return ['text' => ['text'], 'html' => ['html'], 'structured JSON' => ['structured_json']];
    }

    public function testNativeGoogleRejectsExperimentalXmlInsteadOfFallingBack(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Prompt 'experimental_xml.structured_json' not found");
        (new GoogleDriver())->buildRequest(self::CONFIG, '{}', [
            'format' => 'json', 'promptKey' => 'experimental_xml', 'response_schema' => [
                'type' => 'object', 'properties' => ['message' => ['type' => 'string']],
            ],
        ]);
    }

    /** @param array<string,mixed> $body */
    private function extractPrompt(object $driver, array $body): string
    {
        if ($driver instanceof OpenAiDriver) {
            return $body['messages'][0]['content'];
        }
        return $driver instanceof GoogleDriver
            ? $body['system_instruction']['parts'][0]['text']
            : $body['messages'][0]['text'];
    }

    /** @param array<string,mixed> $body */
    private function extractUserPayload(object $driver, array $body): string
    {
        if ($driver instanceof OpenAiDriver) {
            return $body['messages'][1]['content'];
        }
        return $driver instanceof GoogleDriver
            ? $body['contents'][0]['parts'][0]['text']
            : $body['messages'][1]['text'];
    }
}
