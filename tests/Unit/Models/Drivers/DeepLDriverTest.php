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
 * - restoration of pseudo-tags used to protect JSON characters
 */
final class DeepLDriverTest extends TestCase
{
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
    public function buildRequestJsonFormatProtectsJsonChars(): void
    {
        $driver = new DeepLDriver();
        $config = [
            'endpoint' => 'X',
            'requirements' => ['headers' => []],
            'defaults' => ['target_lang' => 'EN', 'format' => 'json'],
        ];

        $input = '{"a":[1,2],"b":"x:y","c":"q,w"}';
        $req = $driver->buildRequest($config, $input, ['format' => 'json']);

        parse_str($req['body'], $params);
        $this->assertArrayHasKey('text', $params);
        // Protected placeholders should be present; raw JSON braces/colons/commas should be absent
        $this->assertStringNotContainsString('{', $params['text']);
        $this->assertStringNotContainsString('}', $params['text']);
        $this->assertStringNotContainsString(':', $params['text']);
        $this->assertStringNotContainsString(',', $params['text']);
        $this->assertStringContainsString('<jlc/>', $params['text']); // protected left curly
        $this->assertSame('EN', $params['target_lang']);
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
    public function parseResponseRestoresJsonPseudoTags(): void
    {
        $driver = new DeepLDriver();
        // Simulate DeepL returning protected placeholders (from JSON mode)
        $json = json_encode(['translations' => [[
            'text' => '<jlc/>"a"<jcol/><jlb/>1<jcomma/>2<jrb/><jrc/>',
        ]]], JSON_UNESCAPED_UNICODE);

        $out = $driver->parseResponse([], $json);
        $this->assertSame('{"a":[1,2]}', $out['text']);
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
