<?php

/**
 * @noinspection PhpDocMissingThrowsInspection
 */

declare(strict_types=1);

namespace Bblslug\Models;

use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\Drivers\DeepLDriver;
use Bblslug\Models\Drivers\GoogleDriver;
use Bblslug\Models\Drivers\OpenAiDriver;
use Bblslug\Models\Drivers\YandexDriver;
use Bblslug\Models\Drivers\XaiDriver;
use Bblslug\Models\ModelDriverInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Registry of available translation models.
 *
 * Loads model definitions from a PHP file and provides
 * convenient accessors for endpoints, formats, auth info, etc.
 */
class ModelRegistry
{
    /** @var array<string, array<string,mixed>> */
    protected array $models = [];

    /**
     * Load the models registry from the given path (or default).
     *
     * @param string|null $path Path to a PHP file returning the models array.
     */
    public function __construct(?string $path = null)
    {
        $path ??= __DIR__ . '/../../../resources/models.yaml';
        if (!is_readable($path)) {
            throw new \RuntimeException("Model registry not found: {$path}");
        }

        $raw = Yaml::parseFile($path);
        if (!\is_array($raw)) {
            throw new \RuntimeException("Model registry must be a mapping array in {$path}");
        }

        /** @var array<string, array<string,mixed>> $flat */
        $flat = [];

        foreach ($raw as $key => $cfg) {
            if (!\is_string($key) || !\is_array($cfg)) {
                continue;
            }
            // vendor‐level grouping?
            if (isset($cfg['models']) && \is_array($cfg['models'])) {
                $vendor = $key;
                $vendorDefaults = $cfg;
                unset($vendorDefaults['models']);

                foreach ($cfg['models'] as $modelName => $modelOverrides) {
                    if (!\is_string($modelName) || !\is_array($modelOverrides)) {
                        continue;
                    }

                    // merge vendor-level + per-model (modelOverride wins)
                    /** @var array<string,mixed> $merged */
                    $merged = array_replace_recursive($vendorDefaults, $modelOverrides);

                    // ensure we still know the vendor
                    $merged['vendor'] = $vendor;
                    $flat["{$vendor}:{$modelName}"] = $merged;
                }
            } else {
                // flat (legacy) definition
                /** @var array<string,mixed> $cfg */
                $flat[$key] = $cfg;
            }
        }

        $this->models = $flat;
    }

    /**
     * Get the full configuration for a model.
     *
     * @param  string             $key Model key, e.g. "deepl:pro"
     * @return array<string,mixed>      Model config, or null if not found
     */
    public function get(string $key): ?array
    {
        return $this->models[$key] ?? null;
    }

    /**
     * Check whether a model is registered.
     *
     * @param  string $key Model key
     * @return bool         True if present
     */
    public function has(string $key): bool
    {
        return isset($this->models[$key]);
    }

    /**
     * List all available model keys.
     *
     * @return string[] Array of keys
     */
    public function list(): array
    {
        return array_keys($this->models);
    }

    /**
     * Get the API endpoint URL for a model.
     *
     * @param  string      $key Model key
     * @return string|null      Endpoint URL or null
     */
    public function getEndpoint(string $key): ?string
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $endpoint = $cfg['endpoint'] ?? null;
        if (\is_string($endpoint) && $endpoint !== '') {
            return $endpoint;
        }
        return null;
    }

    /**
     * Get the supported format(s) for a model ("text", "html", etc).
     *
     * @param  string      $key Model key
     * @return string|null      Format string or null
     */
    public function getFormat(string $key): ?string
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $format = $cfg['format'] ?? null;
        if (\is_string($format) && $format !== '') {
            return $format;
        }
        return null;
    }

    /**
     * Get the approximate character limit for a model.
     *
     * @param  string $key Model key
     * @return int|null     Estimated max characters or null
     */
    public function getCharLimit(string $key): ?int
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $limits = $cfg['limits'] ?? null;
        if (!\is_array($limits)) {
            return null;
        }
        $val = $limits['estimated_max_chars'] ?? null;
        if (\is_int($val) || \is_string($val)) {
            return (int)$val;
        }
        return null;
    }

    /**
     * Get the environment variable name for a model's API key.
     *
     * @param  string $key Model key
     * @return string|null Env var name or null
     */
    public function getAuthEnv(string $key): ?string
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $req = $cfg['requirements'] ?? null;
        if (!\is_array($req)) {
            return null;
        }
        $auth = $req['auth'] ?? null;
        if (!\is_array($auth)) {
            return null;
        }
        $env = $auth['env'] ?? null;
        return \is_string($env) && $env !== '' ? $env : null;
    }

    /**
     * Get the map of variable names → env var names for a model.
     *
     * @param string $key Model key
     * @return array<string,string>  e.g. ['some_var'=>'SOME_VAR']
     */
    public function getVariables(string $key): array
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return [];
        }
        $req = $cfg['requirements'] ?? null;
        if (!\is_array($req)) {
            return [];
        }
        $vars = $req['variables'] ?? [];
        $out  = [];
        if (\is_array($vars)) {
            foreach ($vars as $k => $v) {
                if (\is_string($k) && \is_string($v)) {
                    $out[$k] = $v;
                }
            }
        }
        return $out;
    }

    /**
     * Get the documentation URL for obtaining a model's API key.
     *
     * @param  string $key Model key
     * @return string|null Help URL or null
     */
    public function getHelpUrl(string $key): ?string
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $req = $cfg['requirements'] ?? null;
        if (!\is_array($req)) {
            return null;
        }
        $auth = $req['auth'] ?? null;
        if (!\is_array($auth)) {
            return null;
        }
        $url = $auth['help_url'] ?? null;
        return \is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Get any human-readable notes for a model.
     *
     * @param  string $key Model key
     * @return string|null Notes or null
     */
    public function getNotes(string $key): ?string
    {
        $cfg = $this->get($key);
        if ($cfg === null) {
            return null;
        }
        $n = $cfg['notes'] ?? null;
        if (\is_scalar($n) || (\is_object($n) && \method_exists($n, '__toString'))) {
            $s = (string)$n;
            return $s !== '' ? $s : null;
        }
        return null;
    }

    /**
     * Get the raw models array.
     *
     * @return array<string,array<string,mixed>> All models
     */
    public function getAll(): array
    {
        return $this->models;
    }

    /**
     * Instantiate the driver for a given model/vendor.
     *
     * @param  string                 $key Model key
     * @return ModelDriverInterface        Concrete driver
     * @throws \InvalidArgumentException   If no driver is available
     */
    public function getDriver(string $key): ModelDriverInterface
    {
        $model  = $this->get($key);
        if ($model === null) {
            throw new \InvalidArgumentException("Unknown model key '{$key}'.");
        }
        $vendor = \is_string($model['vendor'] ?? null) ? $model['vendor'] : '';

        return match ($vendor) {
            'anthropic' => new AnthropicDriver(),
            'deepl' => new DeepLDriver(),
            'google' => new GoogleDriver(),
            'openai' => new OpenAiDriver(),
            'yandex' => new YandexDriver(),
            'xai' => new XaiDriver(),
            default => throw new \InvalidArgumentException("Unknown vendor '{$vendor}' in registry."),
        };
    }
}
