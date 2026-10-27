<?php

declare(strict_types=1);

namespace Bblslug\Console;

/** Validate output targets and stage ordinary local files for replacement. */
final class OutputFileWriter
{
    /**
     * Reject obvious target failures without opening or changing the path.
     * This advisory check does not replace the checks made during write().
     *
     * @throws \RuntimeException If the target is already known to be unusable.
     */
    public static function validateTarget(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid output path');
        }
        // Other stream wrappers retain their existing write-time behavior.
        if (preg_match('~\A([a-z][a-z0-9+.-]*)://~i', $path, $scheme) === 1 && strtolower($scheme[1]) !== 'file') {
            return;
        }
        if (str_ends_with($path, '/') || (DIRECTORY_SEPARATOR === '\\' && str_ends_with($path, '\\'))) {
            throw new \RuntimeException('Output path ends in a directory separator');
        }
        clearstatcache(true, $path);
        $exists = file_exists($path);
        if (is_dir($path) || ($exists && !is_writable($path))) {
            throw new \RuntimeException('Output file is not writable');
        }
        // A dangling symlink may point to a creatable target in another directory.
        if (!$exists && !is_link($path) && (!is_dir(dirname($path)) || !is_writable(dirname($path)))) {
            throw new \RuntimeException('Output directory is not writable');
        }
    }

    public static function write(string $path, string $contents): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid output path');
        }
        if (preg_match('~\A[a-z][a-z0-9+.-]*://~i', $path) === 1) {
            self::writeDirect($path, $contents);
            return;
        }
        if (str_ends_with($path, '/') || (DIRECTORY_SEPARATOR === '\\' && str_ends_with($path, '\\'))) {
            throw new \RuntimeException('Output path ends in a directory separator');
        }
        clearstatcache(true, $path);
        $target = realpath($path);
        $original = null;
        if ($target !== false) {
            clearstatcache(true, $target);
            if (!is_file($target)) {
                self::writeDirect($path, $contents);
                return;
            }
            $original = @stat($target);
            if ($original === false || !is_writable($target)) {
                throw new \RuntimeException('Output file is not writable');
            }
        } else {
            if (is_link($path)) {
                self::writeDirect($path, $contents);
                return;
            }
            $parent = realpath(dirname($path));
            if ($parent === false || !is_dir($parent)) {
                throw new \RuntimeException('Output directory does not exist');
            }
            $target = $parent . DIRECTORY_SEPARATOR . basename($path);
        }
        $directory = dirname($target);
        if (!is_writable($directory) || ($original !== null && $original['nlink'] > 1)) {
            self::writeDirect($path, $contents);
            return;
        }
        try {
            $temporary = $directory . DIRECTORY_SEPARATOR . '.bblslug-output-' . bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not name output temporary file', 0, $e);
        }
        // Create and open exclusively, so an existing path is never truncated.
        $handle = @fopen($temporary, 'x+b');
        if ($handle === false) {
            throw new \RuntimeException('Could not create output temporary file');
        }
        try {
            if (realpath(dirname($temporary)) !== $directory || !@chmod($temporary, 0600)) {
                throw new \RuntimeException('Could not prepare private output temporary file');
            }
            $staged = fstat($handle);
            if ($staged === false) {
                throw new \RuntimeException('Could not inspect output temporary file');
            }
            if (
                $original !== null
                && ($staged['uid'] !== $original['uid'] || $staged['gid'] !== $original['gid'])
            ) {
                // Preserve ownership when replacing the inode would change it.
                self::writeDirect($path, $contents);
                return;
            }
            $length = strlen($contents);
            $written = 0;
            while ($written < $length) {
                $bytes = @fwrite($handle, substr($contents, $written));
                if ($bytes === false || $bytes === 0) {
                    throw new \RuntimeException('Could not write complete output temporary file');
                }
                $written += $bytes;
            }
            if (!@fflush($handle)) {
                throw new \RuntimeException('Could not flush output temporary file');
            }
            $mode = $original !== null ? ($original['mode'] & 0777) : (0666 & ~umask());
            if (!@chmod($temporary, $mode) || (function_exists('fsync') && !@fsync($handle))) {
                throw new \RuntimeException('Could not finalize output temporary file');
            }
            $closed = @fclose($handle);
            $handle = null;
            if (!$closed || !@rename($temporary, $target)) {
                throw new \RuntimeException('Could not replace output file');
            }
            $temporary = '';
            clearstatcache(true, $target);
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            if ($temporary !== '') {
                @unlink($temporary);
            }
        }
    }

    private static function writeDirect(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) !== strlen($contents)) {
            throw new \RuntimeException('Could not write complete output file');
        }
    }
}
