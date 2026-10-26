<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\YandexDriver;
use PHPUnit\Framework\TestCase;

final class YandexHtmlFenceTest extends TestCase
{
    private const CONFIG = [
        'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
        'defaults' => ['model' => 'yandexgpt-lite/latest'],
        'requirements' => ['headers' => ['Content-Type: application/json']],
    ];

    public function testHtmlRequestCarriesSourceFenceContext(): void
    {
        $driver = new YandexDriver();

        $request = $driver->buildRequest(self::CONFIG, '<p>Hello</p>', [
            'format' => 'html',
            'folder_id' => 'b1folder',
        ]);
        self::assertSame(['html_fence_free' => true], $request['responseContext']);

        $fenced = $driver->buildRequest(self::CONFIG, "<p>```</p>", [
            'format' => 'html',
            'folder_id' => 'b1folder',
        ]);
        self::assertSame(['html_fence_free' => false], $fenced['responseContext']);

        $entityFenced = $driver->buildRequest(self::CONFIG, '<p>&grave;&grave;&grave;</p>', [
            'format' => 'html',
            'folder_id' => 'b1folder',
        ]);
        self::assertSame(['html_fence_free' => false], $entityFenced['responseContext']);

        $tildeEntityFenced = $driver->buildRequest(self::CONFIG, '<p>&#126;&#126;&#126;</p>', [
            'format' => 'html',
            'folder_id' => 'b1folder',
        ]);
        self::assertSame(['html_fence_free' => false], $tildeEntityFenced['responseContext']);

        $text = $driver->buildRequest(self::CONFIG, 'Hello', [
            'format' => 'text',
            'folder_id' => 'b1folder',
        ]);
        self::assertNull($text['responseContext'] ?? null);
    }

    /** @dataProvider allowedOpeningFences */
    public function testOnlyOneAllowedOpeningFenceIsRemoved(string $prefix): void
    {
        $result = (new YandexDriver())->parseResponse(
            self::CONFIG,
            $this->response($prefix . '<p>Hallo</p>'),
            ['format' => 'html', 'responseContext' => ['html_fence_free' => true]]
        );

        self::assertSame('<p>Hallo</p>', $result['text']);
    }

    /** @return array<string,array{string}> */
    public static function allowedOpeningFences(): array
    {
        return [
            'backtick' => ["```\n"],
            'html label' => ["```html\n"],
            'uppercase html label and crlf' => ["```HTML\r\n"],
            'indented fence' => ["  ```  html  \n"],
        ];
    }

    /** @dataProvider unsupportedFences */
    public function testAnyOtherIntroducedFenceIsRejected(string $translated): void
    {
        $this->expectException(\RuntimeException::class);
        (new YandexDriver())->parseResponse(
            self::CONFIG,
            $this->response($translated),
            ['format' => 'html', 'responseContext' => ['html_fence_free' => true]]
        );
    }

    /** @return array<string,array{string}> */
    public static function unsupportedFences(): array
    {
        return [
            'paired' => ["```html\n<p>Hallo</p>\n```"],
            'closing only' => ["<p>Hallo</p>\n```"],
            'tilde' => ["~~~html\n<p>Hallo</p>"],
            'unknown label' => ["```json\n<p>Hallo</p>"],
            'metadata' => ["```html extra\n<p>Hallo</p>"],
            'non-exact opening length' => ["````html\n<p>Hallo</p>"],
            'multiple opening fences' => ["```\n```\n<p>Hallo</p>"],
            'inline fence' => ['<p>Hallo ``` Welt</p>'],
            'placeholder in fence line' => ["```html @@0@@\n<p>Hallo</p>"],
            'commentary before fence' => ["Here is the translation:\n```html\n<p>Hallo</p>"],
        ];
    }

    public function testMissingContextKeepsLegacyResponseUnchanged(): void
    {
        $translated = "```\n<p>Hallo</p>";
        $result = (new YandexDriver())->parseResponse(self::CONFIG, $this->response($translated), [
            'format' => 'html',
        ]);

        self::assertSame($translated, $result['text']);
    }

    public function testNormalUnfencedHtmlIsUnchanged(): void
    {
        $result = (new YandexDriver())->parseResponse(
            self::CONFIG,
            $this->response('<p>Hallo</p>'),
            ['format' => 'html', 'responseContext' => ['html_fence_free' => true]]
        );

        self::assertSame('<p>Hallo</p>', $result['text']);
    }

    public function testSourceFenceContextPreservesFencedResponse(): void
    {
        $translated = "```html\n<p>Hallo</p>\n```";
        $result = (new YandexDriver())->parseResponse(
            self::CONFIG,
            $this->response($translated),
            ['format' => 'html', 'responseContext' => ['html_fence_free' => false]]
        );

        self::assertSame($translated, $result['text']);
    }

    public function testResponseContextIsRequestLocalWhenRequestsInterleave(): void
    {
        $driver = new YandexDriver();
        $free = $driver->buildRequest(self::CONFIG, '<p>One</p>', [
            'format' => 'html', 'folder_id' => 'b1folder',
        ]);
        $fenced = $driver->buildRequest(self::CONFIG, "```html\n<p>Two</p>\n```", [
            'format' => 'html', 'folder_id' => 'b1folder',
        ]);

        $first = $driver->parseResponse(self::CONFIG, $this->response("```\n<p>One</p>"), [
            'format' => 'html', 'responseContext' => $free['responseContext'],
        ]);
        $second = $driver->parseResponse(self::CONFIG, $this->response("```html\n<p>Two</p>\n```"), [
            'format' => 'html', 'responseContext' => $fenced['responseContext'],
        ]);

        self::assertSame('<p>One</p>', $first['text']);
        self::assertSame("```html\n<p>Two</p>\n```", $second['text']);
    }

    private function response(string $text): string
    {
        return (string) json_encode([
            'result' => [
                'alternatives' => [[
                    'status' => 'ALTERNATIVE_STATUS_FINAL',
                    'message' => ['text' => "‹‹TRANSLATION››\n{$text}\n‹‹END››"],
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
