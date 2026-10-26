<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Drivers\DeepLDriver;
use RuntimeException;

/**
 * DeepL-specific tests:
 * - buildRequest formatting for "text", "html" and "json"
 * - parseResponse happy path and error path
 * - request-local JSON batch reconstruction
 */
final class DeepLDriverTest extends TestCase
{
    /** @test */
    public function translationEngineCanBeSelectedWithoutChangingText(): void
    {
        $driver = new DeepLDriver();
        foreach (['quality_optimized', 'prefer_quality_optimized', 'latency_optimized'] as $engine) {
            $request = $driver->buildRequest(
                ['endpoint' => 'https://api.deepl.com/v2/translate'],
                'Hello',
                ['format' => 'text', 'model_type' => $engine]
            );
            parse_str($request['body'], $params);
            $this->assertSame($engine, $params['model_type']);
            $this->assertSame('Hello', $params['text']);
        }
    }

    /** @test */
    public function unsupportedTranslationEngineIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DeepLDriver())->buildRequest([], 'Hello', ['model_type' => 'unknown']);
    }

    /** @test */
    public function buildRequestTextFormat(): void
    {
        $driver = new DeepLDriver();
        $config = [
            'endpoint' => 'https://api.deepl.com/v2/translate',
            'requirements' => ['headers' => ['Authorization: DeepL-Auth-Key test']],
            'defaults' => ['target_lang' => 'EN', 'formality' => 'prefer_more', 'format' => 'text'],
        ];

        $req = $driver->buildRequest($config, 'Hello', ['format' => 'text']);

        $this->assertSame($config['endpoint'], $req['url']);
        $this->assertContains('Authorization: DeepL-Auth-Key test', $req['headers']);

        parse_str($req['body'], $params);
        $this->assertSame('Hello', $params['text']);
        $this->assertSame('EN', $params['target_lang']);
        $this->assertArrayNotHasKey('tag_handling', $params);
    }

    /** @test */
    public function buildRequestHtmlFormatSetsFlags(): void
    {
        $driver = new DeepLDriver();
        $config = [
            'endpoint' => 'X',
            'requirements' => ['headers' => []],
            'defaults' => ['target_lang' => 'EN', 'format' => 'html'],
        ];

        $req = $driver->buildRequest($config, '<p>Hello</p>', ['format' => 'html']);

        parse_str($req['body'], $params);
        $this->assertSame('html', $params['tag_handling']);
        $this->assertSame('1', $params['preserve_formatting']);
        $this->assertSame('1', $params['outline_detection']);
    }

    /** @test */
    public function buildRequestJsonFormatSendsOnlyStringValues(): void
    {
        $driver = new DeepLDriver();
        $input = '{"title":"Hello","id":123,"enabled":true,"nested":{"label":"World"}}';
        $req = $driver->buildRequest([
            'endpoint' => 'X',
            'requirements' => ['headers' => ['Content-Type: application/x-www-form-urlencoded', 'X-Test: retained']],
        ], $input, ['format' => 'json', 'json_transport' => 'text_batch']);
        $params = json_decode($req['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['Hello', 'World'], $params['text']);
        $this->assertArrayNotHasKey('tag_handling', $params);
        $this->assertContains('Content-Type: application/json', $req['headers']);
        $this->assertNotContains('Content-Type: application/x-www-form-urlencoded', $req['headers']);
        $this->assertContains('X-Test: retained', $req['headers']);
        $raw = json_encode(['translations' => [['text' => 'Hallo'], ['text' => 'Welt']]], JSON_THROW_ON_ERROR);
        $parsed = $driver->parseResponse([], $raw, ['format' => 'json', 'responseContext' => $req['responseContext']]);
        $this->assertSame('{"title":"Hallo","id":123,"enabled":true,"nested":{"label":"Welt"}}', $parsed['text']);
    }

    public function testHtmlRequestContextRemovesOnlyItsTransportWrappers(): void
    {
        $driver = new DeepLDriver();
        $request = $driver->buildRequest([], '<p>@@0@@</p>', ['format' => 'html']);
        parse_str($request['body'], $payload);
        $this->assertStringContainsString('translate="no"', $payload['text']);
        $raw = json_encode(['translations' => [['text' => $payload['text']]]], JSON_THROW_ON_ERROR);
        $parsed = $driver->parseResponse([], $raw, [
            'format' => 'html', 'responseContext' => $request['responseContext'],
        ]);
        $this->assertSame('<p>@@0@@</p>', $parsed['text']);
    }

    public function testHtmlDriverReuseKeepsRequestContextsIndependent(): void
    {
        $driver = new DeepLDriver();
        $requests = [
            $driver->buildRequest([], '<p>@@0@@</p>', ['format' => 'html']),
            $driver->buildRequest([], '<div>@@1@@</div>', ['format' => 'html']),
        ];
        foreach ([1 => '<div>@@1@@</div>', 0 => '<p>@@0@@</p>'] as $index => $expected) {
            parse_str($requests[$index]['body'], $payload);
            $raw = json_encode(['translations' => [['text' => $payload['text']]]], JSON_THROW_ON_ERROR);
            $out = $driver->parseResponse([], $raw, ['responseContext' => $requests[$index]['responseContext']]);
            $this->assertSame($expected, $out['text']);
        }
    }

    public function testHtmlWrappingRespectsProviderBodyLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('128 KiB');
        (new DeepLDriver())->buildRequest([], '<p>' . str_repeat('@@0@@ ', 2000) . '</p>', ['format' => 'html']);
    }

    public function testTextRequestAtTheSerializedBodyLimitIsAccepted(): void
    {
        $baseLength = strlen(http_build_query([
            'text' => '', 'target_lang' => 'EN', 'formality' => 'prefer_more',
        ]));
        $text = str_repeat('a', 128 * 1024 - $baseLength);

        $request = (new DeepLDriver())->buildRequest([], $text, ['format' => 'text']);

        $this->assertSame(128 * 1024, strlen($request['body']));
    }

    public function testTextRequestBodyLimitIncludesContextAndMultibyteEncoding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('128 KiB');

        (new DeepLDriver())->buildRequest(
            ['defaults' => ['context' => str_repeat('é', 30_000)]],
            'Hello',
            ['format' => 'text']
        );
    }

    public function testMultibyteSourceWithinCharacterLimitCanExceedSerializedBodyLimit(): void
    {
        $source = str_repeat('Я', 24_000);
        $config = (new \Bblslug\Models\ModelRegistry())->get('deepl:free');
        self::assertNotNull($config);
        $guard = \Bblslug\Validation\TextLengthValidator::fromModelConfig($config);
        self::assertTrue($guard->validate($source)->isValid());
        foreach (['text', 'html'] as $format) {
            try {
                (new DeepLDriver())->buildRequest($config, $source, ['format' => $format]);
                self::fail('The serialized body must be checked in addition to the character limit');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringContainsString('128 KiB', $exception->getMessage());
            }
        }
    }

    public function testUnchangedHtmlRequestStillRespectsProviderBodyLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('128 KiB');

        (new DeepLDriver())->buildRequest([], str_repeat('a', 131_100), ['format' => 'html']);
    }

    /** @test */
    public function parseResponseOk(): void
    {
        $driver = new DeepLDriver();
        // DeepL does NOT wrap with START/END markers; it returns plain translated text.
        $json = json_encode(['translations' => [['text' => 'Salut']]], JSON_UNESCAPED_UNICODE);

        $out = $driver->parseResponse([], $json);
        $this->assertSame('Salut', $out['text']);
        $this->assertNull($out['usage']); // DeepL usage is null by design
    }

    /** @test */
    public function parseResponsePreservesLiteralFormerPseudoTagsInText(): void
    {
        $driver = new DeepLDriver();
        $raw = json_encode(['translations' => [['text' => '<jlc/> literal <jqt/>']]], JSON_THROW_ON_ERROR);
        $this->assertSame('<jlc/> literal <jqt/>', $driver->parseResponse([], $raw)['text']);
    }

    public function testIndependentRequestContextsCanBeParsedInReverseOrder(): void
    {
        $driver = new DeepLDriver();
        $first = $driver->buildRequest([], '{"first":"One"}', ['format' => 'json', 'json_transport' => 'text_batch']);
        $second = $driver->buildRequest(
            [],
            '{"second":"Two"}',
            ['format' => 'linedJson', 'json_transport' => 'text_batch']
        );
        $raw = json_encode(['translations' => [['text' => 'Zwei']]], JSON_THROW_ON_ERROR);
        $this->assertSame(
            '{"second":"Zwei"}',
            $driver->parseResponse([], $raw, ['responseContext' => $second['responseContext']])['text']
        );
        $raw = json_encode(['translations' => [['text' => 'Eins']]], JSON_THROW_ON_ERROR);
        $this->assertSame(
            '{"first":"Eins"}',
            $driver->parseResponse([], $raw, ['responseContext' => $first['responseContext']])['text']
        );
    }

    public function testJsonWithNoTranslatableValuesProvidesLocalResult(): void
    {
        $request = (new DeepLDriver())->buildRequest(
            [],
            '{"id":123,"empty":" "}',
            ['format' => 'json', 'json_transport' => 'text_batch']
        );
        $this->assertSame('{"id":123,"empty":" "}', $request['localResult']);
    }

    public function testJsonResponseCannotBeParsedWithoutItsContext(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('responseContext');
        (new DeepLDriver())->parseResponse(
            [],
            '{"translations":[{"text":"Hallo"}]}',
            ['format' => 'json', 'json_transport' => 'text_batch']
        );
    }

    public function testMissingBatchTranslationsAreRejected(): void
    {
        $driver = new DeepLDriver();
        $request = $driver->buildRequest([], '["One","Two"]', ['format' => 'json', 'json_transport' => 'text_batch']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('count mismatch');
        $raw = '{"translations":[{"text":"Eins"}]}';
        $driver->parseResponse([], $raw, ['responseContext' => $request['responseContext']]);
    }

    public function testOversizedJsonRequestIsRejectedBeforeHttp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('128 KiB');
        (new DeepLDriver())->buildRequest(
            [],
            json_encode(str_repeat('x', 131072)),
            ['format' => 'json', 'json_transport' => 'text_batch']
        );
    }

    /** @test */
    public function parseResponseUnexpectedShapeThrows(): void
    {
        $driver = new DeepLDriver();
        $json = json_encode(['unexpected' => 'shape'], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DeepL translation failed/i');
        $driver->parseResponse([], $json);
    }
}
