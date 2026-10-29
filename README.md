# Bblslug

Bblslug is a PHP library and CLI for translating **plain text, HTML and JSON** through translation APIs.
It supports Anthropic, DeepL, Google, OpenAI, xAI and Yandex AI Studio.

Filters preserve URLs and selected HTML blocks. Validation checks structure and protected content.

## Installation

Requires **PHP 8.2+** with the cURL, DOM and mbstring extensions.

```bash
composer require habr/bblslug
```

For upgrades, see the [migration guide](docs/upgrading.md); release changes are in the [changelog](CHANGELOG.md).

## First translation with the CLI

Export the key for your chosen provider. For example:

```bash
export BBLSLUG_GOOGLE_API_KEY='your-api-key'
```

Translate HTML from STDIN, protecting code blocks and URLs:

```bash
printf '%s' '<p>Hello! Read <code>status=ready</code> at https://example.invalid/guide</p>' | \
  vendor/bin/bblslug --model=google:gemini-flash-latest --format=html \
    --source-lang=EN --target-lang=DE --filters=url,html_code,html_pre
```

Use `--format=text` for plain text or `--format=json` for a JSON document.
If `--source` is omitted, input comes from STDIN; without `--translated`, output goes to STDOUT.
Diagnostics and usage statistics go to STDERR. Failed translations return a nonzero exit status.

```bash
vendor/bin/bblslug --list-models
vendor/bin/bblslug --help
```

See the [usage guide](docs/usage.md#credentials-input-and-output) for other providers, credentials,
proxy settings and timeouts. The [model catalogue](docs/models.md) lists selectable model keys.

## First translation with PHP

```php
require 'vendor/autoload.php';

use Bblslug\Bblslug;

$apiKey = getenv('BBLSLUG_GOOGLE_API_KEY') ?: getenv('GOOGLE_API_KEY');
if ($apiKey === false || $apiKey === '') {
    throw new \RuntimeException('Set BBLSLUG_GOOGLE_API_KEY or GOOGLE_API_KEY');
}

$result = Bblslug::translate(
    apiKey: $apiKey,
    format: 'json',
    modelKey: 'google:gemini-flash-latest',
    text: '{"message":"Welcome to your workspace!","count":2}',
    context: 'Use formal address. Translate "workspace" as "Arbeitsbereich".',
    sourceLang: 'EN',
    targetLang: 'DE',
);

echo $result['result'];
```

Use `context` for tone, terminology, audience or domain instructions. It supplements the built-in
LLM prompt; format and protection settings still apply. See
[context and application-owned templates](docs/usage.md#add-translation-context-prompt-source-and-target-language).

Inputs and translated results are strings, including for JSON. The library validates the JSON structure
and exact non-string values by default. See [PHP options, result fields and error handling](docs/usage.md#php-library).

## Choosing how to translate

| Mode | Behavior | Selection |
| --- | --- | --- |
| Document (default) | The provider translates the prepared document; filters preserve selected content. | No transport option needed. |
| Segments | Bblslug preserves text line separators or HTML markup and translates text fragments. | `--text-transport=segments` or `--html-transport=segments` |

Segments also support selected HTML strings inside JSON. Count and byte caps divide fragments into
requests; checkpoints retain confirmed packets for resuming a job. See
[transports and validation](docs/transports.md) for examples and tradeoffs.

## Limits and data handling

Validation checks structure, protected content and some content loss. It does not establish translation
accuracy or completeness: a valid document or fragment can still omit meaning. Review translations
where accuracy matters.

JSON strings are translatable, including paths, identifiers and encoded content. Use
[filters or explicit validation](docs/transports.md#filters-inside-json) for content that must be preserved.

Requests are not retried automatically, and models are not substituted. A failed job can include
already billed packets; use checkpoints to retain confirmed results. Verbose logs and checkpoints
can contain source and translated content even when credentials are masked.

## Documentation

- [CLI and PHP usage](docs/usage.md): credentials, I/O, filters, prompts, diagnostics and API results.
- [Transports and validation](docs/transports.md): segments, batching, checkpoints, time budgets and validation rules.
- [Provider options](docs/providers.md): native JSON, model settings and provider-specific protocols.
- [Model catalogue](docs/models.md): registered keys and links to catalogue evidence.
- [Changelog](CHANGELOG.md): releases and compatibility changes.
- [Development checks](tools/README.md): offline and live verification tools and their scope.
- [Input samples](samples/README.md): authored quickstart files and historical corpora.

## License

[MIT](LICENSE).
