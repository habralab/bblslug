<?php

declare(strict_types=1);

namespace Bblslug\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Bblslug\Bblslug;

/**
 * Live integration tests against real vendors.
 *
 * Control flags:
 * - RUN_LIVE_TESTS=1      — enable this suite
 * - {VENDOR}_API_KEY      — per-vendor API keys (OPENAI_API_KEY, ANTHROPIC_API_KEY, GOOGLE_API_KEY,
 *                           XAI_API_KEY, YANDEX_API_KEY, DEEPL_API_KEY)
 * - YANDEX_FOLDER_ID      — required for Yandex
 * - BBLSLUG_PROXY         — optional proxy (http://..., socks5h://...)
 */
final class LiveTranslatePerVendorTest extends TestCase
{
    /** Map vendor => required ENV for API key */
    private const KEY_ENV = [
        'openai'    => 'OPENAI_API_KEY',
        'anthropic' => 'ANTHROPIC_API_KEY',
        'google'    => 'GOOGLE_API_KEY',
        'xai'       => 'XAI_API_KEY',
        'yandex'    => 'YANDEX_API_KEY',
        'deepl'     => 'DEEPL_API_KEY',
    ];

    protected function setUp(): void
    {
        if (!getenv('RUN_LIVE_TESTS')) {
            $this->markTestSkipped('Set RUN_LIVE_TESTS=1 to run live integration tests');
        }
    }

    /**
     * Build a list of (vendor, modelKey, apiKey, extraVariables) for which we have credentials.
     * Uses the first model for each vendor (to avoid hardcoding names).
     *
     * @return array<string,array{string,string,string,array<string,mixed>}>
     */
    public function providerLiveVendors(): array
    {
        $cases = [];
        $modelsByVendor = Bblslug::listModels();

        foreach ($modelsByVendor as $vendor => $models) {
            if (!isset(self::KEY_ENV[$vendor])) {
                continue; // unknown/unsupported vendor in this test
            }
            $apiEnv = self::KEY_ENV[$vendor];
            $apiKey = (string) getenv($apiEnv);

            if ($apiKey === '') {
                continue; // no credentials => skip vendor
            }

            // pick the first model key for this vendor
            $modelKeys = array_keys($models);
            if (empty($modelKeys)) {
                continue;
            }
            $modelKey = $modelKeys[0];

            $vars = [];
            if ($vendor === 'yandex') {
                $folderId = (string) getenv('YANDEX_FOLDER_ID');
                if ($folderId === '') {
                    // cannot test Yandex without folder id
                    continue;
                }
                $vars['folder_id'] = $folderId;
            }

            $cases["{$vendor}::{$modelKey}"] = [$vendor, $modelKey, $apiKey, $vars];
        }

        if (empty($cases)) {
            // ensure test shows as skipped in a readable way
            $this->markTestSkipped('No vendors with credentials found for live tests');
        }

        return $cases;
    }

    /**
     * @dataProvider providerLiveVendors
     * @test
     * @group live
     * @large
     */
    public function translatesSmallTextSnippet(string $vendor, string $modelKey, string $apiKey, array $vars): void
    {
        $proxy = getenv('BBLSLUG_PROXY') ?: null;

        $all = \Bblslug\Bblslug::listPrompts();
        // Быстрые sanity‑проверки:
        $this->assertIsArray($all, 'listPrompts() returned not an array');
        // Должен быть ключ 'translator'
        $this->assertArrayHasKey('translator', $all, 'No "translator" key in prompts');
        // У него должны быть форматы
        $this->assertArrayHasKey('formats', $all['translator'], '"translator" missing formats');
        $this->assertContains('text', $all['translator']['formats'], '"translator" has no "text" format');

        $res = Bblslug::translate(
            apiKey:     $apiKey,
            format:     'text',
            modelKey:   $modelKey,
            text:       'Hello world',
            context:    'Translate as a professional technical translator',
            dryRun:     false,
            filters:    [],
            onFeedback: null,
            proxy:      $proxy ?: null,
            sourceLang: 'EN',
            targetLang: 'DE',
            validate:   false,
            variables:  $vars,
            verbose:    true
        );

        $this->assertIsArray($res);
        $this->assertGreaterThanOrEqual(200, $res['httpStatus']);
        $this->assertLessThan(300, $res['httpStatus']);
        $this->assertIsString($res['result']);
        $this->assertNotSame('', $res['rawResponseBody']);

        // API key must be masked in logs
        $this->assertStringNotContainsString($apiKey, $res['debugRequest']);
        $this->assertStringNotContainsString($apiKey, $res['debugResponse']);
    }

