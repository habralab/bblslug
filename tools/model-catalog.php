<?php

declare(strict_types=1);

use Bblslug\Bblslug;
use Bblslug\Models\ModelRegistry;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc > 2 || ($argc === 2 && $argv[1] !== '--check')) {
    fwrite(STDERR, "Usage: php tools/model-catalog.php [--check]\n");
    exit(1);
}

$catalog = json_decode(
    (string) file_get_contents(dirname(__DIR__) . '/resources/model-catalog.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$lines = [
    '# Model catalogue',
    '',
    '[README](../README.md) · [Usage](usage.md) · [Providers](providers.md)',
    '',
    'Generated from `resources/models.yaml` by `php tools/model-catalog.php`; do not edit the lists by hand.',
    '',
    'Use `vendor/bin/bblslug --list-models` for models suited to new integrations, or',
    '`vendor/bin/bblslug --list-models=all` for the complete registry. PHP `Bblslug::listModels()`',
    'continues to return every registered key. Lists are sorted by provider and then natural model-name order.',
    '',
    '## Choosing a model',
    '',
    'Choose a canonical provider model ID. The built-in registry excludes provider aliases, compatibility',
    'redirects and dated snapshots. An undated ID can identify a fixed version; the absence of a date alone',
    'does not establish whether an ID is canonical. Some older families have no entry under this policy.',
    'The library sends the selected API identifier; it does not silently replace retired models.',
    '',
    '| Kind | Meaning |',
    '| --- | --- |',
    '| `model` | Version/family ID without a pinned build date |',
    '| `preview` | Preview or release-candidate identifier |',
    '| `tier` | DeepL service tier; choose the translation engine with `model_type` |',
    '',
    'Lifecycle (`available` / `deprecated`) is separate from access (`standard`, `existing_customers`,',
    '`restricted`). Standard access still requires a valid account, region, quota and supported API.',
    'Announced shutdown dates are shown where known; an absent date means no date is recorded,',
    'not indefinite support.',
    '`shutdown` is an announced API retirement; `redirect` is an announced automatic model reroute.',
    '`retirement not before` is a lower bound, not a scheduled retirement or a guarantee beyond that date.',
    '',
    '## Models for new integrations',
    '',
    'Canonical model versions remain selectable, including supported legacy versions.',
    'This view omits previews, deprecated entries, and models with',
    '`restricted` or `existing_customers` access.',
    'It is a discovery aid, not a claim that every listed model was live-tested for every format.',
    '',
    '| Model key | Kind |',
    '| --- | --- |',
];
$groups = Bblslug::listModels();
foreach ($groups as $models) {
    foreach ($models as $key => $config) {
        if (ModelRegistry::isDefaultListing($config)) {
            $lines[] = '| `' . $key . '` | ' . $config['kind'] . ' |';
        }
    }
}
$lines = array_merge($lines, [
    '',
    '## Full registry',
    '',
    '<details>',
    '<summary>Show all registered models, including previews and legacy entries</summary>',
    '',
    'APIs supported:',
    '',
]);
foreach ($groups as $vendor => $models) {
    $lines[] = '- ' . $vendor . ':';
    foreach ($models as $key => $config) {
        $labels = [$config['kind'], $config['status'], $config['access']];
        foreach (
            [
            'shutdown_date' => 'shutdown',
            'retirement_not_before' => 'retirement not before',
            'redirect_date' => 'redirect',
            ] as $field => $description
        ) {
            if (isset($config[$field])) {
                $labels[] = $description . ' ' . $config[$field];
            }
        }
        if (isset($config['replacement'])) {
            $labels[] = 'replacement ' . $config['replacement'];
        }
        $lines[] = '  - `' . $key . '` — ' . implode('; ', $labels);
    }
}
$lines = array_merge($lines, [
    '',
    'This list mirrors the registry. Last broad lifecycle review: **' . $catalog['checked_at'] . '**.',
    '',
    '</details>',
    '',
    '## Lifecycle sources and maintenance',
    '',
    'Lifecycle metadata lives in `resources/models.yaml`: `kind`, `status`, `access`, optional',
    '`shutdown_date`, `retirement_not_before`, `redirect_date`, `replacement`, `checked_at` and',
    '`lifecycle_source`. Sources apply to the provider-hosted API,',
    'not similarly named models on third-party hosting. Verified retired IDs are excluded rather than redirected.',
    '',
]);
foreach ($groups as $vendor => $models) {
    $config = reset($models);
    $lines[] = '- [' . $vendor . '](' . $config['lifecycle_source'] . ')';
}
$lines = array_merge($lines, [
    '',
    'API payload IDs, exclusions and dated historical plain-text observations are retained in',
    '[model-catalog.json](../resources/model-catalog.json). Historical checks are not current availability guarantees.',
    'When updating a provider, check its lifecycle source, update metadata and exclusions, regenerate this page,',
    'then run `php tools/model-catalog.php --check` and the registry tests.',
    'Keep removed keys in `resources/unavailable-models.json` with a reason and, when verified, a currently',
    'registered replacement. PHP and CLI use this separate history for errors; removed keys stay unselectable.',
    'No provider API is contacted by generation.',
    '',
]);
$content = implode("\n", $lines);
$path = dirname(__DIR__) . '/docs/models.md';
if ($argc === 2) {
    $today = gmdate('Y-m-d');
    foreach ($groups as $models) {
        foreach ($models as $key => $config) {
            foreach (['shutdown_date', 'redirect_date'] as $field) {
                if (isset($config[$field]) && $config[$field] <= $today) {
                    fwrite(STDERR, "Recheck expired {$field} for {$key}: {$config[$field]}\n");
                    exit(1);
                }
            }
        }
    }
    if (!is_file($path) || file_get_contents($path) !== $content) {
        fwrite(STDERR, "Model catalogue is stale; run php tools/model-catalog.php\n");
        exit(1);
    }
    echo "Model catalogue matches the registry.\n";
} else {
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Cannot write docs/models.md');
    }
    echo "Generated docs/models.md\n";
}
