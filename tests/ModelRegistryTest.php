<?php

namespace Bblslug\Tests;

use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\Drivers\DeepLDriver;
use Bblslug\Models\Drivers\GoogleDriver;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

class ModelRegistryTest extends TestCase
{
    /** @var ModelRegistry */
    private $registry;

    /**
     * Flat list of all expected model keys for reuse across tests.
     */
    private const EXPECTED_MODELS = [
        'anthropic:claude-haiku-3.5',
        'anthropic:claude-opus-4',
        'anthropic:claude-sonnet-4',
        'deepl:free',
        'deepl:pro',
        'google:gemini-2.0-flash',
        'google:gemini-2.5-flash',
        'google:gemini-2.5-flash-lite',
        'google:gemini-2.5-pro',
        'openai:gpt-4',
        'openai:gpt-4-turbo',
        'openai:gpt-4o',
        'openai:gpt-4o-mini',
    ];

    protected function setUp(): void
    {
        $this->registry = new ModelRegistry();
    }

    /** @test */
    public function getAllReturnsFullModelsArray(): void
    {
        $all = $this->registry->getAll();
        $this->assertIsArray($all, 'getAll() must return an array');
        // все наши ожидаемые ключи должны присутствовать
        foreach (self::EXPECTED_MODELS as $modelKey) {
            $this->assertArrayHasKey(
                $modelKey,
                $all,
                "getAll() should contain '{$modelKey}'"
            );
        }
    }

    /** @test */
    public function itKnowsExistingAndMissingModels(): void
    {
        $all = $this->registry->list();

        foreach (self::EXPECTED_MODELS as $modelKey) {
            $this->assertContains(
                $modelKey,
                $all,
                "Expected model registry to contain '{$modelKey}'"
            );
        }

        $this->assertFalse(
            $this->registry->has('foo:bar'),
            "Registry should not contain 'foo:bar'"
        );
    }

    /** @test */
    public function itReturnsCorrectEndpoint(): void
    {
        // Every known model should return a non-null HTTP(S) endpoint URL
        foreach (self::EXPECTED_MODELS as $modelKey) {
            $endpoint = $this->registry->getEndpoint($modelKey);
            $this->assertNotNull(
                $endpoint,
                "Model '{$modelKey}' should have a configured endpoint"
            );

            $this->assertMatchesRegularExpression(
                '~^https?://~',
                $endpoint,
                "Endpoint for '{$modelKey}' must start with http:// or https://"
            );
        }

        // Unknown model yields null
        $this->assertNull(
            $this->registry->getEndpoint('foo:bar'),
            "Unknown model should yield null endpoint"
        );
    }

    /** @test */
    public function itListsAllModelKeys(): void
    {
        $keys = $this->registry->list();
        foreach (self::EXPECTED_MODELS as $modelKey) {
            $this->assertContains(
                $modelKey,
                $keys,
                "Model list should contain '{$modelKey}'"
            );
        }
    }

    /** @test */
    public function itReturnsExpectedAuthEnvForEachModel(): void
    {
        $expected = [
            'anthropic:claude-haiku-3.5'   => 'ANTHROPIC_API_KEY',
            'anthropic:claude-opus-4'      => 'ANTHROPIC_API_KEY',
            'anthropic:claude-sonnet-4'    => 'ANTHROPIC_API_KEY',
            'deepl:free'                   => 'DEEPL_FREE_API_KEY',
            'deepl:pro'                    => 'DEEPL_PRO_API_KEY',
            'google:gemini-2.0-flash'      => 'GOOGLE_API_KEY',
            'google:gemini-2.5-flash'      => 'GOOGLE_API_KEY',
            'google:gemini-2.5-flash-lite' => 'GOOGLE_API_KEY',
            'google:gemini-2.5-pro'        => 'GOOGLE_API_KEY',
            'openai:gpt-4'                 => 'OPENAI_API_KEY',
            'openai:gpt-4-turbo'           => 'OPENAI_API_KEY',
            'openai:gpt-4o'                => 'OPENAI_API_KEY',
            'openai:gpt-4o-mini'           => 'OPENAI_API_KEY',
        ];

        foreach ($expected as $modelKey => $envName) {
            $this->assertSame(
                $envName,
                $this->registry->getAuthEnv($modelKey),
                "Auth env for '{$modelKey}' should be '{$envName}'"
            );
        }
    }

