# Bblslug

**Bblslug** is a versatile translation tool that can be used as both a **CLI utility** and a **PHP library**.

It uses translation APIs for plain text, HTML and JSON, with validation and placeholder filters to help preserve structure, code blocks, and URLs.

APIs supported:

- Anthropic (Claude):
  - `anthropic:claude-haiku-5.5` - claude-haiku-5.5
  - `anthropic:claude-sonnet-5.5` - claude-sonnet-5.5
  - `anthropic:claude-opus-5.5` - claude-opus-5.5
  - `anthropic:claude-fable-5.1` - claude-fable-5.1
  - `anthropic:claude-opus-5` - claude-opus-5
  - `anthropic:claude-sonnet-5` - claude-sonnet-5
  - `anthropic:claude-fable-5` - claude-fable-5
  - `anthropic:claude-opus-4.8` - claude-opus-4.8
  - `anthropic:claude-opus-4.7` - claude-opus-4.7
  - `anthropic:claude-sonnet-4.6` - claude-sonnet-4.6
  - `anthropic:claude-opus-4.6` - claude-opus-4.6
  - `anthropic:claude-opus-4.5-20251101` - claude-opus-4.5-20251101
  - `anthropic:claude-haiku-4.5-20251001` - claude-haiku-4.5-20251001
  - `anthropic:claude-sonnet-4.5-20250929` - claude-sonnet-4.5-20250929
  - `anthropic:claude-mythos-5` - claude-mythos-5
  - `anthropic:claude-mythos-preview` - claude-mythos-preview
  - `anthropic:claude-mythos-5.1` - claude-mythos-5.1
- DeepL:
  - `deepl:free` - DeepL API Free
  - `deepl:pro` - DeepL API Pro
- Google (Gemini and Gemma):
  - `google:gemini-2.5-flash` - Gemini 2.5 Flash
  - `google:gemini-2.5-pro` - Gemini 2.5 Pro
  - `google:gemma-4-26b-a4b-it` - gemma-4-26b-a4b-it
  - `google:gemma-4-31b-it` - gemma-4-31b-it
  - `google:gemini-flash-latest` - gemini-flash-latest
  - `google:gemini-flash-lite-latest` - gemini-flash-lite-latest
  - `google:gemini-pro-latest` - gemini-pro-latest
  - `google:gemini-2.5-flash-lite` - Gemini 2.5 Flash-Lite
  - `google:gemini-3-flash-preview` - gemini-3-flash-preview
  - `google:gemini-3.1-pro-preview` - gemini-3.1-pro-preview
  - `google:gemini-3.1-pro-preview-customtools` - gemini-3.1-pro-preview-customtools
  - `google:gemini-3.1-flash-lite-preview` - gemini-3.1-flash-lite-preview
  - `google:gemini-3.1-flash-lite` - gemini-3.1-flash-lite
  - `google:gemini-3.5-flash-lite` - gemini-3.5-flash-lite
  - `google:gemini-3.6-flash` - gemini-3.6-flash
  - `google:gemini-3.8-flash` - gemini-3.8-flash
  - `google:gemini-4-argon` - gemini-4-argon
