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
            'google:gemini-3.5-flash',
            'google:gemini-3.7-flash',
            'xai:grok-3',
            'xai:grok-3-mini',
            'xai:grok-4',
            'yandex:llama',
            'yandex:llama-lite',
        ];
        foreach ($retiredKeys as $retiredKey) {
            self::assertFalse($registry->has($retiredKey), $retiredKey);
        }

        foreach (['openai:gpt-4', 'openai:gpt-4o', 'yandex:gpt-32k'] as $legacyKey) {
            self::assertTrue($registry->has($legacyKey), $legacyKey);
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
        foreach (['gpt-5.6-sol', 'gpt-5.2-pro', 'o1', 'o3-pro', 'gpt-5.3-codex'] as $model) {
            $key = 'openai:' . $model;
            $config = $registry->get($key);
            self::assertIsArray($config);
            $request = $registry->getDriver($key)->buildRequest($config, 'Hello', ['format' => 'text']);
            $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('temperature', $payload, $key);
            if (in_array($model, ['gpt-5.2-pro', 'o3-pro', 'gpt-5.3-codex'], true)) {
                self::assertSame('https://api.openai.com/v1/responses', $request['url']);
                self::assertArrayHasKey('input', $payload);
            }
        }
    }

    public function testAlternateApiUsageFieldsAreCounted(): void
    {
        $registry = new ModelRegistry();
        foreach (['openai:gpt-5.2-pro', 'xai:grok-4.20-multi-agent-0309'] as $key) {
            $config = $registry->get($key);
            self::assertIsArray($config);
            $usage = UsageExtractor::extract($config, [
                'total_tokens' => 15, 'input_tokens' => 10, 'output_tokens' => 5,
            ]);
            self::assertSame(15, $usage['tokens']['total']);
            self::assertSame(10, $usage['tokens']['breakdown']['prompt']);
            self::assertSame(5, $usage['tokens']['breakdown']['completion']);
        }
        $config = $registry->get('yandex:aliceai-llm-flash');
        self::assertIsArray($config);
        $usage = UsageExtractor::extract($config, [
            'total_tokens' => 15, 'prompt_tokens' => 10, 'completion_tokens' => 5,
        ]);
        self::assertSame(10, $usage['tokens']['breakdown']['prompt']);
        self::assertSame(5, $usage['tokens']['breakdown']['completion']);
    }
}
