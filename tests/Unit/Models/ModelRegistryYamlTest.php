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

    /** @test */
    public function itFlattensVendorLevelModels(): void
    {
        // create temporary YAML with vendor-level config and two nested models
        $yaml = <<<YAML
---
bar:
  endpoint: "https://api.bar.test"
  format: html
  defaults:
    source_lang: EN
    target_lang: DE
  requirements:
    auth:
      type: header
      key_name: X-API
      env: BAR_API_KEY
      help_url: "https://help.bar.test"
  models:
    m1:
      defaults:
        model: foo-v1
        temperature: 0.5
      notes: "first submodel"
    m2:
      endpoint: "https://override.bar.test"
      defaults:
        model: foo-v2
      notes: "second submodel"
YAML;

        file_put_contents($this->tmpFile, $yaml);
        $registry = new ModelRegistry($this->tmpFile);
        $all = $registry->getAll();

        // expects keys "bar:m1" and "bar:m2"
        $this->assertArrayHasKey('bar:m1', $all);
        $this->assertArrayHasKey('bar:m2', $all);

        // check that settings for "bar:m1" inherited endpoint and format from vendor
        $m1 = $all['bar:m1'];
        $this->assertSame('bar', $m1['vendor']);
        $this->assertSame('https://api.bar.test', $m1['endpoint']);
        $this->assertSame('html', $m1['format']);
        $this->assertSame('foo-v1', $m1['defaults']['model']);
        $this->assertSame(0.5, $m1['defaults']['temperature']);
        $this->assertSame('first submodel', $m1['notes']);

        // for "bar:m2" endpoint must be redefined
        $m2 = $all['bar:m2'];
        $this->assertSame('bar', $m2['vendor']);
        $this->assertSame('https://override.bar.test', $m2['endpoint']);
        $this->assertSame('html', $m2['format']);
        $this->assertSame('foo-v2', $m2['defaults']['model']);
        $this->assertSame('second submodel', $m2['notes']);
    }
}
