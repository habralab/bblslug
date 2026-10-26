<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Models\Prompts;

/** Bind saved translations to semantic inputs, loaded templates and library code. */
final class CheckpointIdentity
{
    /** @param array<string,mixed> $settings */
    public static function create(array $settings, string $promptKey): string
    {
        $templates = [];
        $formats = Prompts::list()[$promptKey]['formats'] ?? [];
        foreach (['json', 'structured_json'] as $format) {
            if (in_array($format, $formats, true)) {
                $templates[$format] = Prompts::render($promptKey, $format, []);
            }
        }
        $root = dirname(__DIR__);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        asort($files, SORT_STRING);
        $code = hash_init('sha256');
        foreach ($files as $absolute => $relative) {
            hash_update($code, $relative . "\0");
            if (!hash_update_file($code, $absolute)) {
                throw new \RuntimeException('Could not fingerprint library code for checkpoint');
            }
        }
        try {
            return hash('sha256', json_encode(self::canonical([
                'settings' => $settings, 'templates' => $templates, 'code' => hash_final($code),
            ]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Checkpoint settings cannot be serialized', 0, $e);
        }
    }

    private static function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            return array_map(self::canonical(...), $value);
        }
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('Checkpoint settings require serializable scalar or array values');
        }
        return $value;
    }
}
