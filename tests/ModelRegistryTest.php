<?php

namespace Bblslug\Tests;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\ModelRegistry;
use Bblslug\Models\DeepLDriver;
use Bblslug\Models\OpenAiDriver;

class ModelRegistryTest extends TestCase
{
    /** @var ModelRegistry */
    private $registry;

    protected function setUp(): void
    {
        $this->registry = new ModelRegistry();
    }

    /** @test */
    public function it_knows_existing_and_missing_models(): void
    {
        $this->assertTrue($this->registry->has('deepl:free'));
        $this->assertTrue($this->registry->has('openai:gpt-4o'));
        $this->assertFalse($this->registry->has('foo:bar'));
    }

    /** @test */
    public function it_returns_correct_endpoint(): void
    {
        $endpoint = $this->registry->getEndpoint('deepl:free');
        $this->assertStringContainsString('api-free.deepl.com', $endpoint);
        $this->assertNull($this->registry->getEndpoint('foo:bar'));
    }

    public function it_lists_all_model_keys(): void
    {
        $keys = $this->registry->list();
        $this->assertContains('deepl:free', $keys);
        $this->assertContains('openai:gpt-4o', $keys);
    }

    /** @test */
    public function it_returns_auth_env_and_help_url(): void
    {
        $env = $this->registry->getAuthEnv('deepl:free');
        $this->assertSame('DEEPL_FREE_API_KEY', $env);

        $help = $this->registry->getHelpUrl('deepl:free');
        $this->assertStringContainsString('deepl.com/account/summary', $help);
    }

    /** @test */
    public function it_returns_notes_and_char_limits(): void
    {
        $limit = $this->registry->getCharLimit('deepl:free');
        $this->assertIsInt($limit);
        $this->assertGreaterThan(0, $limit);

        $notes = $this->registry->getNotes('openai:gpt-4o');
        $this->assertStringContainsString('adaptive translation', $notes);
    }

    /** @test */
    public function it_instantiates_correct_driver(): void
    {
        $driver1 = $this->registry->getDriver('deepl:free');
        $this->assertInstanceOf(DeepLDriver::class, $driver1);

        $driver2 = $this->registry->getDriver('openai:gpt-4o');
        $this->assertInstanceOf(OpenAiDriver::class, $driver2);
    }

    /** @test */
    public function it_throws_on_unknown_vendor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getDriver('foo:bar');
    }

    /** @test */
    public function it_throws_on_unsupported_google_vendor(): void
    {
        // Assuming 'gemini:1.5-pro' uses vendor 'google'
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getDriver('gemini:1.5-pro');
    }
}
