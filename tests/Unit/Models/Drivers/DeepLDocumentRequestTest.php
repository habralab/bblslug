<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Models\Drivers;

use Bblslug\Models\Drivers\DeepLDocumentWorkflow;
use Bblslug\Models\Drivers\DeepLDriver;
use PHPUnit\Framework\TestCase;

final class DeepLDocumentRequestTest extends TestCase
{
    private const CONFIG = [
        'endpoint' => 'https://api-free.deepl.com/v2/translate',
        'requirements' => ['headers' => ['Content-Type: application/x-www-form-urlencoded', 'X-Trace: retained']],
        'defaults' => ['target_lang' => 'DE', 'source_lang' => 'EN', 'formality' => 'prefer_less'],
    ];

    public function testMultipartContainsExactSourceAndDerivedDocumentEndpoint(): void
    {
        $source = '{"title":"Hello","count":123}';
        $request = (new DeepLDriver())->buildRequest(self::CONFIG, $source, ['format' => 'json']);
        self::assertSame('https://api-free.deepl.com/v2/document', $request['url']);
        self::assertStringContainsString($source, $request['body']);
        self::assertStringContainsString('name="file"; filename="translation.json"', $request['body']);
        self::assertStringContainsString('name="source_lang"', $request['body']);
        self::assertStringContainsString('name="target_lang"', $request['body']);
        self::assertStringContainsString('name="formality"', $request['body']);
        self::assertNotContains('Content-Type: application/x-www-form-urlencoded', $request['headers']);
        self::assertContains('X-Trace: retained', $request['headers']);
        self::assertStringContainsString('multipart/form-data; boundary=', implode('\n', $request['headers']));
    }

    public function testProEndpointDerivesDocumentEndpointAndReturnsWorkflowMetadata(): void
    {
        $request = (new DeepLDriver())->buildRequest(
            ['endpoint' => 'https://api.deepl.com/v2/translate'],
            '{"title":"Hello"}',
            ['format' => 'json', 'document_timeout' => 9]
        );
        self::assertSame('https://api.deepl.com/v2/document', $request['url']);
        self::assertInstanceOf(DeepLDocumentWorkflow::class, $request['workflow']);
        self::assertSame($request['workflow'], $request['responseContext']);
        self::assertSame(9, $request['timeoutSeconds']);
    }

    public function testCanonicalPositiveIntegerStringTimeoutIsAccepted(): void
    {
        $request = (new DeepLDriver())->buildRequest(
            self::CONFIG,
            '{"title":"Hello"}',
            ['format' => 'json', 'document_timeout' => '5']
        );
        self::assertSame(5, $request['timeoutSeconds']);
    }

    public function testDocumentUploadAllowsMoreThan128KiBButRejectsOverOneMegabyte(): void
    {
        $source = json_encode(['value' => str_repeat('x', 140 * 1024)], JSON_THROW_ON_ERROR);
        $request = (new DeepLDriver())->buildRequest(self::CONFIG, $source, ['format' => 'json']);
        self::assertStringContainsString($source, $request['body']);
        $oversize = json_encode(['value' => str_repeat('x', 1_000_000)], JSON_THROW_ON_ERROR);
        $this->expectExceptionMessage('1 MB');
        (new DeepLDriver())->buildRequest(self::CONFIG, $oversize, ['format' => 'json']);
    }

    /** @dataProvider invalidTimeoutProvider */
    public function testInvalidDocumentTimeoutIsRejectedBeforeWorkflowConstruction(mixed $timeout): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DeepLDriver())->buildRequest(
            self::CONFIG,
            '{"title":"Hello"}',
            ['format' => 'json', 'document_timeout' => $timeout]
        );
    }

    /** @return array<string,array{mixed}> */
    public static function invalidTimeoutProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'float' => [1.5],
            'decimal string' => ['1.5'],
            'nonnumeric string' => ['five'],
            'boolean' => [true],
            'zero string' => ['0'],
            'overflow string' => [str_repeat('9', 100)],
        ];
    }

    public function testUnsupportedTransportAndNativeOnlyOptionsAreRejected(): void
    {
        $driver = new DeepLDriver();
        $this->expectExceptionMessage('json_transport');
        $driver->buildRequest(self::CONFIG, '{"title":"Hello"}', ['format' => 'json', 'json_transport' => 'nope']);
    }

    /** @dataProvider nativeUnsupportedProvider */
    public function testContextAndModelTypeAreRejectedForNativeDocuments(array $defaults, array $options = []): void
    {
        $this->expectExceptionMessage('does not support');
        $config = array_replace_recursive(self::CONFIG, ['defaults' => $defaults]);
        $driverOptions = array_merge(['format' => 'json'], $options);
        (new DeepLDriver())->buildRequest($config, '{"title":"Hello"}', $driverOptions);
    }

    /** @return array<string,array{array<string,mixed>,array<string,mixed>}> */
    public static function nativeUnsupportedProvider(): array
    {
        return [
            'default context' => [['context' => 'context'], []],
            'default model type' => [['model_type' => 'quality_optimized'], []],
            'option model type' => [[], ['model_type' => 'quality_optimized']],
        ];
    }

    public function testNoTranslatableValuesReturnLocalResultWithoutWorkflowCall(): void
    {
        $request = (new DeepLDriver())->buildRequest(
            self::CONFIG,
            '{"id":123,"enabled":true,"empty":""}',
            ['format' => 'json']
        );
        self::assertSame('{"id":123,"enabled":true,"empty":""}', $request['localResult']);
        self::assertInstanceOf(DeepLDocumentWorkflow::class, $request['workflow']);
    }

    public function testDocumentResponseCannotBeParsedBeforeWorkflowContext(): void
    {
        $request = (new DeepLDriver())->buildRequest(self::CONFIG, '{"title":"Hello"}', ['format' => 'json']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('completed workflow');
        (new DeepLDriver())->parseResponse(
            [],
            '{"document_id":"fixture-upload-id","document_key":"fixture-document-key-secret"}',
            ['format' => 'json', 'responseContext' => $request['responseContext']]
        );
    }
}
