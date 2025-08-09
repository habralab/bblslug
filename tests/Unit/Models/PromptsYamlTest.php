<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

final class PromptsYamlTest extends TestCase
{
    private function loadPrompts(): array
    {
        $path = __DIR__ . '/../../../resources/prompts.yaml';

        $this->assertFileExists($path, 'prompts.yaml must exist');
        $this->assertIsReadable($path, 'prompts.yaml must be readable');

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            $this->fail('YAML parse error in prompts.yaml: ' . $e->getMessage());
            return [];
        }

        $this->assertIsArray($data, 'Parsed prompts.yaml must be an array');
        $this->assertNotEmpty($data, 'prompts.yaml must define at least one template');

        return $data;
    }

    /** @test */
    public function promptsYamlIsValidAndNonEmpty(): void
    {
        $this->loadPrompts(); // assertions inside
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function translatorTemplateHasAllRequiredFormats(): void
    {
        $data = $this->loadPrompts();

        $this->assertArrayHasKey('translator', $data, 'prompts.yaml must define "translator" template');
        $tpl = $data['translator'];

        $this->assertIsArray($tpl, '"translator" must be a mapping');

        // "notes" is optional, but formats are required
        foreach (['text', 'html', 'json'] as $fmt) {
            $this->assertArrayHasKey($fmt, $tpl, sprintf('"translator" must define "%s" format', $fmt));
            $this->assertIsString($tpl[$fmt], sprintf('"translator.%s" must be a string', $fmt));
            $this->assertNotSame('', trim($tpl[$fmt]), sprintf('"translator.%s" must be non-empty', $fmt));
        }
    }

    /** @test */
    public function eachFormatContainsRequiredPlaceholders(): void
    {
        $data = $this->loadPrompts();

        // Required placeholders expected by drivers: {source}, {target}, {start}, {end}, {context}
        $required = ['{source}', '{target}', '{start}', '{end}', '{context}'];

        foreach ($data as $key => $tpl) {
            if (!is_array($tpl)) {
                $this->fail(sprintf('Template "%s" must be a mapping', (string)$key));
            }

            // Check only known format keys if present
            foreach (['text', 'html', 'json'] as $fmt) {
                if (!array_key_exists($fmt, $tpl)) {
                    continue;
                }
                $body = $tpl[$fmt];
                $this->assertIsString($body, sprintf('Template "%s.%s" must be a string', $key, $fmt));
                $this->assertNotSame('', trim($body), sprintf('Template "%s.%s" must be non-empty', $key, $fmt));

                foreach ($required as $ph) {
                    $this->assertStringContainsString(
                        $ph,
                        $body,
                        sprintf('Template "%s.%s" must contain placeholder %s', $key, $fmt, $ph)
                    );
                }
            }
        }
    }
}