    /**
     * HTML with two-level nesting: <div><p>Hi <strong>there</strong></p></div>
     * We enable validation to ensure container syntax is preserved.
     *
     * @dataProvider providerLiveVendors
     * @test
     * @group live
     * @large
     */
    public function translatesSmallHtmlSnippet(string $vendor, string $modelKey, string $apiKey, array $vars): void
    {
        $proxy = getenv('BBLSLUG_PROXY') ?: null;

        $html = '<div class="wrap"><p>Hi <strong>there</strong>!</p></div>';

        $res = Bblslug::translate(
            apiKey:     $apiKey,
            format:     'html',
            modelKey:   $modelKey,
            text:       $html,
            context:    'Translate as a professional technical translator',
            dryRun:     false,
            filters:    [],
            onFeedback: null,
            proxy:      $proxy ?: null,
            sourceLang: 'EN',
            targetLang: 'DE',
            validate:   true,   // run HTML validator pre/post
            variables:  $vars,
            verbose:    true
        );

        $this->assertGreaterThanOrEqual(200, $res['httpStatus']);
        $this->assertLessThan(300, $res['httpStatus']);
        $this->assertIsString($res['result']);
        $this->assertNotSame('', $res['rawResponseBody']);

        // Minimal sanity: preserved tags should still be present
        $this->assertStringContainsString('<div', $res['result']);
        $this->assertStringContainsString('<p', $res['result']);
        $this->assertStringContainsString('<strong', $res['result']);

        $this->assertStringNotContainsString($apiKey, $res['debugRequest']);
        $this->assertStringNotContainsString($apiKey, $res['debugResponse']);
    }

    /**
     * JSON with double nesting:
     * {
     *   "article": {
     *     "title": "Hello",
     *     "meta": { "desc": "Short text" }
     *   }
     * }
     * JSON validation + schema check are enabled; if the model breaks structure,
     * Bblslug::translate() will throw and the test will fail (by design).
     *
     * @dataProvider providerLiveVendors
     * @test
     * @group live
     * @large
     */
    public function translatesSmallJsonSnippet(string $vendor, string $modelKey, string $apiKey, array $vars): void
    {
        $proxy = getenv('BBLSLUG_PROXY') ?: null;

        $json = json_encode([
            'article' => [
                'title' => 'Hello',
                'meta'  => ['desc' => 'Short text'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $res = Bblslug::translate(
            apiKey:     $apiKey,
            format:     'json',
            modelKey:   $modelKey,
            text:       (string)$json,
            context:    'Translate as a professional technical translator',
            dryRun:     false,
            filters:    [],
            onFeedback: null,
            proxy:      $proxy ?: null,
            sourceLang: 'EN',
            targetLang: 'DE',
            validate:   true,   // JSON syntax + schema validation
            variables:  $vars,
            verbose:    true
        );

        $this->assertGreaterThanOrEqual(200, $res['httpStatus']);
        $this->assertLessThan(300, $res['httpStatus']);
        $this->assertIsString($res['result']);
        $this->assertNotSame('', $res['rawResponseBody']);

        // Must still be valid JSON
        $decoded = json_decode($res['result'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('article', $decoded);
        $this->assertArrayHasKey('title', $decoded['article']);
        $this->assertArrayHasKey('meta', $decoded['article']);
        $this->assertArrayHasKey('desc', $decoded['article']['meta']);

        $this->assertStringNotContainsString($apiKey, $res['debugRequest']);
        $this->assertStringNotContainsString($apiKey, $res['debugResponse']);
    }
}
