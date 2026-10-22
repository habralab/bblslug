<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\Drivers\XaiDriver;
use Bblslug\Models\Drivers\YandexDriver;

final class DriverBuildRequestRequiredConfigTest extends TestCase
{
    /** @test */
    public function openaiRequiresModelName(): void
    {
        $driver = new OpenAiDriver();
        $config = [
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'defaults' => [/* no model here */],
            'requirements' => ['headers' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing OpenAI model name');
        $driver->buildRequest($config, 'text', []);
    }

    /** @test */
    public function anthropicRequiresModelName(): void
    {
        $driver = new AnthropicDriver();
        $config = [
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'defaults' => [/* no model here */],
            'requirements' => ['headers' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing Anthropic model name');
        $driver->buildRequest($config, 'text', []);
    }

    /** @test */
    public function xaiRequiresModelName(): void
    {
        $driver = new XaiDriver();
        $config = [
            'endpoint' => 'https://api.x.ai/v1/chat/completions',
            'defaults' => [/* no model here */],
            'requirements' => ['headers' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing xAI model name');
        $driver->buildRequest($config, 'text', []);
    }

    /** @test */
    public function yandexRequiresFolderIdInOptions(): void
    {
        $driver = new YandexDriver();
        $config = [
            'endpoint' => 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion',
            'defaults' => ['model' => 'yandexgpt-lite'],
            'requirements' => ['headers' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing Yandex folder_id in options');
        // no folder_id in options
        $driver->buildRequest($config, 'text', []);
    }
}