- OpenAI:
  - `openai:gpt-4-turbo-2024-04-09` - gpt-4-turbo-2024-04-09; scheduled shutdown 2026-10-23
  - `openai:gpt-5.6-luna` - gpt-5.6-luna
  - `openai:gpt-5-search-api` - gpt-5-search-api
  - `openai:gpt-5.5-2026-04-23` - gpt-5.5-2026-04-23
  - `openai:gpt-5.4-2026-03-05` - gpt-5.4-2026-03-05
  - `openai:gpt-3.5-turbo-0125` - gpt-3.5-turbo-0125; scheduled shutdown 2026-10-23
  - `openai:gpt-5-nano` - OpenAI GPT-5 Nano
  - `openai:gpt-5.4-mini` - gpt-5.4-mini
  - `openai:o1-pro` - o1-pro; scheduled shutdown 2026-10-23
  - `openai:gpt-5-mini` - OpenAI GPT-5 Mini
  - `openai:gpt-5-mini-2025-08-07` - gpt-5-mini-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:o4-mini-2025-04-16` - o4-mini-2025-04-16; scheduled shutdown 2026-10-23
  - `openai:gpt-5.2-pro` - gpt-5.2-pro
  - `openai:gpt-5-pro-2025-10-06` - gpt-5-pro-2025-10-06; scheduled shutdown 2026-12-11
  - `openai:gpt-5.6-sol` - gpt-5.6-sol
  - `openai:gpt-5.5-pro` - gpt-5.5-pro
  - `openai:gpt-5-2025-08-07` - gpt-5-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:gpt-5.2-pro-2025-12-11` - gpt-5.2-pro-2025-12-11
  - `openai:gpt-5.5-pro-2026-04-23` - gpt-5.5-pro-2026-04-23
  - `openai:gpt-5.4-pro` - gpt-5.4-pro
  - `openai:gpt-4o-2024-11-20` - gpt-4o-2024-11-20; scheduled shutdown 2027-01-14
  - `openai:gpt-5.1` - gpt-5.1; scheduled shutdown 2027-04-01
  - `openai:gpt-5-pro` - gpt-5-pro
  - `openai:gpt-5.4-mini-2026-03-17` - gpt-5.4-mini-2026-03-17
  - `openai:gpt-5.4` - gpt-5.4
  - `openai:gpt-4.1-nano` - gpt-4.1-nano; scheduled shutdown 2026-10-23
  - `openai:gpt-3.5-turbo-16k` - gpt-3.5-turbo-16k
  - `openai:gpt-4.1` - gpt-4.1; scheduled shutdown 2027-01-14
  - `openai:gpt-5.5` - gpt-5.5
  - `openai:o3-pro-2025-06-10` - o3-pro-2025-06-10; scheduled shutdown 2026-12-11
  - `openai:chat-latest` - chat-latest
  - `openai:gpt-5.4-pro-2026-03-05` - gpt-5.4-pro-2026-03-05
  - `openai:o3-2025-04-16` - o3-2025-04-16; scheduled shutdown 2026-12-11
  - `openai:gpt-5.2-2025-12-11` - gpt-5.2-2025-12-11
  - `openai:gpt-3.5-turbo` - gpt-3.5-turbo; scheduled shutdown 2026-10-23
  - `openai:gpt-4o-2024-05-13` - gpt-4o-2024-05-13; scheduled shutdown 2026-10-23
  - `openai:gpt-5.6-terra` - gpt-5.6-terra
  - `openai:o3-mini` - o3-mini; scheduled shutdown 2026-10-23
  - `openai:gpt-5.4-nano` - gpt-5.4-nano; scheduled shutdown 2027-04-01
  - `openai:o1-pro-2025-03-19` - o1-pro-2025-03-19; scheduled shutdown 2026-10-23
  - `openai:gpt-4-turbo` - OpenAI GPT-4 Turbo; scheduled shutdown 2026-10-23
  - `openai:o3-pro` - o3-pro
  - `openai:gpt-4o-2024-08-06` - gpt-4o-2024-08-06; scheduled shutdown 2027-01-14
  - `openai:o3` - o3
  - `openai:gpt-4.1-mini-2025-04-14` - gpt-4.1-mini-2025-04-14; scheduled shutdown 2027-01-14
  - `openai:o3-mini-2025-01-31` - o3-mini-2025-01-31; scheduled shutdown 2026-10-23
  - `openai:gpt-5.1-2025-11-13` - gpt-5.1-2025-11-13
  - `openai:gpt-4-0613` - gpt-4-0613; scheduled shutdown 2026-10-23
  - `openai:gpt-4o` - OpenAI GPT-4o; scheduled shutdown 2027-01-14
  - `openai:gpt-4.1-mini` - gpt-4.1-mini; scheduled shutdown 2027-01-14
  - `openai:gpt-4o-mini` - OpenAI GPT-4o Mini; scheduled shutdown 2027-01-14
  - `openai:gpt-6-sol` - gpt-6-sol
  - `openai:gpt-5.2` - gpt-5.2
  - `openai:o1` - o1; scheduled shutdown 2026-10-23
  - `openai:gpt-5.4-nano-2026-03-17` - gpt-5.4-nano-2026-03-17
  - `openai:gpt-6.1-sol` - OpenAI GPT-6.1 Sol
  - `openai:o1-2024-12-17` - o1-2024-12-17; scheduled shutdown 2026-10-23
  - `openai:gpt-5.3-codex` - gpt-5.3-codex; scheduled shutdown 2027-04-01
  - `openai:gpt-5-search-api-2025-10-14` - gpt-5-search-api-2025-10-14
  - `openai:gpt-5` - OpenAI GPT-5
  - `openai:gpt-4.1-nano-2025-04-14` - gpt-4.1-nano-2025-04-14; scheduled shutdown 2026-10-23
  - `openai:gpt-4.1-2025-04-14` - gpt-4.1-2025-04-14; scheduled shutdown 2027-01-14
  - `openai:gpt-4o-mini-2024-07-18` - gpt-4o-mini-2024-07-18; scheduled shutdown 2027-01-14
  - `openai:gpt-4` - OpenAI GPT-4; scheduled shutdown 2026-10-23
  - `openai:gpt-5-nano-2025-08-07` - gpt-5-nano-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:o4-mini` - o4-mini; scheduled shutdown 2026-10-23
  - `openai:gpt-6-astra` - OpenAI GPT-6 Astra
  - `openai:gpt-6-luna` - OpenAI GPT-6 Luna
  - `openai:gpt-5.6-cyber` - gpt-5.6-cyber
  - `openai:gpt-rosalind-research` - gpt-rosalind-research
  - `openai:gpt-rosalind-discovery` - gpt-rosalind-discovery
  - `openai:gpt-4-1106-preview` - gpt-4-1106-preview; scheduled shutdown 2026-10-23
- Yandex AI Studio:
  - `yandex:gpt-lite` - Yandex GPT Lite
  - `yandex:gpt-pro` - Yandex GPT Pro
  - `yandex:gpt-32k` - Yandex GPT Pro 32K
  - `yandex:aliceai-llm` - aliceai-llm
  - `yandex:aliceai-llm-flash` - aliceai-llm-flash
  - `yandex:yandexgpt-5.1` - yandexgpt-5.1
  - `yandex:yandexgpt-5-pro` - yandexgpt-5-pro
  - `yandex:yandexgpt-5-lite` - yandexgpt-5-lite
  - `yandex:deepseek-v4-flash` - deepseek-v4-flash
  - `yandex:deepseek-v4.1-flash` - deepseek-v4.1-flash
  - `yandex:qwen3-235b-a22b-fp8` - qwen3-235b-a22b-fp8
  - `yandex:gpt-oss-120b` - gpt-oss-120b
  - `yandex:gpt-oss-20b` - gpt-oss-20b
  - `yandex:qwen3.6-35b-a3b` - qwen3.6-35b-a3b
  - `yandex:yandexgpt-latest` - yandexgpt-latest
  - `yandex:yandexgpt-rc` - yandexgpt-rc
- xAI (Grok):
  - `xai:grok-4.20-0309-reasoning` - grok-4.20-0309-reasoning
  - `xai:grok-4.20-0309-non-reasoning` - grok-4.20-0309-non-reasoning
  - `xai:grok-4.20-multi-agent-0309` - grok-4.20-multi-agent-0309
  - `xai:grok-4.3` - grok-4.3
  - `xai:grok-4.5` - grok-4.5
  - `xai:grok-4.6` - grok-4.6
  - `xai:grok-4.7` - grok-4.7
  - `xai:grok-build-0.1` - grok-build-0.1

The catalogue was checked on **2026-10-22**. It includes available legacy,
preview, restricted-access, and dated snapshot models, not only current flagships.
Retired identifiers that merely redirect to replacements are excluded. Normal
provider-maintained rolling aliases are retained; they are not pinned snapshots.

This list mirrors the registry and `--list-models`. Account permissions, regional
availability, and model retirement dates still apply. Sources, API identifiers,
and exclusion evidence are recorded in [model-catalog.json](resources/model-catalog.json).

## Features

- Supports **HTML**, **JSON** and **plain text** (`--format=text|html|json|structured_json|linedJson`)
- Placeholder-based protection with filters: `html_pre`, `html_code`, `url`, etc.
- Model selection via `--model=vendor:name`
- Fully configurable backend registry (via `resources/models.yaml`)
- **Dry-run** mode to preview placeholders without making API calls
- **Variables** (`--variables`) to send or override model-specific options
- **Verbose** mode (`--verbose`) to print request previews
- Can be invoked as a CLI tool or embedded in PHP code
- **Validation** of container syntax for HTML or JSON; disable with `--no-validate`

The GPT-6 entries were checked against [official OpenAI documentation](https://developers.openai.com/api/docs/guides/latest-model) on 2026-10-22.
The GPT-6 defaults use Chat Completions with `reasoning_effort=low` and omit `temperature`.
Responses-only models use the Responses API with its own request and usage fields.
Older OpenAI model identifiers remain available for existing integrations; registry presence does not guarantee access for your API account.

The Claude 5.5 entries use the [Anthropic Messages API](https://platform.claude.com/docs/en/api/messages/create)
with `effort=low`, no `temperature`, and an initial output budget of 4096 tokens.
Use `--variables=maxTokens=8192` to override the output budget. Use `--variables=effort=low,maxTokens=8192` for models supporting effort; older
Claude snapshots omit unsupported thinking and effort parameters.
Retired Claude 3.5/4, Gemini 2.0, and compatibility-redirect Grok model keys have been removed, rather than redirected
to different models. Their retirement is documented by [Anthropic](https://platform.claude.com/docs/en/about-claude/model-deprecations)
and [Google](https://ai.google.dev/gemini-api/docs/deprecations).

DeepL Free and Pro are service tiers. Select any documented translation engine
with `--variables=model_type=quality_optimized`, `prefer_quality_optimized`, or
`latency_optimized`. The provider default applies when omitted. These are engine
options, rather than additional model IDs.

Yandex models using the OpenAI-compatible API retain the `yandex:` prefix and the
same credentials/folder variables. The registry selects the required endpoint;
the application does not substitute another model after a failure.

## Installation

Requires PHP 8.2 or newer with the cURL, DOM, and mbstring extensions.

**Upgrading from `0.8.0`:** the updated release is **0.9.0**. Packagist keeps
published version references immutable, so republishing the old tag cannot
replace its stored code. Update the consumer's constraint, for example with
`composer require habr/bblslug:0.9.0`, review the lock file, and commit both
`composer.json` and `composer.lock`. No VCS override is needed. See the
[changelog](CHANGELOG.md) for compatibility changes and validation limits.

```bash
composer require habr/bblslug
chmod +x vendor/bin/bblslug
```

## CLI Usage

### Prepare

1. **Specify a model and format** with `--model=vendor:name` and `--format=text|html|json|structured_json|linedJson`.
   Both are required for translation and dry-run; listing models, prompts, and help does not require them.

2. **Export your API key(s)** before running:

  ```bash
  export ANTHROPIC_API_KEY=...
  export DEEPL_FREE_API_KEY=...
  export DEEPL_PRO_API_KEY=...
  export GOOGLE_API_KEY=...
  export OPENAI_API_KEY=...
  export YANDEX_API_KEY=... && export YANDEX_FOLDER_ID=...
  export XAI_API_KEY=...
  ```

  **NB!** Some vendors require additional parameters, e.g. `YANDEX_FOLDER_ID`.

  To use a credential-specific prefix, set `BBLSLUG_` before the usual environment
  variable name (for example, `BBLSLUG_OPENAI_API_KEY` and
  `BBLSLUG_YANDEX_FOLDER_ID`). A non-empty prefixed value takes precedence; the
  unprefixed variable remains a fallback.

3. **Input / output**:

  - If `--source` is omitted, Bblslug reads from **STDIN**.
  - If `--translated` is omitted, Bblslug writes to **STDOUT**.
  - Progress, errors, and translation statistics go to **STDERR**.
  - Failed requests or output writes exit with a nonzero status.

4. **Optional proxy**:

  To route requests through a proxy (e.g. HTTP or SOCKS5), use the `--proxy` option or set the `BBLSLUG_PROXY` environment variable:

  ```bash
  # using CLI flag
  vendor/bin/bblslug --proxy="http://localhost:8888" ...
  
  # or set it globally
  export BBLSLUG_PROXY="socks5h://127.0.0.1:9050"
  ```

  This works for all HTTP requests and supports authentication (`http://user:pass@host:port`).

