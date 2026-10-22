<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class ModelsYamlTest extends TestCase
{
    /** @test */
    public function modelsYamlIsValidAndNonEmpty(): void
    {
        $path = __DIR__ . '/../../../resources/models.yaml';

        $this->assertFileExists($path, 'models.yaml must exist');
        $this->assertIsReadable($path, 'models.yaml must be readable');

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            $this->fail('YAML parse error in models.yaml: ' . $e->getMessage());
            return;
        }

        $this->assertIsArray($data, 'Parsed models.yaml must return an array');
        $this->assertNotEmpty($data, 'models.yaml must define at least one model');
    }
}
