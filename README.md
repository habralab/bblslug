# Bblslug

Bblslug is a PHP library and CLI for translating **plain text, HTML and JSON** through translation APIs.
It supports Anthropic, DeepL, Google, OpenAI, xAI and Yandex AI Studio.

Filters protect URLs and selected HTML blocks. Validation checks document structure and protected content.
Optional segment transport keeps HTML markup or plain-text line separators local while translating text fragments.

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
vendor/bin/bblslug --list-models=all
vendor/bin/bblslug --list-prompts
vendor/bin/bblslug --help
```

Credentials with the `BBLSLUG_` prefix take precedence over their unprefixed equivalents.
Other provider credentials, proxy settings and timeouts are covered in the [usage guide](docs/usage.md#credentials-input-and-output).
The [model catalogue](docs/models.md) lists registered models; availability depends on your provider account.

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
    text: '{"message":"Hello, world!","count":2}',
    sourceLang: 'EN',
    targetLang: 'DE',
);

echo $result['result'];
```

Inputs and translated results are strings, including for JSON. The library validates the JSON structure
and exact non-string values by default. See [PHP options, result fields and error handling](docs/usage.md#php-library).

## Choosing how to translate

The default **document** transport sends the prepared document to the provider.
Use filters such as `url`, `html_code` and `html_pre` to keep matching source content unchanged.

For stricter reconstruction, opt into **segments** with `--text-transport=segments` or
`--html-transport=segments`. Segment mode checks that each requested fragment has a response.
HTML tags and non-language attributes stay local. Selected HTML strings inside JSON can also use this mode.
Count and byte limits split requests into packets; optional checkpoints retain confirmed successful packets.
See [transports, protection and validation](docs/transports.md) for examples and tradeoffs.

## Limits and data handling

Validation detects structural changes and some content loss; it does **not** prove translation accuracy or completeness.
Document mode can omit prose inside an otherwise valid document. Segment mode can retain all fragment identifiers
while losing meaning within a fragment. Review translations where accuracy matters.

JSON string values are translatable content. Paths, identifiers, embedded JSON and HTML inside strings
need appropriate protection or explicit validation; the library does not infer business rules from field names.
See [filters and validation](docs/transports.md#filters-inside-json) and [provider-specific JSON modes](docs/providers.md).

Failed requests are not retried automatically, and another model is not substituted.
A later failure can occur after earlier packets have been billed. Output is returned only after complete success;
local file-write guarantees and exceptions are described in the [usage guide](docs/usage.md#credentials-input-and-output).

Diagnostic key masking does not remove all source or translated content. Verbose logs and checkpoints can contain
that content; handle them as private translation data.

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