5. **HTTP timeouts**:

  All providers use a 10-second connection timeout and a 120-second total request
  timeout. Set `BBLSLUG_HTTP_CONNECT_TIMEOUT` and `BBLSLUG_HTTP_TIMEOUT` to change
  these limits (positive integer seconds). Unset or empty values use the defaults;
  invalid values fail before sending a request, including library dry-run mode.
  CLI dry-run only runs filters and does not use the HTTP client.

  Direct callers of `HttpClient::request()` can override these settings with
  `connectTimeoutSeconds` and `timeoutSeconds`. Explicit arguments take precedence
  over environment values. Network failures throw `RuntimeException` with the cURL
  error code (28 for a timeout). Requests are not automatically retried.


### Show available models

```bash
vendor/bin/bblslug --list-models
```

### Show available prompt templates

```bash
vendor/bin/bblslug --list-prompts
```

### Translate an HTML file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html
```

### Translate a JSON file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=json \
  --source=input.json \
  --translated=output.json
```

### Translate an HTML file and write to another file with filters

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --filters=url,html_code,html_pre
```

### Add translation context (prompt), source and target language

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

### Pass model-specific variables

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

### Choose a different prompt template

Use `--list-prompts` to check supported formats. The `experimental` group supports JSON only.

```bash
vendor/bin/bblslug \
  --model=openai:gpt-6-luna \
  --format=json \
  --source=input.json \
  --translated=out.json \
  --prompt-key=experimental
