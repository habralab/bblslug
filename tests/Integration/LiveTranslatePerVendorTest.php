<?php

declare(strict_types=1);

namespace Bblslug\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;

/**
 * Live integration tests against real vendors.
 *
 * Control flags:
 * - RUN_LIVE_TESTS=1      — enable this suite
 * - BBLSLUG_{VENDOR}_API_KEY — preferred per-vendor API keys; unprefixed names remain supported
 * - {VENDOR}_API_KEY      — per-vendor API keys (OPENAI_API_KEY, ANTHROPIC_API_KEY, GOOGLE_API_KEY,
 *                           XAI_API_KEY, YANDEX_API_KEY, DEEPL_FREE_API_KEY, DEEPL_PRO_API_KEY)
 * - BBLSLUG_LIVE_MODEL   — optional exact model key; default: first model per vendor
 * - BBLSLUG_YANDEX_FOLDER_ID or YANDEX_FOLDER_ID — required for Yandex
 * - BBLSLUG_PROXY         — optional proxy (http://..., socks5h://...)
 */
final class LiveTranslatePerVendorTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_LIVE_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_LIVE_TESTS=1 to run live integration tests');
        }
    }

    /**
     * Build credential-free (vendor, modelKey) datasets for available vendors.
     * Uses the first model for each vendor (to avoid hardcoding names).
     *
     * @return array<string,array{string,string}>
     */
    public function providerLiveVendors(): array
    {
        // PHPUnit formats datasets before setUp(), including for skipped tests.
        if (getenv('RUN_LIVE_TESTS') !== '1') {
            return ['disabled' => ['', '']];
        }
        $cases = [];
        $modelsByVendor = Bblslug::listModels();
        $registry = new ModelRegistry();
        $selectedModel = getenv('BBLSLUG_LIVE_MODEL') ?: null;
        if ($selectedModel === 'all') {
            $selectedModel = null;
        }
        if ($selectedModel !== null && !$registry->has($selectedModel)) {
            throw new \InvalidArgumentException('Unknown BBLSLUG_LIVE_MODEL');
        }

        foreach ($modelsByVendor as $vendor => $models) {
            // pick the first model key for this vendor
            $modelKeys = array_keys($models);
            if (empty($modelKeys)) {
                continue;
            }
            $modelKey = $selectedModel ?? $modelKeys[0];
            if (!isset($models[$modelKey])) {
                continue;
            }
            $apiEnv = $registry->getAuthEnv($modelKey);
            $apiKey = $apiEnv !== null ? (string) self::getEnvironmentValue($apiEnv) : '';
            if ($apiKey === '') {
                if ($selectedModel !== null) {
                    throw new \RuntimeException('Missing API key for selected model ' . $modelKey);
                }
                continue;
            }

            if ($vendor === 'yandex') {
                $folderId = (string) self::getEnvironmentValue('YANDEX_FOLDER_ID');
                if ($folderId === '') {
                    if ($selectedModel !== null) {
                        throw new \RuntimeException('Missing YANDEX_FOLDER_ID for selected model');
                    }
                    // cannot test Yandex without folder id
                    continue;
                }
            }

            $cases["{$vendor}::{$modelKey}"] = [$vendor, $modelKey];
        }

        if (empty($cases)) {
            // ensure test shows as skipped in a readable way
            $this->markTestSkipped('No vendors with credentials found for live tests');
        }

        return $cases;
    }

    /** @return array{string,array<string,mixed>} */
    private static function liveCredentials(string $vendor, string $modelKey): array
    {
        $apiEnv = (new ModelRegistry())->getAuthEnv($modelKey);
        $apiKey = $apiEnv !== null ? (string) self::getEnvironmentValue($apiEnv) : '';
        if ($apiKey === '') {
            throw new \RuntimeException('Missing API key for selected model ' . $modelKey);
        }
        $vars = $vendor === 'openai' ? ['max_completion_tokens' => 4096] : [];
        if ($vendor === 'yandex') {
            $folderId = (string) self::getEnvironmentValue('YANDEX_FOLDER_ID');
            if ($folderId === '') {
                throw new \RuntimeException('Missing YANDEX_FOLDER_ID for selected model');
            }
            $vars['folder_id'] = $folderId;
        }
        return [$apiKey, $vars];
    }

    /**
     * @return string|false
     */
    private static function getEnvironmentValue(string $name)
    {
        $prefixed = getenv('BBLSLUG_' . $name);
        if ($prefixed !== false && $prefixed !== '') {
            return $prefixed;
        }

        return getenv($name);
    }

    /**
     * @dataProvider providerLiveVendors
     * @test
     * @group live
     * @large
     */
    public function translatesSmallTextSnippet(string $vendor, string $modelKey): void
    {
        [$apiKey, $vars] = self::liveCredentials($vendor, $modelKey);
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
        $this->assertNotSame('', trim($res['result']));
        $this->assertNotSame('Hello world', trim($res['result']), 'Source text was not translated');
        $this->assertNotSame('', $res['rawResponseBody']);

        // API key must be masked in logs
        $this->assertFalse(str_contains($res['debugRequest'], $apiKey), 'API key leaked in request diagnostics');
        $this->assertFalse(str_contains($res['debugResponse'], $apiKey), 'API key leaked in response diagnostics');
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
    public function translatesSmallHtmlSnippet(string $vendor, string $modelKey): void
    {
        [$apiKey, $vars] = self::liveCredentials($vendor, $modelKey);
        $proxy = getenv('BBLSLUG_PROXY') ?: null;

        $html = '<div class="wrap"><p>Hi <strong>there</strong>!</p>'
            . '<a href="https://example.org/docs?a=1&amp;b=2">Documentation</a>'
            . '<pre><code>echo "a\\b";</code></pre></div>';

        $res = Bblslug::translate(
            apiKey:     $apiKey,
            format:     'html',
            modelKey:   $modelKey,
            text:       $html,
            context:    'Translate as a professional technical translator',
            dryRun:     false,
            filters:    ['url', 'html_pre'],
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
        $this->assertStringNotContainsString('```', $res['result'], 'Unexpected Markdown around HTML');
        $this->assertStringContainsString('https://example.org/docs?a=1&amp;b=2', $res['result']);
        $this->assertStringContainsString('<pre><code>echo "a\\b";</code></pre>', $res['result']);
        $dom = new \DOMDocument();
        $dom->loadHTML($res['result'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, (int) $xpath->evaluate('count(/div/a)'), 'Link moved in HTML tree');
        $this->assertSame(1, (int) $xpath->evaluate('count(/div/pre/code)'), 'Code moved in HTML tree');

        $this->assertFalse(str_contains($res['debugRequest'], $apiKey), 'API key leaked in request diagnostics');
        $this->assertFalse(str_contains($res['debugResponse'], $apiKey), 'API key leaked in response diagnostics');
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
    public function translatesSmallJsonSnippet(string $vendor, string $modelKey): void
    {
        [$apiKey, $vars] = self::liveCredentials($vendor, $modelKey);
        $proxy = getenv('BBLSLUG_PROXY') ?: null;

        $json = json_encode([
            'article' => [
                'title' => 'Hello',
                'meta'  => ['desc' => 'Short text'],
                'body' => '<p title="Greeting">Hello <strong>world</strong>!</p>',
            ],
            'id' => 123,
            'enabled' => true,
            'optional' => null,
            'embeddedJson' => json_encode(['message' => 'Hello', 'path' => 'C:\\tmp\\"x"']),
        ], JSON_UNESCAPED_UNICODE);

        $res = Bblslug::translate(
            apiKey:     $apiKey,
            format:     'json',
            modelKey:   $modelKey,
            text:       (string)$json,
            context:    $vendor === 'deepl' ? null : 'Translate as a professional technical translator',
            dryRun:     false,
            filters:    [],
            onFeedback: null,
            proxy:      $proxy ?: null,
            sourceLang: 'EN',
            targetLang: 'DE',
            validate:   true,   // JSON syntax + schema validation
            variables:  $vars,
            verbose:    true,
            validateEmbeddedJson: true
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
        $this->assertNotSame('Hello', $decoded['article']['title']);
        $this->assertSame(123, $decoded['id']);
        $this->assertTrue($decoded['enabled']);
        $this->assertArrayHasKey('optional', $decoded);
        $this->assertNull($decoded['optional']);
        $embedded = json_decode($decoded['embeddedJson'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('C:\\tmp\\"x"', $embedded['path']);

        $this->assertFalse(str_contains($res['debugRequest'], $apiKey), 'API key leaked in request diagnostics');
        $this->assertFalse(str_contains($res['debugResponse'], $apiKey), 'API key leaked in response diagnostics');
    }
}