    /** @test */
    public function itReturnsExpectedHelpUrlForEachModel(): void
    {
        $expected = [
            'anthropic:claude-haiku-3.5'   => 'console.anthropic.com',
            'anthropic:claude-opus-4'      => 'console.anthropic.com',
            'anthropic:claude-sonnet-4'    => 'console.anthropic.com',
            'deepl:free'                   => 'deepl.com/account/summary',
            'deepl:pro'                    => 'deepl.com/account/summary',
            'google:gemini-2.0-flash'      => 'makersuite.google.com/app/apikey',
            'google:gemini-2.5-flash'      => 'makersuite.google.com/app/apikey',
            'google:gemini-2.5-flash-lite' => 'makersuite.google.com/app/apikey',
            'google:gemini-2.5-pro'        => 'makersuite.google.com/app/apikey',
            'openai:gpt-4'                 => 'platform.openai.com/account/api-keys',
            'openai:gpt-4-turbo'           => 'platform.openai.com/account/api-keys',
            'openai:gpt-4o'                => 'platform.openai.com/account/api-keys',
            'openai:gpt-4o-mini'           => 'platform.openai.com/account/api-keys',
        ];

        foreach ($expected as $modelKey => $urlSubstring) {
            $this->assertStringContainsString(
                $urlSubstring,
                $this->registry->getHelpUrl($modelKey),
                "Help URL for '{$modelKey}' should contain '{$urlSubstring}'"
            );
        }
    }

    /** @test */
    public function itReturnsValidCharLimitsForAllModels(): void
    {
        foreach (self::EXPECTED_MODELS as $modelKey) {
            $limit = $this->registry->getCharLimit($modelKey);
            $this->assertIsInt(
                $limit,
                "Char limit for '{$modelKey}' should be an integer"
            );
            $this->assertGreaterThan(
                0,
                $limit,
                "Char limit for '{$modelKey}' should be greater than zero"
            );
        }
    }

    /** @test */
    public function itReturnsNonEmptyNotesForAllModels(): void
    {
        foreach (self::EXPECTED_MODELS as $modelKey) {
            $notes = $this->registry->getNotes($modelKey);
            $this->assertIsString(
                $notes,
                "Notes for '{$modelKey}' should be a string"
            );
            $this->assertNotEmpty(
                $notes,
                "Notes for '{$modelKey}' should not be empty"
            );
        }
    }

    /** @test */
    public function itInstantiatesCorrectDriverForEachModel(): void
    {
        $map = [
            'anthropic:claude-haiku-3.5'   => AnthropicDriver::class,
            'anthropic:claude-opus-4'      => AnthropicDriver::class,
            'anthropic:claude-sonnet-4'    => AnthropicDriver::class,
            'deepl:free'                   => DeepLDriver::class,
            'deepl:pro'                    => DeepLDriver::class,
            'google:gemini-2.0-flash'      => GoogleDriver::class,
            'google:gemini-2.5-flash'      => GoogleDriver::class,
            'google:gemini-2.5-flash-lite' => GoogleDriver::class,
            'google:gemini-2.5-pro'        => GoogleDriver::class,
            'openai:gpt-4'                 => OpenAiDriver::class,
            'openai:gpt-4-turbo'           => OpenAiDriver::class,
            'openai:gpt-4o'                => OpenAiDriver::class,
            'openai:gpt-4o-mini'           => OpenAiDriver::class,
        ];

        foreach ($map as $modelKey => $driverClass) {
            $driver = $this->registry->getDriver($modelKey);
            $this->assertInstanceOf(
                $driverClass,
                $driver,
                "Driver for '{$modelKey}' should be instance of {$driverClass}"
            );
        }
    }

    /** @test */
    public function itThrowsOnUnknownVendor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getDriver('foo:bar');
    }

    /** @test */
    public function itThrowsOnUnsupportedGoogleModel(): void
    {
        // Assuming 'gemini-1.5-pro' uses vendor 'google'
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getDriver('google:gemini-1.5-pro');
    }
}
