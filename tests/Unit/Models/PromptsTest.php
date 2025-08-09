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
    public function testRenderThrowsExceptionForUndefinedTemplate(): void
    {
        // Create YAML with a single known template
        $yaml = <<<YAML
bot:
  html: '<p>{message}</p>'
YAML;
        file_put_contents($this->yamlPath, $yaml);

        Prompts::load($this->yamlPath);

        // Trying to render missing kind.format should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Prompt 'unknown.json' not found");
        Prompts::render(
            kind: 'unknown',
            format: 'json',
            vars: []
        );
    }
}
