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

    /**
     * Load prompt definitions from YAML.
     *
     * @param string|null $path
     * @return void
     * @throws \RuntimeException
     */

    public static function load(?string $path = null): void
    {
        if (self::$templates !== null) {
            return;
        }

        $path ??= __DIR__ . '/../../../resources/prompts.yaml';

        if (!is_readable($path)) {
            throw new \RuntimeException("Prompts file not found or not readable: {$path}");
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data) || empty($data)) {
            throw new \RuntimeException("Prompts YAML is empty or invalid at: {$path}");
        }

        if (\getenv('BBLSLUG_DEBUG_PROMPTS')) {
            \error_log('[bblslug:prompts] path=' . $path
                . '; keys=' . \implode(', ', \array_keys($data)));
        }
        self::$templates = $data;
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

        if (!isset(self::$templates[$kind]) || !is_array(self::$templates[$kind])) {
            throw new \InvalidArgumentException("Prompt group '{$kind}' not found");
        }
        if (!isset(self::$templates[$kind][$format]) || !is_string(self::$templates[$kind][$format])) {
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

    /**
     * Return a flat list of all prompts, with supported formats and optional notes.
     *
     * @return array<string, array{formats: string[], notes: ?string}>
     * @throws \RuntimeException
     */
    public static function list(): array
    {
        if (self::$templates === null) {
            self::load();
        }

        $out = [];
        foreach (self::$templates as $key => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }

            // collect formats (all keys except “notes”)
            $formats = [];
            foreach ($cfg as $fmt => $_) {
                if ($fmt === 'notes') {
                    continue;
                }
                $formats[] = $fmt;
            }
            $out[$key] = [
                'formats' => $formats,
                'notes'   => isset($cfg['notes']) ? (string) $cfg['notes'] : null,
            ];
        }

        return $out;
    }
}
