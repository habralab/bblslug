<?php

namespace Bblslug\Models;

class ModelRegistry
{
    protected array $models;

    public function __construct(?string $path = null)
    {
        $path ??= __DIR__ . '/../../../resources/models.php';
        $this->models = require $path;
    }

    public function get(string $key): ?array
    {
        return $this->models[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->models[$key]);
    }

    public function list(): array
    {
        return array_keys($this->models);
    }

    public function getEndpoint(string $key): ?string
    {
        return $this->models[$key]['endpoint'] ?? null;
    }

    public function getFormat(string $key): ?string
    {
        return $this->models[$key]['format'] ?? null;
    }

    public function getCharLimit(string $key): ?int
    {
        return $this->models[$key]['limits']['estimated_max_chars'] ?? null;
    }

    public function getAuthEnv(string $key): ?string
    {
        return $this->models[$key]['requirements']['auth']['env'] ?? null;
    }

    public function getHelpUrl(string $key): ?string
    {
        return $this->models[$key]['requirements']['auth']['help_url'] ?? null;
    }

    public function getNotes(string $key): ?string
    {
        return $this->models[$key]['notes'] ?? null;
    }

    public function getAll(): array
    {
        return $this->models;
    }
}
