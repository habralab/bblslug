<?php

namespace Bblslug\Models;

use Symfony\Component\Yaml\Yaml;

/**
 * Prompt templates loader and renderer.
 *
 * Loads prompt definitions from a YAML file and renders them
 * by substituting variables into placeholders.
 */
class Prompts
{
    /** @var array<string,mixed>|null */
    private static ?array $templates = null;

    public static function load(?string $path = null): void
    {
        $path ??= __DIR__ . '/../../../resources/prompts.yaml';
        // Ensure the YAML file is readable
        if (!is_readable($path)) {
            throw new \RuntimeException("Prompts file not found: {$path}");
        }
        self::$templates = Yaml::parseFile($path);
    }

    /**
     * Render a specific prompt template with variables.
     *
     * @param string    $kind   Template category, e.g. 'translator'
     * @param string    $format Template format, e.g. 'text' or 'html'
     * @param array     $vars   Variables for replacement: source, target, start, end, context, etc.
     * @return string  The rendered prompt text
     * @throws \InvalidArgumentException If the requested template is not defined
     */
    public static function render(string $kind, string $format, array $vars): string
    {
        if (self::$templates === null) {
            self::load();
        }

        if (!isset(self::$templates[$kind][$format])) {
            throw new \InvalidArgumentException("Prompt '{$kind}.{$format}' not found");
        }

        $tpl = self::$templates[$kind][$format];
        // Build replacements map "{key}" => value
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = (string)$value;
        }
        // Replace placeholders in template
        return strtr($tpl, $replacements);
    }
}
