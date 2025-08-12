<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Prompts;
use ReflectionClass;

class PromptsTest extends TestCase
{
    private string $yamlPath;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset internal cache
        $ref = new ReflectionClass(Prompts::class);
        $prop = $ref->getProperty('templates');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Prepare a temporary YAML file for testing
        $this->yamlPath = sys_get_temp_dir() . '/prompts_test.yaml';
        if (file_exists($this->yamlPath)) {
            unlink($this->yamlPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp file
        if (file_exists($this->yamlPath)) {
            unlink($this->yamlPath);
        }
        // Reset internal cache so other tests don't see our overridden prompts
        $ref = new ReflectionClass(Prompts::class);
        $prop = $ref->getProperty('templates');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        parent::tearDown();
    }

    /** @test */
    public function testLoadThrowsExceptionForMissingFile(): void
    {
        // Should throw when file is not readable
        $this->expectException(\RuntimeException::class);
        Prompts::load('/path/does/not/exist.yaml');
    }

    /** @test */
    public function testRenderReplacesPlaceholders(): void
    {
        // Create a simple YAML with one template
        $yaml = <<<YAML
translator:
  text: 'Translate from {source} to {target}: {text}'
YAML;
        file_put_contents($this->yamlPath, $yaml);

        // Load our test YAML
        Prompts::load($this->yamlPath);

        // Render the template with variables
        $output = Prompts::render(
            kind: 'translator',
            format: 'text',
            vars: ['source' => 'en', 'target' => 'fr', 'text' => 'Hello']
        );

        $this->assertSame(
            'Translate from en to fr: Hello',
            $output
        );
    }

    /** @test */
    public function testRenderThrowsExceptionForUndefinedGroup(): void
    {
        // Create YAML with a single known template; group "unknown" is not present
        $yaml = <<<YAML
bot:
  html: '<p>{message}</p>'
YAML;
        file_put_contents($this->yamlPath, $yaml);

        Prompts::load($this->yamlPath);

        // Missing group should fail before format check
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Prompt group 'unknown' not found");
        Prompts::render(
            kind: 'unknown',
            format: 'json',
            vars: []
        );
    }

    /** @test */
    public function testRenderThrowsExceptionForUndefinedFormat(): void
    {
        // Create YAML where group exists, but requested format does not
        $yaml = <<<YAML
translator:
  text: 'Translate {text}'
YAML;
        file_put_contents($this->yamlPath, $yaml);

        Prompts::load($this->yamlPath);

        // Missing format within an existing group should fail with precise message
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Prompt 'translator.json' not found");
        Prompts::render(
            kind: 'translator',
            format: 'json',
            vars: []
        );
    }
}