```

### Dry-run placeholders only

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

### Verbose mode (prints request preview to stderr)

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --verbose \
  --source=input.html \
  --translated=out.html
```

### Pipe STDIN → file

```bash
cat input.txt | vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --translated=out.txt
```

### Pipe STDIN → STDOUT

```bash
echo "Hello world" | vendor/bin/bblslug \
  --model=vendor:name \
  --format=text > translated.out
```


### Native structured JSON

Use `format: 'structured_json'` with an explicit JSON Schema in
`variables['response_schema']`. This mode is implemented for OpenAI Chat
Completions/Responses and Google Gemini; the selected model must support native
structured output. Unsupported providers and legacy OpenAI Completions are
rejected locally. Provider schema restrictions and access rules still apply.

```php
$translation = \Bblslug\Bblslug::translate(
    apiKey: getenv('BBLSLUG_OPENAI_API_KEY'),
    format: 'structured_json',
    modelKey: 'openai:gpt-5-mini',
    text: '{"message":"Hello"}',
    targetLang: 'DE',
    variables: ['response_schema' => [
        'type' => 'object',
        'properties' => ['message' => ['type' => 'string']],
        'required' => ['message'],
        'additionalProperties' => false,
    ]]
);
```

The CLI reads the schema from a JSON file:

```bash
bblslug --model=openai:gpt-5-mini --format=structured_json \
  --response-schema=translation.schema.json --source=input.json --target-lang=DE
```

