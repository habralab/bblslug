<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\YandexDriver;
use PHPUnit\Framework\TestCase;

final class YandexDriverBuildRequestTest extends TestCase
{
    /** @test */
    public function nativeFoundationRequestKeepsLegacyModelUri(): void
    {
        $request = (new YandexDriver())->buildRequest([
            'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
            'defaults' => ['model' => 'yandexgpt-5.1'],
            'requirements' => ['headers' => ['Content-Type: application/json']],
        ], 'Hello', ['folder_id' => 'b1folder']);

        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('gpt://b1folder/yandexgpt-5.1', $payload['modelUri']);
        self::assertArrayNotHasKey('choices', $payload);
    }

    /** @test */
    public function openAiCompatibleRequestUsesExplicitModelUriAndProjectHeader(): void
    {
        $request = (new YandexDriver())->buildRequest([
            'api_type' => 'chat_completions',
            'endpoint' => 'https://ai.api.cloud.yandex.net/v1/chat/completions',
            'defaults' => ['model' => 'gpt://b1folder/yandexgpt-5.1'],
            'requirements' => ['headers' => ['Content-Type: application/json']],
        ], 'Hello', [
            'folder_id' => 'b1folder',
            'maxTokens' => 128,
        ]);

        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('gpt://b1folder/yandexgpt-5.1', $payload['model']);
        self::assertSame(128, $payload['max_tokens']);
        self::assertSame('x-project: b1folder', $request['headers'][1]);
        self::assertSame('user', $payload['messages'][1]['role']);
    }

    /** @test */
    public function openAiCompatibleResponseIsParsed(): void
    {
        $body = json_encode([
            'choices' => [[
                'message' => ['content' => "‹‹TRANSLATION››\nПривет\n‹‹END››"],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $result = (new YandexDriver())->parseResponse([], (string)$body);
        self::assertSame('Привет', $result['text']);
    }
}
