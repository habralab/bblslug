<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use Bblslug\Models\ModelRegistry;
use Bblslug\Models\UsageExtractor;
use PHPUnit\Framework\TestCase;

final class ModelCatalogTest extends TestCase
{
    public function testModelCatalogDocListsExactlyTheRegisteredModelKeys(): void
    {
        $modelCatalogDoc = file_get_contents(__DIR__ . '/../../../docs/models.md');
        self::assertIsString($modelCatalogDoc);
        self::assertIsInt(preg_match('/APIs supported:\n(.*?)\nThis list mirrors/s', $modelCatalogDoc, $match));
        self::assertArrayHasKey(1, $match);

        preg_match_all('/^\s*-\s+`([^`]+)`/m', $match[1], $found);
        $listed = array_values(array_unique($found[1]));
        sort($listed);

        $registered = (new ModelRegistry())->list();
        sort($registered);

        self::assertSame($registered, $listed, 'docs/models.md model list differs from the registry');
    }

    public function testCatalogPayloadIdsMatchTheRegistry(): void
    {
        $catalog = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../resources/model-catalog.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $registry = new ModelRegistry();

        $catalogKeys = [];
        foreach ($catalog['vendors'] as $vendor => $definition) {
            foreach ($definition['models'] as $name => $payloadModel) {
                $key = "{$vendor}:{$name}";
                $catalogKeys[] = $key;
                self::assertNotNull($registry->get($key), "Catalog model {$key} is not registered");
                $config = $registry->get($key);
                self::assertIsArray($config);
                $lifecycle = array_intersect_key($config, array_flip([
                    'kind', 'status', 'access', 'checked_at', 'lifecycle_source',
                    'shutdown_date', 'retirement_not_before', 'redirect_date', 'replacement',
                ]));
                self::assertEquals($lifecycle, $definition['lifecycle_evidence'][$name], $key);

                if ($vendor === 'deepl') {
                    self::assertContains($payloadModel, ['Free tier', 'Pro tier']);
                    continue;
                }

                $config = $registry->get($key);
                self::assertIsArray($config);
                self::assertSame($payloadModel, $config['defaults']['model'] ?? null, $key);
            }
        }

        $registered = $registry->list();
        sort($catalogKeys);
        sort($registered);
        self::assertSame($registered, $catalogKeys);
    }

    public function testRetiredAndCompatibilityRedirectModelsAreNotRegistered(): void
    {
        $registry = new ModelRegistry();
        $retiredKeys = [
            'google:gemini-3.1-flash-lite-preview',
            'openai:gpt-4',
            'openai:gpt-4-turbo',
            'openai:gpt-3.5-turbo-0125',
            'google:gemini-3.5-flash',
            'google:gemini-3.7-flash',
            'xai:grok-3',
            'xai:grok-3-mini',
            'xai:grok-4',
            'yandex:gpt-oss-20b',
            'yandex:gpt-oss-120b',
            'yandex:llama',
            'yandex:llama-lite',
        ];
        foreach ($retiredKeys as $retiredKey) {
            self::assertFalse($registry->has($retiredKey), $retiredKey);
        }

        foreach (['openai:gpt-5-mini', 'openai:gpt-4o', 'yandex:gpt-32k'] as $aliasKey) {
            self::assertFalse($registry->has($aliasKey), $aliasKey);
        }
    }

    public function testRemovedSnapshotsAreUnknownAndNamedModelsRemain(): void
    {
        $registry = new ModelRegistry();
        foreach (
            [
            'anthropic:claude-haiku-4.5-20251001',
            'anthropic:claude-opus-4.5-20251101',
            'anthropic:claude-sonnet-4.5-20250929',
            'openai:gpt-4.1-2025-04-14',
            'openai:gpt-4.1-mini-2025-04-14',
            'openai:gpt-4o-2024-08-06',
            'openai:gpt-4o-2024-11-20',
            'openai:gpt-4o-mini-2024-07-18',
            'openai:gpt-5-2025-08-07',
            'openai:gpt-5-mini-2025-08-07',
            'openai:gpt-5-nano-2025-08-07',
            'openai:gpt-5-pro-2025-10-06',
            'openai:gpt-5-search-api-2025-10-14',
            'openai:gpt-5.1-2025-11-13',
            'openai:gpt-5.2-2025-12-11',
            'openai:gpt-5.2-pro-2025-12-11',
            'openai:gpt-5.4-2026-03-05',
            'openai:gpt-5.4-mini-2026-03-17',
            'openai:gpt-5.4-nano-2026-03-17',
            'openai:gpt-5.4-pro-2026-03-05',
            'openai:gpt-5.5-2026-04-23',
            'openai:gpt-5.5-pro-2026-04-23',
            'openai:o3-2025-04-16',
            'openai:o3-pro-2025-06-10',
            'xai:grok-4.20-0309-non-reasoning',
            'xai:grok-4.20-0309-reasoning',
            'xai:grok-4.20-multi-agent-0309',
            ] as $key
        ) {
            self::assertFalse($registry->has($key), $key);
            self::assertNull($registry->get($key), $key);
        }
        foreach (
            ['openai:gpt-6.1-terra', 'anthropic:claude-opus-4.6', 'xai:grok-4.7',
            'anthropic:claude-fable-5.2', 'anthropic:claude-mythos-5.2', 'google:gemini-4-carbon'] as $key
        ) {
            self::assertTrue($registry->has($key), $key);
        }
    }

