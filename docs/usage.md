# CLI and PHP usage

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

Start with the CLI and PHP examples in the [README](../README.md). This page covers configuration, I/O, diagnostics and the library result. For all CLI flags, run `vendor/bin/bblslug --help`.

- [Credentials, input and output](#credentials-input-and-output)
- [Formats](#formats)
- [Filters and model options](#filters-and-model-options)
- [Translate a JSON file and write to another file](#translate-a-json-file-and-write-to-another-file)
- [Translate an HTML file and write to another file with filters](#translate-an-html-file-and-write-to-another-file-with-filters)
- [Add translation context (prompt), source and target language](#add-translation-context-prompt-source-and-target-language)
- [Pass model-specific variables](#pass-model-specific-variables)
- [Choose a different prompt template](#choose-a-different-prompt-template)
- [Dry-run placeholders only](#dry-run-placeholders-only)
- [Verbose mode (prints request preview to stderr)](#verbose-mode-prints-request-preview-to-stderr)
- [Standard input and output](#standard-input-and-output)
- [Secret masking in diagnostics](#secret-masking-in-diagnostics)
- [Verbose filter and validation diagnostics](#verbose-filter-and-validation-diagnostics)
- [Statistics](#statistics)
- [PHP library](#php-library)

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

Omit `--source` to read STDIN and `--translated` to write STDOUT. Progress,
errors and usage statistics go to STDERR. If STDERR is unavailable or its pipe
is broken, diagnostics are skipped; they never fall back to STDOUT. Failed
requests or output writes return a nonzero exit status.

`--translated` accepts one nonempty path. Missing, empty and repeated values
fail before translation. For ordinary local paths, directories, missing parent
directories and obvious permission failures are checked before API calls.
Preflight does not create or truncate output. Existing writable files can be
updated inside read-only parent directories. Stream outputs retain write-time
checks; dry-run ignores a valid `--translated` path. Disk space and permissions
can change after preflight, so final write errors are checked separately.

Ordinary local output files are staged in their destination directory, flushed
and replaced only after the complete write succeeds. The same writer protects
dry-run `.prepared` files. Failed staged writes leave previous files intact.
Existing symlinks to regular files are retained while their targets are replaced.
Basic permission bits are preserved; new files use `0666 & ~umask`. Replacement
creates a new inode and does not copy ACLs or extended attributes.

Stream URIs (including `file://`), special files, hardlinks, dangling symlinks,
existing writable files inside read-only directories, and files whose ownership
or group would change on replacement use checked direct writes. These can
receive a partial write on I/O failure. Failed atomic replacement is reported
without retrying through a destructive direct write.

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
`timeoutSeconds`; explicit arguments take precedence over environment values.
Network failures throw `RuntimeException` with the cURL error code (28 for a
timeout). Requests are not automatically retried.

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

Filter names are `url` or `html_TAG`. Unknown names (for example `urll`) and
malformed tag names cause an error before translation. `TAG` starts with an ASCII
letter and cannot contain whitespace, quotes, angle brackets, forward slashes, or control
characters; custom names such as `x-panel`, `svg:path`, and `x-элемент` are allowed.
An empty API list (`filters: []`) or CLI `--filters=` applies no filters. Empty
entries inside a nonempty CLI list, such as `url,,html_code`, are rejected.

`variables` / `--variables` accepts model-specific options. The names `format`,
`dryRun`, `promptKey`, `verbose`, and `context` are reserved: use the corresponding
`translate()` arguments or CLI flags. `responseContext` is internal request state
and cannot be supplied through variables. Reserved names cause an error even if
the value is `null` or equals the explicit argument, including in dry-run mode.
The diagnostic names the option without printing its value. Options such as
`temperature`, `response_schema`, `json_transport`, and `document_timeout` remain
available through variables.

## Translate a JSON file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=json \
  --source=input.json \
  --translated=output.json
```

## Translate an HTML file and write to another file with filters

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --filters=url,html_code,html_pre
```

## Add translation context (prompt), source and target language

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --target-lang=EN \
  --source-lang=DE \
  --context="Translate as a professional technical translator"
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
  --model=vendor:name \
  --format=text \
  --variables=max_completion_tokens=8192,reasoning_effort=low \
  --source=in.txt \
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
vendor/bin/bblslug --model=openai:gpt-5-mini --format=json \
  --source=input.json --target-lang=DE --prompt-key=experimental_xml
```

PHP callers select `promptKey: 'experimental_xml'`. The template is sent verbatim
as text with XML-like tags. The library substitutes its placeholders; it does not
parse XML, expand entities, or run a [POML renderer](https://microsoft.github.io/poml/latest/language/basic/).
`translator` remains the default. This experimental group provides no `text`,
`html`, `linedJson`, or `structured_json` template; native JSON mode requests
`structured_json` and therefore cannot use this group.

## Dry-run placeholders only

CLI dry-run applies filters without calling a provider or validating the container.
It still requires a registered model, format, and API-key environment variable.
With `--source=input.txt`, it writes `input.txt.prepared`; with STDIN, it writes
the prepared text to STDOUT. `--translated` is not used in this mode.
The library's `dryRun: true` follows the full preparation and validation path and
returns a request preview without sending a request.

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --filters=url \
  --source=input.txt \
  --dry-run
```

## Verbose mode (prints request preview to stderr)

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --verbose \
  --source=input.html \
  --translated=out.html
```

## Standard input and output

```bash
printf 'Hello world\n' | vendor/bin/bblslug \
  --model=google:gemini-2.5-flash --format=text --target-lang=DE > translated.txt
```

Use `--translated=translated.txt` instead of shell redirection to use the CLI output writer and its preflight checks.

## Secret masking in diagnostics

The facade masks the API key in HTTP diagnostic logs and provider exceptions.
Low-level `HttpClient::request()` callers supply raw secrets through
`maskPatterns`. Masking covers raw text, URL/form encoding, and up to two layers
of JSON string escaping, including hexadecimal escape case variations. Empty
patterns are ignored; overlapping secrets are matched longest first.

Request and response diagnostic copies are sanitized. Sent payloads, returned
response bodies and headers keep their original data. Network exceptions retain
the cURL error code and redact known secrets from the message.

## Verbose filter and validation diagnostics

With `--verbose`, the CLI prints captured URL and HTML-filter previews to stderr.
Library callers receive these messages through `onFeedback` when `verbose: true`.
Each filter shows at most 50 captures; each value is masked before being shortened
to 200 characters plus an ellipsis. Values are JSON-quoted so line breaks and
backslashes remain visible on one log line. Maps describe intermediate filter
stages and may contain internal placeholder tokens. Translation output and exact
protected source bytes are unchanged.

JSON syntax errors and HTML syntax/structure errors include a preview of the
restored invalid document only in verbose mode. This preview is masked before
being limited to 2000 characters plus an ellipsis. Existing HTTP diagnostic logs
have their own behavior; this limit applies to the restored-document preview.
The new previews mask the supplied API key, proxy URI, and Yandex folder ID using
the same encoded-secret representations as `SecretMasker`. Other source content
can remain visible, so verbose output is diagnostic data.

`FilterManager::getDebugMaps()` and `DebuggableFilterInterface::debugDump()` expose
raw captures for diagnostic integrations; callers must mask them before logging.
The optional interface leaves the existing `FilterInterface` contract unchanged.
Callback exceptions remain isolated from translation.

## Statistics

- **Usage metrics**

  Counters accept nonnegative integers, decimal integer strings (including Yandex's
  string counters), and finite whole-valued floats within PHP's integer range.
  Missing or malformed values use the existing zero fallback. Negative, fractional,
  nonfinite and out-of-range values are not truncated, rounded or saturated into
  counts. Decimal fractions and exponent notation in strings are rejected rather
  than passed through a floating-point conversion. This applies equally with or
  without checkpointing.

  When summing packets, a total or breakdown counter that exceeds `PHP_INT_MAX`
  is omitted for the entire aggregation. Other representable counters remain.
  Later packets do not restart an incomplete sum. No overflowed count is returned
  as a float, saturated value or artificial zero; the CLI omits its line too.
  This applies to both whole-document and current-run checkpoint statistics.

  After each translation (when not in dry-run), Bblslug prints to stderr a summary of consumed usage metrics, for example:

  ```
  Usage metrics:
      Tokens:
          Total:       1074
          -----------------
          Prompt:      631
          Completion:  443
  ```

## PHP library

```php
require 'vendor/autoload.php';

use Bblslug\Bblslug;
```

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
$verbose = true;
$result = Bblslug::translate(
    apiKey:   $apiKey,
    format:   'html',                          // text, html, json, structured_json, linedJson
    modelKey: 'openai:gpt-6-luna',              // A key from Bblslug::listModels()
    text:     $text,                           // Source text to be translated
    // optional:
    // Additional context/prompt pass to model
    context:    'Translate as a professional technical translator',
    filters:    ['url','html_code'],           // List of placeholder filters
    onFeedback: $verbose ? static function (string $message, string $level = 'info'): void { error_log("[$level] $message"); } : null, // Optional progress callback
    promptKey: 'translator',                   // which prompt template to use
    proxy:      getenv('BBLSLUG_PROXY') ?: null, // Optional proxy URI
    sourceLang: 'DE',                          // Source language code (optional; autodetect if null)
    targetLang: 'EN',                          // Target language code (optional; default from driver settings)
    validate: true,                            // Container syntax and JSON structure validation
    variables:  ['max_completion_tokens'=>'8192'], // Model-specific overrides
    verbose:    true,                          // If true, returns debug request/response
);
echo $result['result'];
```

`onFeedback` is an optional callback for the progress information, accepts (string $message, string $level),
where `$level` includes `'info' | 'warning' | 'error'`. Pass null to switch it off.

Result structure:

```php
[
  'original'        => string,   // Original input
  'prepared'        => string,   // After placeholder filters
  'result'          => string,   // Translated result
  'httpStatus'      => int,      // HTTP status
  'debugRequest'    => string,   // Request debug
  'debugResponse'   => string,   // Response debug
  'rawResponseBody' => string,   // Response body
  'consumed'        => [                  // Normalized usage metrics; [] if unavailable
     'tokens' => [
       'total'     => int,                // Total tokens consumed
       'breakdown' => [                   // Per-type breakdown
         'prompt'      => int,            // Names depend on the model
         'completion'  => int,
       ],
     ],
     // additional categories if supported by the model...
  ],
  'lengths'         => [         // Text length statistics
     'original'    => int,       // - original text
     'prepared'    => int,       // - after placeholder filters
     'translated'  => int,       // - returned translated text
  ],
  'filterStats'     => [         // Placeholder stats
     ['filter'=>'url','count'=>3], …
  ],
]
```

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

```php
try {
    $res = Bblslug::translate(
        apiKey: $apiKey,
        format: 'html',
        modelKey: 'openai:gpt-6-luna',
        text: $text
    );
} catch (\InvalidArgumentException $e) {
    // Invalid model, unsupported format, invalid timeout settings, etc.
} catch (\RuntimeException $e) {
    // HTTP error, parse failure, driver-specific error
}
```