Input and output are JSON **strings**, not PHP arrays. Native mode sends the
schema without rewriting it and reads the JSON response without translation
markers. OpenAI uses strict schema mode by default; PHP callers may override
`strict` with a boolean and `response_schema_name` with a name of 1–64 letters,
digits, underscores, or dashes. A schema supplied with `format: 'json'` also enables
native mode. The library still validates returned JSON syntax and source structure;
it does not independently implement every JSON Schema constraint.

`linedJson` selects an alternative JSON translation prompt with the same container
validation as `json`. It processes one JSON document, not JSON Lines, and does not
silently repair malformed output. Neither new mode assumes particular field names
or a document domain. Use the existing context or a custom prompt for application
rules. Custom prompt templates must provide the requested format; native mode
requests the `structured_json` template.

### Live provider checks

Small English-to-German requests checked on 2026-10-23 through the library with
validation enabled, HTML URL/code filters, and opt-in embedded JSON validation:

| Model | Text | HTML | JSON |
| --- | --- | --- | --- |
| `deepl:free` | Passed | Passed: protected block and inline code | Passed through native Document API |
| `deepl:pro` | Passed | Passed: protected block and inline code | Passed through native Document API |
| `yandex:gpt-lite` | Passed | One response added an opening Markdown fence; now rejected | Passed |
| `yandex:gpt-pro` | Passed | Passed | Passed |

These are individual small scenarios, not a reliability or translation-quality
benchmark. Native DeepL JSON document requests passed the path-quote scenario on
both API tiers, including a string-encoded JSON object. This does not guarantee
semantic preservation of arbitrary string data. Container and placeholder
validation remain enabled by default.

### Secret masking in diagnostics

