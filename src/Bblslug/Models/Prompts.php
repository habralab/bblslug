<?php

declare(strict_types=1);

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
    /** @var array<string, array<string,mixed>>|null */
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
        if (!is_array($data) || $data === []) {
            throw new \RuntimeException("Prompts YAML is empty or invalid at: {$path}");
        }

        // Normalize strictly to: array<string, array<string,mixed>>
        $clean = [];
        foreach ($data as $k => $v) {
            if (!is_string($k) || !is_array($v)) {
                continue;
            }
            $clean[$k] = $v;
        }
        if ($clean === []) {
            throw new \RuntimeException("Prompts YAML has no valid template groups at: {$path}");
        }

        if (\getenv('BBLSLUG_DEBUG_PROMPTS')) {
            \error_log('[bblslug:prompts] path=' . $path
                . '; keys=' . \implode(', ', \array_keys($clean)));
        }
        /** @var array<string, array<string,mixed>> $typed */ $typed = $clean;
        self::$templates = $typed;
    }

    /**
     * Render a specific prompt template with variables.
     *
     * @param string    $kind   Template category, e.g. 'translator'
     * @param string    $format Template format, e.g. 'text' or 'html'
     * @param array<string, string|int|float|bool|\Stringable> $vars Variables for replacement
     * @return string  The rendered prompt text
     * @throws \InvalidArgumentException If the requested template is not defined
     */
    public static function render(string $kind, string $format, array $vars): string
    {
        if (self::$templates === null) {
            self::load();
        }

        // After load() ensure non-null for static analysis and use a local var.
        if (self::$templates === null) {
            throw new \LogicException('Prompts not loaded');
        }
        /** @var array<string, array<string,mixed>> $templates */
        $templates = self::$templates;

        if (!array_key_exists($kind, $templates)) {
            throw new \InvalidArgumentException("Prompt group '{$kind}' not found");
        }
        /** @var array<string,mixed> $group */
        $group = $templates[$kind];
        if (!array_key_exists($format, $group) || !is_string($group[$format])) {
            throw new \InvalidArgumentException("Prompt '{$kind}.{$format}' not found");
        }

        /** @var string $tpl */
        $tpl = $group[$format];

        // Build replacements map "{key}" => value
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
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
        /** @var array<string, array<string,mixed>> $templates */
        $templates = self::$templates;
        foreach ($templates as $key => $cfg) {
            // collect formats (all keys except “notes”)
            $formats = [];
            foreach ($cfg as $fmt => $_) {
                if ($fmt === 'notes') {
                    continue;
                }
                $formats[] = $fmt; // $fmt is string by array shape
            }
            $notes = null;
            if (array_key_exists('notes', $cfg)) {
                $n = $cfg['notes'];
                if (is_scalar($n) || (is_object($n) && method_exists($n, '__toString'))) {
                    $notes = (string) $n;
                }
            }
            $out[$key] = [
                'formats' => $formats,
                'notes'   => $notes,
            ];
        }

        return $out;
    }
}
