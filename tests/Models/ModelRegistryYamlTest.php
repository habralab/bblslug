<?php

namespace Bblslug\Tests\Models;

use Bblslug\Models\ModelRegistry;
use PHPUnit\Framework\TestCase;

class ModelRegistryYamlTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/bblslug_models_test.yaml';
        file_put_contents($this->tmpFile, <<<YAML
---
foo:
  vendor: testvendor
  endpoint: "https://example.test"
YAML
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    /** @test */
    public function constructorThrowsWhenYamlNotReadable(): void
    {
        $this->expectException(\RuntimeException::class);
        new ModelRegistry('/path/does/not/exist.yaml');
    }

    /** @test */
    public function itLoadsModelsFromCustomYaml(): void
    {
        $registry = new ModelRegistry($this->tmpFile);
        $all = $registry->getAll();

        $this->assertArrayHasKey('foo', $all);
        $this->assertSame('testvendor', $all['foo']['vendor']);
        $this->assertSame('https://example.test', $all['foo']['endpoint']);
    }
}