The facade masks the API key in HTTP diagnostic logs and provider exceptions.
Low-level `HttpClient::request()` callers supply raw secrets through
`maskPatterns`. Masking covers raw text, URL/form encoding, and up to two layers
of JSON string escaping, including hexadecimal escape case variations. Empty
patterns are ignored; overlapping secrets are matched longest first.

Request and response diagnostic copies are sanitized. Sent payloads, returned
response bodies and headers keep their original data. Network exceptions retain
the cURL error code and redact known secrets from the message.

### DeepL JSON transport

For `json` and `linedJson`, DeepL Free and Pro use the native
[Document API](https://developers.deepl.com/api-reference/document/upload-and-translate-a-document):
upload the prepared JSON as a multipart `.json` file, poll its status, then download
the translated JSON. The result goes through the existing placeholder restoration
and JSON syntax, structure, exact non-string value, and optional embedded JSON checks.

The source file limit is 1 MB; the facade also enforces the model registry's
existing character limit. `variables: ['document_timeout' => 120]` sets a
positive integer timeout in seconds: the upload has this HTTP timeout, and polling
plus downloading share a separate deadline of that duration. Polling ignores the
provider's estimated remaining time. An HTTP error, processing error, malformed
status, or deadline expiry stops the operation. Uploads and downloads are not
retried automatically. Document IDs and encryption keys are masked in debug logs
and exceptions; `rawResponseBody` contains the downloaded document.

The Document API has no `context` or `model_type` parameter. These options cause
an explicit error in native JSON mode. To retain text-endpoint options or the
previous local reconstruction behavior, select
`variables: ['json_transport' => 'text_batch']` explicitly. No automatic fallback
occurs after an unsuccessful document request. Text and HTML retain their existing
transport.

In `text_batch` mode, decoded nonempty string values are sent as an
[ordered text batch](https://developers.deepl.com/api-reference/translate/request-translation).
Keys, container whitespace, and exact non-string tokens remain local; returned
strings are JSON-encoded before insertion. Encoded JSON objects/arrays are processed
recursively. This mode has a 128 KiB request-body limit and can change punctuation
inside technical strings, as observed with ASCII quotes in a path.

Both modes finish locally (`httpStatus` is `0`) when no translatable string values
remain. Empty strings, whitespace-only values, and placeholder-only values are
not translated.

Low-level integrations must forward each request's `responseContext` to
`parseResponse()`. For native JSON, they must also execute its optional `workflow`
continuation after the upload, using authenticated HTTP with the supplied timeout
and mask patterns, then parse the downloaded response. The optional `localResult`
means no HTTP exchange is needed. `Bblslug::translate()` handles this automatically.
The driver keeps no shared current-document state.

### DeepL HTML placeholders

For HTML requests, markers in normal text are temporarily wrapped in spans using
[DeepL's `translate="no"` support](https://developers.deepl.com/docs/translate/translating-html).
Only wrappers created for that request are removed before restoring the original
fragments. User markup is not reserialized. Markers in attributes, comments, CDATA,
and raw-text/RCDATA elements such as `script`, `style`, `title`, and `textarea`
are left without these wrappers.

A missing, duplicated, malformed, or modified transport wrapper causes an error.
Post-validation still compares the restored HTML tree, so the wrappers do not
bypass structure checks. The 128 KiB request-body limit is checked
after wrapping so transport overhead cannot silently exceed the provider limit.

### HTML filters inside JSON

When a filter such as `html_code` or `html_pre` receives valid JSON, it inspects
string values separately and recursively handles JSON containers/strings encoded
inside them. Escaped closing tags (`<\/code>`), quoted attributes, and Unicode
escapes are decoded for matching; protected fragments are restored from exact
source byte slices. JSON keys, numeric spellings, whitespace, and unrelated escape
sequences are not reserialized by the filter. Tag pairs in separate JSON values
cannot form one protected block. This behavior applies across providers.

For ordinary HTML/text input, the same filters continue to use the HTML token
scan. Only complete explicit tag pairs are protected; an unclosed element is
left unchanged. Filters do not repair malformed JSON or markup.

### Disable validation

For HTML and JSON formats, Bblslug performs basic syntax validation before and after translation.

JSON validation also checks the returned structure against the input. Missing
fields or list elements, including `null` values, cause a schema error. Omitted
values are not automatically restored.
Objects and arrays must retain their container types, including empty `{}` and `[]`.
Object property order may change, including in nested objects; list positions remain
significant. Non-string values are compared at their original property/index.
The provider's output is returned without reordering its properties.
Numbers, booleans, and `null` must retain their original JSON tokens; only string
values may be translated. Number comparison uses the exact JSON spelling to avoid
rounding large integers or precise decimals. Equivalent rewrites such as `1.0`
to `1e0` also cause a `JSON scalar values changed` error. This check is disabled
along with container validation by `--no-validate` or `validate: false`.

With placeholder filters enabled, translated output must preserve each prepared
marker's occurrence count. Missing, duplicated, or unexpected markers cause a
`Placeholder mismatch` error before restoration. Marker order may change. This
integrity check remains enabled with `--no-validate` or `validate: false`.

HTML validation also compares the parsed source and translated trees: tag order,
nesting, attribute presence, comments, doctype, and non-translatable attribute
values must match. Text and the values of `title`, `alt`, and `aria-label` may
change. Attribute order and equivalent entity spellings are ignored. The result
is returned unchanged; comparison does not reserialize the HTML. Newly added
Markdown fences at document boundaries are rejected even when libxml parses them
as ordinary text. Literal fences already present at those boundaries are allowed.

Current limits: HTML comparison uses libxml's parsed tree, including its HTML
normalization, and does not verify translated text content. Use placeholder
filters to protect code or other text that must remain unchanged.

JSON encoded inside string values is checked only when explicitly enabled with
`validateEmbeddedJson: true` in the PHP API or `--validate-embedded-json` in the CLI.
This option supports `json`, `linedJson`, and `structured_json`. It detects source
strings containing valid JSON objects or arrays, then recursively checks their
translated syntax, structure, and exact non-string values using the same rules as
the outer JSON. Object key order may change. Errors report `Embedded JSON mismatch`
and the affected field or index. Output strings are not repaired or reserialized.
Ordinary strings, malformed JSON-like source text, and encoded JSON scalars such
as `123`, `true`, or `null` remain translatable. The option is off by default because
JSON-looking text can be intentional prose or a code example. `validate: false`
or `--no-validate` also disables these checks.

To skip validation, add:

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --no-validate \
  --source=input.html \
  --translated=out.html
```

### Statistics

- **Usage metrics**

  After each translation (when not in dry-run), Bblslug prints to stderr a summary of consumed usage metrics, for example:

  ```
  Usage metrics:
      Tokens:
          Total:       1074
          -----------------
          Prompt:      631
          Completion:  443
  ```

## PHP Library Usage

You can embed Bblslug in your PHP project.

### Quickstart

1. **Install:**

  ```bash
  composer require habr/bblslug
  ```

2. **Require & Import:**

  ```php
  require 'vendor/autoload.php';
  use Bblslug\Bblslug;
  ```

### Translate

Text translation function example:

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
    format:   'html',                          // 'text', 'html' or 'json'
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

Returns an array like:

```php
[
  'deepl'  => ['deepl:free' => […], 'deepl:pro' => […]],
  'openai' => ['openai:gpt-4' => […], …],
  …
]
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

Returns an array like:

```php
[
  'translator' => [
      'formats' => ['text', 'html', 'json', 'structured_json', 'linedJson'],
      'notes'   => 'professional translator template',
  ],
  'experimental' => [
      'formats' => ['json'],
      'notes'   => 'Compact experimental prompt based on `translator`',
  ],
  // …
]
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



## Samples

### Local live smoke checks

Run the live suite with an exact `BBLSLUG_LIVE_MODEL`, or `all` for the first model
of each vendor with credentials. Use `deepl:pro` to check the Pro tier separately.
Credentials use the `BBLSLUG_` prefix first, with unprefixed names as a fallback.
Yandex also requires `BBLSLUG_YANDEX_FOLDER_ID` or `YANDEX_FOLDER_ID`.
The JSON sample includes a JSON-encoded string with backslashes and quotes.
These checks call paid APIs and verify basic translation and container invariants;
they are not a translation-quality benchmark.

Locally, with the corresponding environment variables configured:

```bash
RUN_LIVE_TESTS=1 BBLSLUG_LIVE_MODEL=openai:gpt-6-luna \
  vendor/bin/phpunit --testsuite integration --testdox
```

### Input samples

You can find sample input files under the `samples/` directory.

## License

This project is licensed under the MIT License – see the [LICENSE](LICENSE) file for details.
