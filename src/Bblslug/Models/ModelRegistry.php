<?php

namespace Bblslug\Models;

use Bblslug\Models\Drivers\AnthropicDriver;
use Bblslug\Models\Drivers\DeepLDriver;
use Bblslug\Models\Drivers\GoogleDriver;
use Bblslug\Models\Drivers\OpenAiDriver;
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
    protected array $models;

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

        $this->models = Yaml::parseFile($path);
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
        return $this->models[$key]['endpoint'] ?? null;
    }

    /**
     * Get the supported format(s) for a model ("text", "html", etc).
     *
     * @param  string      $key Model key
     * @return string|null      Format string or null
     */
    public function getFormat(string $key): ?string
    {
        return $this->models[$key]['format'] ?? null;
    }

    /**
     * Get the approximate character limit for a model.
     *
     * @param  string $key Model key
     * @return int|null     Estimated max characters or null
     */
    public function getCharLimit(string $key): ?int
    {
        return $this->models[$key]['limits']['estimated_max_chars'] ?? null;
    }

    /**
     * Get the environment variable name for a model's API key.
     *
     * @param  string $key Model key
     * @return string|null Env var name or null
     */
    public function getAuthEnv(string $key): ?string
    {
        return $this->models[$key]['requirements']['auth']['env'] ?? null;
    }

    /**
     * Get the documentation URL for obtaining a model's API key.
     *
     * @param  string $key Model key
     * @return string|null Help URL or null
     */
    public function getHelpUrl(string $key): ?string
    {
        return $this->models[$key]['requirements']['auth']['help_url'] ?? null;
    }

    /**
     * Get any human-readable notes for a model.
     *
     * @param  string $key Model key
     * @return string|null Notes or null
     */
    public function getNotes(string $key): ?string
    {
        return $this->models[$key]['notes'] ?? null;
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
        $vendor = $model['vendor'] ?? '';

        return match ($vendor) {
            'anthropic' => new AnthropicDriver(),
            'deepl' => new DeepLDriver(),
            'google' => new GoogleDriver(),
            'openai' => new OpenAiDriver(),
            default => throw new \InvalidArgumentException("Unknown vendor '{$vendor}' in registry."),
        };
    }
}
