<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use Bblslug\Models\Drivers\GoogleDriver;
use PHPUnit\Framework\TestCase;

final class GoogleDriverBuildRequestTest extends TestCase
{
    public function testUsesThinkingLevelWhenConfigured(): void
    {
        $request = (new GoogleDriver())->buildRequest([
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-4-argon:generateContent',
            'defaults' => [
                'model' => 'gemini-4-argon',
                'thinkingLevel' => 'high',
                'thinkingBudget' => 2048,
            ],
        ], 'Hello', []);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('high', $body['generationConfig']['thinkingConfig']['thinkingLevel']);
        $this->assertArrayNotHasKey('thinkingBudget', $body['generationConfig']['thinkingConfig']);
    }

    public function testUsesThinkingBudgetForLegacyThinkingModels(): void
    {
        $request = (new GoogleDriver())->buildRequest([
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent',
            'defaults' => ['model' => 'gemini-2.5-pro', 'thinkingBudget' => 2048],
        ], 'Hello', []);
        $body = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2048, $body['generationConfig']['thinkingConfig']['thinkingBudget']);
        $this->assertArrayNotHasKey('thinkingLevel', $body['generationConfig']['thinkingConfig']);
    }
}