    public function testAliasesAndUnverifiedCanonicalIdsAreNotRegistered(): void
    {
        $registry = new ModelRegistry();
        foreach (
            [
            'openai:gpt-5', 'openai:gpt-5-mini', 'openai:gpt-5-nano', 'openai:gpt-5-pro',
            'openai:o3', 'openai:o3-pro', 'openai:chat-latest', 'openai:gpt-5.6-cyber',
            'openai:gpt-rosalind-discovery', 'openai:gpt-rosalind-research', 'openai:gpt-6-rosalind',
            'google:gemini-flash-latest', 'google:gemini-pro-latest', 'google:gemini-flash-lite-latest',
            'anthropic:claude-haiku-4.5', 'anthropic:claude-opus-4.5', 'anthropic:claude-sonnet-4.5',
            'yandex:gpt-lite', 'yandex:gpt-pro', 'yandex:yandexgpt-latest', 'yandex:yandexgpt-rc',
            'yandex:qwen3-235b-a22b-fp8',
            ] as $key
        ) {
            self::assertFalse($registry->has($key), $key);
        }
    }

    public function testLifecycleDateMeaningsRemainDistinct(): void
    {
        $registry = new ModelRegistry();
        $claude = $registry->get('anthropic:claude-fable-5.2');
        self::assertIsArray($claude);
        self::assertSame('2027-11-09', $claude['retirement_not_before']);
        self::assertArrayNotHasKey('shutdown_date', $claude);
        $google = $registry->get('google:gemini-3.1-flash-lite');
        self::assertIsArray($google);
        self::assertSame('available', $google['status']);
        self::assertSame('2027-05-07', $google['retirement_not_before']);
        self::assertArrayNotHasKey('shutdown_date', $google);
        $redirect = $registry->get('google:gemini-2.5-pro');
        self::assertIsArray($redirect);
        self::assertSame('2026-12-01', $redirect['redirect_date']);
        self::assertSame('google:gemini-3.8-flash', $redirect['replacement']);
        self::assertArrayNotHasKey('shutdown_date', $redirect);
        foreach (['yandex:yandexgpt-5-lite', 'yandex:deepseek-v4-flash'] as $key) {
            $config = $registry->get($key);
            self::assertIsArray($config);
            self::assertSame('2026-11-15', $config['shutdown_date']);
            self::assertFalse(ModelRegistry::isDefaultListing($config));
        }
    }

