# CLI and PHP usage

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

Start with the CLI and PHP examples in the [README](../README.md). This page covers configuration, I/O, diagnostics and the library result. For all CLI flags, run `vendor/bin/bblslug --help`.

- [Example setup](#running-the-file-examples)
- [Credentials and I/O](#credentials-input-and-output)
- [Formats](#formats)
- [Filters and model options](#filters-and-model-options)
- [File translation](#translate-a-json-file-and-write-to-another-file)
- [Tone and terminology](#add-translation-context-prompt-source-and-target-language)
- [Prompt templates](#choose-a-different-prompt-template) and [provider settings](#pass-model-specific-variables)
- [Dry-run](#dry-run-placeholders-only) and [diagnostics](#verbose-mode-prints-request-preview-to-stderr)
- [Statistics](#statistics)
- [PHP API](#php-library)

## Running the file examples

Examples use a Composer-installed package and run from your project directory. Use your own input
files, or copy `input.txt`, `input.html`, `input.json` and `translation.schema.json` from
`vendor/habr/bblslug/samples/quickstart/` into that directory. The [sample guide](../samples/README.md)
describes their contents.

From a repository checkout, use `bin/bblslug` instead of `vendor/bin/bblslug`; the same inputs are in
`samples/quickstart/`.

Translation commands contact the selected provider. Add `--dry-run` to prepare input without a
translation request; it still requires a configured key. Use full option names from `--help`.
Unknown or abbreviated flags fail before input is read or requests are sent, including with `--help`.

## Credentials, input and output

Export credentials only for the provider you use:

| Provider | Environment variables |
| --- | --- |
| Anthropic | `ANTHROPIC_API_KEY` |
| DeepL | `DEEPL_FREE_API_KEY` or `DEEPL_PRO_API_KEY`, matching the selected tier |
| Google | `GOOGLE_API_KEY` |
| OpenAI | `OPENAI_API_KEY` |
| Yandex | `YANDEX_API_KEY` and `YANDEX_FOLDER_ID` |
| xAI | `XAI_API_KEY` |

Each name also accepts the `BBLSLUG_` prefix. A nonempty prefixed value takes
precedence over the unprefixed fallback, including for the Yandex folder ID.

### Input and output

Omit `--source` to read STDIN and `--translated` to write STDOUT. Progress, errors and usage go to
STDERR. Translation or output-write failures return a nonzero exit status; diagnostics never use STDOUT.

`--translated` accepts one nonempty path. Known invalid local paths fail before API calls, without
creating or truncating output. Dry-run ignores a valid `--translated` path and writes prepared input instead.

For ordinary local files with a writable parent, output is staged and replaced after a complete write.
A failed staged write preserves the previous file. Streams and other paths that require direct writing
can receive partial output on I/O failure. This applies to dry-run `.prepared` files too.

<details>
<summary>File replacement details</summary>

Existing symlinks to regular files retain the link while their targets are replaced. Basic permission
bits are preserved; new files use `0666 & ~umask`. Replacement creates a new inode and does not copy
ACLs or extended attributes.

Stream URIs (including `file://`), special files, hardlinks, dangling symlinks, existing writable files
in read-only directories, and files whose ownership or group would change on replacement use checked
direct writes. Failed atomic replacement is reported without falling back to a direct write.
Disk space and permissions can change after preflight, so writes are checked again at completion.

</details>

### Proxy

Use `--proxy=http://localhost:8888` or set `BBLSLUG_PROXY`, for example:

```bash
export BBLSLUG_PROXY='socks5h://127.0.0.1:9050'
```

Proxy settings apply to all HTTP requests and support authentication with a URI
such as `http://user:pass@host:port`.

### HTTP timeouts

All providers use a 10-second connection timeout and a 120-second total request
timeout. Set `BBLSLUG_HTTP_CONNECT_TIMEOUT` and `BBLSLUG_HTTP_TIMEOUT` to positive
integer seconds to override them. Unset or empty values use the defaults;
invalid values fail before sending a request, including library dry-run mode.
CLI dry-run only runs preparation and does not use the HTTP client.
For a shared budget across multiple requests, use an
[operation timeout](transports.md#limit-the-whole-translation-operation).

Direct `HttpClient::request()` callers can pass `connectTimeoutSeconds` and
`timeoutSeconds`; explicit arguments take precedence over environment values. The optional positive
`timeoutMilliseconds` cap can shorten, but cannot extend, that request timeout.
Network failures throw `RuntimeException` with the cURL error code (28 for a
timeout). Requests are not automatically retried.

### HTTP response size

Set `BBLSLUG_HTTP_MAX_RESPONSE_BYTES` to a positive integer byte count to cap each
HTTP response body, including error responses and DeepL document downloads.
Unset or empty means unlimited. Invalid values fail before sending a request;
CLI dry-run does not use or validate HTTP settings. Direct `HttpClient::request()`
callers can pass `maxResponseBytes` to override the environment value.

The client counts received body bytes rather than trusting `Content-Length`;
chunked transfer framing does not count toward the limit. Exceeding the cap aborts
the download with `RuntimeException` and cURL code 23 (`CURLE_WRITE_ERROR`), without
returning a partial response or automatically retrying. The cap covers one response
body; request data, response headers, verbose logs and the aggregated translation
result can use additional memory.

## Formats

| Format | Input |
| --- | --- |
| `text` | Plain text |
| `html` | HTML document or fragment |
| `json` | One JSON document |
| `linedJson` | One JSON document using an alternative translation prompt; not JSON Lines |
| `structured_json` | JSON with an explicit provider-supported response schema; see [native structured JSON](providers.md#native-structured-json) |

Translation and dry-run require both a registered model and a format. Use
`vendor/bin/bblslug --list-models` and `--list-prompts` to inspect available keys
and template formats without credentials or a translation request.

## Filters and model options

Use `url` to preserve URLs or `html_TAG` to preserve complete HTML blocks, for example
`html_code` or `html_pre`. `filters: []` and CLI `--filters=` disable filters.
Unknown names and empty entries such as `url,,html_code` fail before translation.

Tag names start with an ASCII letter and exclude whitespace, quotes, angle brackets, `/` and control
characters. Custom names such as `x-panel`, `svg:path` and `x-элемент` are supported. See
[JSON filtering and tag boundaries](transports.md#filters-inside-json).

Pass provider settings through `variables` / `--variables`, such as `temperature`, `response_schema`,
`json_transport` or `document_timeout`. Use dedicated arguments for `format`, `dryRun`, `promptKey`,
`verbose` and `context`; these names and the internal `responseContext` are reserved and rejected in
variables, including during dry-run.

## Translate a JSON file and write to another file

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=json \
  --source=input.json \
  --translated=output.json
```

## Translate an HTML file and write to another file with filters

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --filters=url,html_code,html_pre
```

## Add translation context (prompt), source and target language

Use `context` on each PHP call for tone, terminology, audience or subject matter. The built-in LLM
templates include it alongside their translation and response-format instructions. For example
(with the [PHP setup](#php-library) and `$apiKey`):

```php
$result = \Bblslug\Bblslug::translate(
    apiKey: $apiKey,
    format: 'text',
    modelKey: 'google:gemini-flash-latest',
    text: 'Welcome to your workspace!',
    context: 'Use formal address. Translate "workspace" as "Arbeitsbereich".',
    sourceLang: 'EN',
    targetLang: 'DE',
);
```

`context` adds instructions; `promptKey` selects a template group. Format, filters and validation
remain controlled by their own arguments. A custom template must contain `{context}` to include it.
DeepL uses its native context field rather than an LLM prompt; the Document API rejects context.
See [DeepL transport options](providers.md#deepl-json-transport).

The CLI equivalent is `--context`:

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --target-lang=DE \
  --source-lang=EN \
  --context='Use formal address. Translate "workspace" as "Arbeitsbereich".'
```

## Pass model-specific variables

For OpenAI, `--variables=reasoning_effort=low,max_completion_tokens=8192` can set
the reasoning effort and output budget (including reasoning tokens). These parameters
must be supported by the selected model. GPT-6 reasoning requests should omit temperature.

CLI values are passed as strings, separated by commas; there is no escaping syntax
for commas inside values. Drivers convert known numeric parameters. For boolean
options using a PHP boolean cast, such as Gemini's `includeThoughts`, use `0` or `1`:
the non-empty string `false` is treated as true.

```bash
vendor/bin/bblslug \
  --model=openai:gpt-6-luna \
  --format=text \
  --variables=max_completion_tokens=8192,reasoning_effort=low \
  --source=input.txt \
  --translated=out.txt
```

## Choose a different prompt template

Use `--list-prompts` to check supported formats. The `experimental` and
`experimental_xml` groups support JSON only.

```bash
vendor/bin/bblslug \
  --model=openai:gpt-6-luna \
  --format=json \
  --source=input.json \
  --translated=out.json \
  --prompt-key=experimental
```

The opt-in `experimental_xml` group uses an XML-style JSON prompt:

```bash
vendor/bin/bblslug --model=openai:gpt-6-luna --format=json \
  --source=input.json --target-lang=DE --prompt-key=experimental_xml
```

PHP callers use `promptKey: 'experimental_xml'`. This JSON-only template is plain text with
XML-like tags; no XML/POML renderer is required. It cannot be combined with native structured JSON,
which needs a `structured_json` template. `translator` is the default group.

### Application-owned templates

For an application-wide prompt policy, copy the [bundled YAML](../resources/prompts.yaml) from
`vendor/habr/bblslug/resources/prompts.yaml` to your application, for example `config/prompts.yaml`.
Edit its groups or add your own, then load it during application startup:

```php
require 'vendor/autoload.php';

\Bblslug\Models\Prompts::load(__DIR__ . '/config/prompts.yaml');
```

Load it before the first `translate()`, `listPrompts()` or template render. The first load determines
all groups for the PHP process; built-in groups are not merged and later `load()` calls have no effect.
This is bootstrap configuration, not a per-request template switch for a long-running worker.

Select an added group with `promptKey: 'my_translator'`, or keep `translator` if you edited that group.
Templates can use `{source}`, `{target}`, `{context}`, `{start}` and `{end}`; input content is sent
separately. Preserve the response and placeholder rules in the template you replace. Segments use
`json` templates, or `structured_json` when native JSON is selected; native document mode also uses
`structured_json`. Supply the formats your application uses. DeepL does not use these LLM templates.

## Dry-run placeholders only

CLI dry-run applies filters without calling a provider or validating the container.
It still requires a registered model, format, and API-key environment variable.
With `--source=input.txt`, it writes `input.txt.prepared`; with STDIN, it writes
the prepared text to STDOUT. `--translated` is not used in this mode.
The library's `dryRun: true` follows the full preparation and validation path and
returns a request preview without sending a request.

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=text \
  --filters=url \
  --source=input.txt \
  --dry-run
```

## Verbose mode (prints request preview to stderr)

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=html \
  --verbose \
  --source=input.html \
  --translated=out.html
```

## Standard input and output

```bash
printf 'Hello world\n' | vendor/bin/bblslug \
  --model=google:gemini-flash-latest --format=text --target-lang=DE > translated.txt
```

Use `--translated=translated.txt` instead of shell redirection to use the CLI output writer and its preflight checks.

## Secret masking in diagnostics

API keys are masked in HTTP diagnostics and provider exceptions, including URL/form encoding and
up to two layers of JSON string escaping. Raw response bodies, headers and sent payloads retain their data.
Low-level `HttpClient::request()` callers supply secrets through `maskPatterns`.

Verbose error reports include masked request/response diagnostics. Source and translated content can
remain visible, so treat logs as translation data. Network exceptions retain their cURL error code.

## Verbose filter and validation diagnostics

With `--verbose`, the CLI prints masked filter and validation previews to STDERR. PHP callers receive
them through `onFeedback` with `verbose: true`.

| Preview | Limit |
| --- | --- |
| Captured URL or HTML-filter values | 50 captures per filter; 200 characters per value |
| Restored document in JSON/HTML validation errors | 2000 characters |

Values are masked before truncation; an ellipsis marks shortened content. Filter values are
JSON-quoted and can show intermediate placeholders. HTTP request/response logs have separate behavior
and are not covered by these preview limits.

`FilterManager::getDebugMaps()` and `DebuggableFilterInterface::debugDump()` return raw captures.
Mask them before logging. Callback exceptions are ignored.

## Statistics

After a successful translation, the CLI prints provider-reported usage to STDERR:

```text
Usage metrics:
    Tokens:
        Total:       1074
        Prompt:      631
        Completion:  443
```

The PHP result exposes the same normalized counters in `consumed`. Counters are nonnegative integers;
missing or invalid values use zero. When a packet sum exceeds `PHP_INT_MAX`, that counter is omitted
rather than estimated or clipped; other counters remain available. With checkpoints, `consumed`
covers the whole document and `consumedThisRun` covers new successful requests.

## PHP library

```php
require 'vendor/autoload.php';

use Bblslug\Bblslug;
```

The remaining PHP snippets assume this import, a configured `$apiKey` (`$key` in shorter snippets),
and source strings `$html` / `$json` where shown. Yandex also needs `$folderId`.
Credentials are supplied explicitly to `translate()`; the CLI resolves provider environment variables automatically.

### Translation result and options

Translate an HTML document with optional settings:

```php
$text = file_get_contents('input.html');
if ($text === false) {
    throw new \RuntimeException('Cannot read input.html');
}
$apiKey = getenv('BBLSLUG_OPENAI_API_KEY') ?: getenv('OPENAI_API_KEY');
if ($apiKey === false || $apiKey === '') {
    throw new \RuntimeException('Set BBLSLUG_OPENAI_API_KEY or OPENAI_API_KEY');
}
$result = Bblslug::translate(
    apiKey: $apiKey,
    format: 'html',
    modelKey: 'openai:gpt-6-luna',
    text: $text,
    filters: ['url', 'html_code'],
    sourceLang: 'EN',
    targetLang: 'DE',
);
echo $result['result'];
```

Optional settings are grouped by purpose:

| Settings | Purpose |
| --- | --- |
| `context` | Additional tone, terminology, audience or domain instructions for this call |
| `promptKey` | Named template group; default `translator` |
| `sourceLang`, `targetLang` | Source and target languages |
| `filters` | Content preserved through placeholders |
| `variables`, `proxy` | [Provider settings](providers.md) and HTTP proxy |
| `validate`, `validateEmbeddedJson`, `validateHtmlJsonPointers` | [Container and selected-content checks](transports.md#validation-and-opting-out) |
| `textTransport`, `htmlTransport`, `htmlJsonPointers` | [Segment translation](transports.md) |
| `textSegmentBatchSize`, `htmlSegmentBatchSize`, `segmentBatchMaxBytes`, `segmentAbbreviations` | [Packet planning](transports.md#bound-segment-packets-by-serialized-bytes) |
| `checkpointPath`, `operationTimeoutSeconds` | [Resume](transports.md#resume-confirmed-successful-segment-packets) and [operation deadline](transports.md#limit-the-whole-translation-operation) |
| `dryRun` | Prepare and validate without sending a request |
| `verbose`, `onFeedback` | Diagnostics and progress callback |

`onFeedback` receives `(string $message, string $level = 'info')`; levels are `info`, `warning` and
`error`. Pass `null` to disable it. Callback exceptions do not interrupt translation.

`translate()` returns an array:

| Key | Value |
| --- | --- |
| `original`, `prepared`, `result` | Input, filtered input and translated output strings |
| `httpStatus` | Last response status; 0 for dry-run |
| `debugRequest`, `debugResponse` | Request/response diagnostics |
| `rawResponseBody` | Raw provider response; a JSON array of response strings when batching is configured |
| `consumed` | Provider usage grouped by category, for example `tokens` with `total` and `breakdown`; `[]` when unavailable |
| `lengths` | Character counts keyed by `original`, `prepared` and `translated` |
| `filterStats` | Entries such as `['filter' => 'url', 'count' => 3]` |
| `batches` | Ordered packet summaries when a count or byte cap is configured |
| `reusedBatches`, `consumedThisRun` | Reuse count and fresh usage when checkpointing is enabled |

### List available models

```php
$modelsByVendor = Bblslug::listModels();

foreach ($modelsByVendor as $vendor => $models) {
    echo "Vendor: {$vendor}\n";
    foreach ($models as $key => $config) {
        printf("  - %s: %s\n", $key, $config['notes'] ?? '(no notes)');
    }
}
```

### List available prompts

```php
$prompts = Bblslug::listPrompts();

foreach ($prompts as $key => $info) {
    $formats = implode(', ', $info['formats']);
    echo "{$key} ({$formats})";
    if (! empty($info['notes'])) {
        echo " – {$info['notes']}";
    }
    echo "\n";
}
```

### Error handling

`translate()` returns a result after complete success and throws on failure. Common exception classes:

| Exception | Meaning |
| --- | --- |
| `InvalidArgumentException` | Invalid arguments or settings, such as an unknown model, unsupported format or invalid timeout |
| `RuntimeException` | Request/provider failure, rejected response, failed integrity/validation check or checkpoint I/O error |

Use [checkpoints](transports.md#resume-confirmed-successful-segment-packets) to retain confirmed
packets when a multi-packet job fails.