    public function testEveryRegisteredModelBuildsAValidLocalRequest(): void
    {
        $registry = new ModelRegistry();
        foreach ($registry->list() as $key) {
            [$vendor] = explode(':', $key, 2);
            $config = $registry->get($key);
            self::assertIsArray($config);

            $request = $registry->getDriver($key)->buildRequest($config, 'Hello', [
                'format' => 'text',
                'folder_id' => 'fake-folder',
            ]);
            self::assertMatchesRegularExpression('~^https://~', $request['url'], $key);

            if ($vendor === 'deepl') {
                parse_str($request['body'], $form);
                self::assertSame('Hello', $form['text'] ?? null, $key);
                continue;
            }

            $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body, $key);
            $model = $config['defaults']['model'] ?? null;
            self::assertIsString($model, $key);

            if ($vendor === 'google') {
                self::assertStringContainsString('/models/' . $model . ':', $request['url'], $key);
            } elseif ($vendor === 'yandex') {
                $expected = str_starts_with($model, 'gpt://')
                    ? $model
                    : 'gpt://fake-folder/' . $model;
                self::assertSame($expected, $body['model'] ?? $body['modelUri'] ?? null, $key);
            } else {
                self::assertSame($model, $body['model'] ?? null, $key);
            }
        }
    }

    public function testReasoningDefaultsDoNotInheritUnsupportedTemperature(): void
    {
        $registry = new ModelRegistry();
        foreach (['gpt-5.6-sol', 'gpt-6-cyber', 'gpt-6-luna', 'gpt-5.5-cyber-preview', 'gpt-5.3-codex'] as $model) {
            $key = 'openai:' . $model;
            $config = $registry->get($key);
            self::assertIsArray($config);
            $request = $registry->getDriver($key)->buildRequest($config, 'Hello', ['format' => 'text']);
            $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('temperature', $payload, $key);
            if (in_array($model, ['gpt-6-cyber', 'gpt-5.5-cyber-preview', 'gpt-5.3-codex'], true)) {
                self::assertSame('https://api.openai.com/v1/responses', $request['url']);
                self::assertArrayHasKey('input', $payload);
            }
        }
    }

    public function testAlternateApiUsageFieldsAreCounted(): void
    {
        $registry = new ModelRegistry();
        foreach (['openai:gpt-5.3-codex'] as $key) {
            $config = $registry->get($key);
            self::assertIsArray($config);
            $usage = UsageExtractor::extract($config, [
                'total_tokens' => 15, 'input_tokens' => 10, 'output_tokens' => 5,
            ]);
            self::assertSame(15, $usage['tokens']['total']);
            self::assertSame(10, $usage['tokens']['breakdown']['prompt']);
            self::assertSame(5, $usage['tokens']['breakdown']['completion']);
        }
        // Keep coverage for the alternate xAI Responses usage format with explicit configuration.
        $config = ['usage' => ['tokens' => [
            'total' => 'total_tokens',
            'breakdown' => ['prompt' => 'input_tokens', 'completion' => 'output_tokens'],
        ]]];
        $usage = UsageExtractor::extract($config, [
            'total_tokens' => 15, 'input_tokens' => 10, 'output_tokens' => 5,
        ]);
        self::assertSame(15, $usage['tokens']['total']);
        self::assertSame(10, $usage['tokens']['breakdown']['prompt']);
        self::assertSame(5, $usage['tokens']['breakdown']['completion']);

        $config = $registry->get('yandex:aliceai-llm-flash');
        self::assertIsArray($config);
        $usage = UsageExtractor::extract($config, [
            'total_tokens' => 15, 'prompt_tokens' => 10, 'completion_tokens' => 5,
        ]);
        self::assertSame(10, $usage['tokens']['breakdown']['prompt']);
        self::assertSame(5, $usage['tokens']['breakdown']['completion']);
    }

    public function testEveryModelHasSeparateLifecycleAndAccessMetadata(): void
    {
        $registry = new ModelRegistry();
        foreach ($registry->getAll() as $key => $config) {
            self::assertContains($config['status'] ?? null, ['available', 'deprecated'], $key);
            self::assertContains($config['kind'] ?? null, ['model', 'preview', 'tier'], $key);
            self::assertContains($config['access'] ?? null, ['standard', 'restricted', 'existing_customers'], $key);
            self::assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}$/', $config['checked_at'] ?? '', $key);
            self::assertStringStartsWith('https://', $config['lifecycle_source'] ?? '', $key);
            if (isset($config['shutdown_date'])) {
                self::assertSame('deprecated', $config['status'], $key);
                self::assertGreaterThan($config['checked_at'], $config['shutdown_date'], $key);
            }
            foreach (['shutdown_date', 'retirement_not_before', 'redirect_date'] as $field) {
                if (isset($config[$field])) {
                    self::assertMatchesRegularExpression('/^\\d{4}-\\d{2}-\\d{2}$/', $config[$field], $key);
                }
            }
            if (isset($config['redirect_date'])) {
                self::assertSame('deprecated', $config['status'], $key);
                self::assertArrayHasKey('replacement', $config, $key);
            }
            if (isset($config['replacement'])) {
                self::assertTrue($registry->has($config['replacement']), $key);
            }
        }
    }

    public function testPhpModelListIsSortedAndContainsNamedModels(): void
    {
        $groups = \Bblslug\Bblslug::listModels();
        self::assertSame(['anthropic', 'deepl', 'google', 'openai', 'xai', 'yandex'], array_keys($groups));
        self::assertArrayHasKey('openai:gpt-6.1-terra', $groups['openai']);
        foreach ($groups as $models) {
            $keys = array_keys($models);
            $sorted = $keys;
            natsort($sorted);
            self::assertSame(array_values($sorted), $keys);
        }
    }
    public function testCurrentGrokUsesResponsesWithExplicitReasoningSettings(): void
    {
        $registry = new ModelRegistry();
        $config = $registry->get('xai:grok-4.7');
        self::assertIsArray($config);
        $request = $registry->getDriver('xai:grok-4.7')->buildRequest($config, 'Hello', ['format' => 'text']);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://api.x.ai/v1/responses', $request['url']);
        self::assertSame('low', $body['reasoning']['effort']);
        self::assertArrayHasKey('max_output_tokens', $body);
        self::assertArrayNotHasKey('max_tokens', $body);
    }
}
