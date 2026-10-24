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

### Detect missing fragments in plain text

Use `--format=text --text-transport=segments` (PHP: `textTransport: 'segments'`)
to translate each nonempty source line through the provider's JSON transport.
Bblslug requires exactly one nonempty string for every fragment identifier and
reassembles the text in source order. Missing, extra, duplicate or invalid values
fail even with `--no-validate`. Returned fragments may not introduce CR or LF.
Lines containing protected placeholders are split around those placeholders;
their source bytes remain local.

```bash
vendor/bin/bblslug --model=google:gemini-2.5-flash --format=text \
  --text-transport=segments --source=input.txt --translated=output.txt
```

Line separators (LF, CRLF and CR), blank lines, and leading/trailing Unicode
whitespace stay unchanged. Text remains literal: HTML entities and markup are not
decoded or escaped. Existing filters still apply and restore locally. Dry-run
returns prepared plain text; whitespace-only or placeholder-only input needs no
HTTP request. Segment mode requires valid UTF-8 and rejects `response_schema`.

The default is still `textTransport: 'document'` / `--text-transport=document`.
Document mode does not detect omitted paragraphs. Segment mode detects missing
fragment identifiers, but cannot prove that every sentence within a returned
fragment was translated, or that the translation is accurate. Translating lines
separately may reduce fluency across line boundaries. Custom prompt groups need
a JSON template. Requests are not split into batches in this text mode; JSON
payload overhead counts toward model limits, and output budgets must fit the
whole translation. `--html-segment-batch-size` applies only to HTML segments.
There is no automatic retry or fallback to document mode.

A 2026-10-24 live check translated a 19,978-character text through Gemini 2.5
Flash with original CR/LF separators preserved and an explicitly increased output
budget. Yandex Lite omitted `s108` on the same input; segment mode rejected the
response. A small Yandex Pro request also passed. These checks verify the segment
protocol and reconstruction, not translation fidelity.

### Keep HTML markup local during translation

The optional `segments` transport sends text fragments as a JSON object with
stable identifiers. Bblslug reconstructs the HTML from the original source slices;
the provider does not rewrite its tags or non-language attributes.

```bash
vendor/bin/bblslug --model=google:gemini-2.5-flash --format=html \
  --html-transport=segments --filters=url,html_code,html_pre \
  --source=input.html --translated=out.html
```

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'html',
    modelKey: 'google:gemini-2.5-flash',
    text: $html,
    filters: ['url', 'html_code', 'html_pre'],
    htmlTransport: 'segments',
);
```

Text nodes and `title`, `alt`, and `aria-label` attribute values are translated.
`pre`, `code`, scripts, styles, legacy raw-text elements, SVG/MathML, comments,
and declarations remain local. `plaintext` protects the remainder of the document.
`title` and `textarea` contents are handled as text, including literal less-than
signs. Placeholder markers remain at their source positions and are not sent.
Original whitespace at fragment edges is retained. Entities are decoded for
translation; changed values are escaped for their HTML context, including unquoted
attributes. An unchanged value keeps its original entity spelling.
CSS visibility and browser scripting state are not evaluated.

The response must contain exactly the expected identifiers, without duplicates,
and a nonempty string for every fragment. These transport checks remain active
with `--no-validate`; they cannot restore an incomplete response safely. Output is
still subject to the normal HTML checks unless validation is disabled. An input
with no translatable fragments skips HTTP. CLI dry-run validates extraction but
continues to return prepared HTML, not the internal JSON payload.

The default remains `htmlTransport: 'document'` / `--html-transport=document`.
`segments` supports HTML input and selected HTML strings in JSON (below). It uses
the selected prompt group's JSON template and the provider's JSON transport. A custom HTML-only prompt group needs
a JSON template for this mode. DeepL uses its JSON Document API by default;
`json_transport=text_batch` remains an explicit alternative with its existing
batch limits. Length checks apply to the serialized JSON payload; returned `prepared` text
and length statistics still describe HTML. Requests remain unsplit unless explicit
batching is enabled below. There is no automatic retry or fallback to document mode.

Fragment translation can reduce fluency where grammar requires moving words
across inline tags. Keeping every fragment does not prove translation fidelity.
Extraction is conservative: incomplete markup and unsupported protected-block
forms fail before HTTP. SVG/MathML blocks, including self-closing roots, stay
local and are restored byte for byte.

A 2026-10-23 check on a 26,087-character HTML document passed with Gemini 2.5
Flash, GPT-5 Mini, Claude Haiku 4.5, and DeepL free/pro. The same document passed
through Gemini CLI. Output budgets were explicitly increased for this large
input. Grok 4.20 non-reasoning and Yandex Lite/Pro responses failed segment
protocol checks and were rejected without retries. This single-document check
is not a guarantee for all models or documents.

### Translate selected HTML strings inside JSON

Use `htmlTransport: 'segments'` with explicit `htmlJsonPointers` to translate
only selected HTML string values. Supported formats are `json` and `linedJson`.

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'google:gemini-2.5-flash',
    text: $json,
    filters: ['url', 'html_code'],
    htmlTransport: 'segments',
    htmlJsonPointers: ['/body', '/items/0/html'],
);
```

```bash
vendor/bin/bblslug --model=google:gemini-2.5-flash --format=json \
  --html-transport=segments --html-json-pointer=/body \
  --html-json-pointer=/items/0/html --filters=url,html_code \
  --source=input.json --translated=out.json
```

Only HTML fragments from those strings are sent. All unselected values, keys,
whitespace, property order, and numeric tokens remain byte-for-byte unchanged.
Filters apply separately inside selected strings, leaving unselected fields alone.
Each HTML string has its own scope, including raw-text/`plaintext` handling.
Fragment identifiers are unique across the request. Reconstruction escapes each
changed HTML string as JSON; an unchanged string retains its original JSON token.
A changed selected token may normalize its JSON escape spelling, while its decoded
markup stays local. `rawResponseBody` contains the provider's fragment response;
`result` contains the assembled JSON document.

Selectors use RFC 6901 JSON Pointer syntax: `~1` for `/`, `~0` for `~`, array
indices such as `/items/0/html`, and an empty pointer for a root JSON string
(`--html-json-pointer ''`). Repeated selectors are deduplicated. There are no
wildcards, field-name guesses, or implicit traversal into JSON encoded inside
another string. Invalid/missing/non-string selections, duplicate source object
keys, and malformed source JSON fail before HTTP, including dry-run and
`--no-validate`. Segment response integrity is also mandatory. When validation is
enabled, selected HTML is checked before and after translation automatically.

`htmlJsonPointers` / `--html-json-pointer` selects content to **translate**.
`validateHtmlJsonPointers` / `--validate-html-json-pointer` only selects additional
HTML strings to **check**. Both can be used together. JSON segment mode requires
at least one translation selector; document mode rejects translation selectors.
`structured_json` and `response_schema` are incompatible with segment mode because
the provider sees a fragment object rather than the outer JSON schema.

The earlier HTML transport limitations still apply. CLI JSON dry-run outputs
filtered JSON with only the selected strings prepared; the PHP API exposes this in
`prepared` while its dry-run `result` remains the source. Transport input limits apply to the
serialized fragment payload, not to unselected metadata. This mode does not change
the default JSON translation behavior, which translates all string values.

A large JSON fixture produced the same 179-fragment request as its standalone
HTML value. Replaying five recorded provider responses preserved its unselected
bytes and HTML structure. Fresh Gemini 2.5 Flash API and CLI checks also passed,
with `maxOutputTokens=32768`; protected URLs/code and unselected JSON bytes were
unchanged. This verifies reconstruction for that fixture, not general translation
fidelity or a new live check of every provider.

### Limit HTML segment batches

Set `htmlSegmentBatchSize: 25` in the PHP API or add
`--html-segment-batch-size=25` in the CLI to send at most 25 fragments per request.
The option requires `htmlTransport: 'segments'` / `--html-transport=segments` and
works for HTML and selected HTML strings inside `json`/`linedJson`.

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'xai:grok-4.20-0309-non-reasoning',
    text: $json,
    htmlTransport: 'segments',
    htmlJsonPointers: ['/body'],
    htmlSegmentBatchSize: 25,
);
```

```bash
vendor/bin/bblslug --model=xai:grok-4.20-0309-non-reasoning --format=html \
  --html-transport=segments --html-segment-batch-size=25 \
  --source=input.html --translated=out.html
```

The default is `null` (no batching). Packets run sequentially, preserving the
original identifiers and source positions. Every response is checked against its
own packet before continuing, even with validation disabled. Missing, duplicate,
extra, empty, or non-string values abort the operation. A packet failure identifies
its position and the number of earlier completed packets; no further packets are
sent, no partial document is returned, and the CLI keeps existing files unchanged.
There are no automatic retries or fallback requests. A successful earlier packet
is a completed provider operation even when a later packet fails.

Model input-length checks and request-body preparation run for **all** packets
before the first HTTP call. A single oversized fragment fails; it is never clipped
or silently divided. The limit counts fragments, not characters or bytes. Smaller
packets also reduce context across inline text nodes, so translation fidelity still
requires review. Source HTML and unselected JSON remain local as before.

Batching explicitly makes multiple translation requests. Existing output-token
settings and timeouts apply **per packet**, not to the complete document. Prompts
and context are repeated. DeepL's default JSON transport starts a separate document
workflow per packet; its explicit `json_transport=text_batch` alternative retains
its own endpoint limits. Authentication, proxy, language, and model options are
reused for each request.

With a batch size configured, the result adds `batches`: ordered summaries with
`index` (1-based), `segments`, `httpStatus`, and normalized `consumed` usage.
Top-level `consumed` sums the metrics actually reported across packets; absent
metrics are not estimated. `httpStatus` describes the last packet.
`rawResponseBody` becomes a JSON array of raw response-body **strings**, one per
packet, rather than a fabricated provider response. Verbose diagnostics identify
packets. API dry-run records planned packets with status 0 and sends no HTTP;
CLI dry-run still returns prepared HTML/JSON. Inputs with no translatable text
make no requests and return an empty `batches` list. Without the option, the
existing single-response result shape remains unchanged.

A 2026-10-23 large-document check used 179 fragments and a limit of 25. It did
not produce a complete large-document translation:

| Configuration | Observed result |
| --- | --- |
| Grok 4.20 non-reasoning | Six packets passed; packet 7/8 timed out after 240 seconds. |
| Yandex Lite | Packet 1/8 returned invalid JSON with an opening Markdown fence. |
| Yandex Pro | Packet 1 passed; packet 2/8 added paired Markdown fences around JSON. |

No packets were retried. Offline inspection also found a JSON syntax error in
Lite's response after removing its transport fence; Pro's enclosed 25 values
passed strict checks in that offline inspection. Fence removal is **not** part of
this batching feature. A separate small JSON CLI example passed on Grok: five
fragments in three packets, with selected HTML, code, and unselected JSON preserved.
These results establish the batching/error-handling behavior, not general provider
reliability on large documents.

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

The opt-in `experimental_xml` group preserves the XML-style JSON prompt prototype:

```bash
vendor/bin/bblslug --model=openai:gpt-5-mini --format=json \
  --source=input.json --target-lang=DE --prompt-key=experimental_xml
```

PHP callers select `promptKey: 'experimental_xml'`. The template is sent verbatim
as text with XML-like tags. The library substitutes its placeholders; it does not
parse XML, expand entities, or run a [POML renderer](https://microsoft.github.io/poml/latest/language/basic/).
Its marker instructions and JSON escape examples were clarified during review.
`translator` remains the default. This experimental group provides no `text`,
`html`, `linedJson`, or `structured_json` template; native JSON mode requests
`structured_json` and therefore cannot use this group.

A small 2026-10-23 comparison used the same English-to-German JSON sample with
encoded JSON, protected code/URL, and non-string values. Six model/tier entries
(OpenAI GPT-5 mini, Claude Haiku 4.5, Gemini 2.5 Flash, Grok 4.20, Yandex Lite/Pro)
ran once through API and once through CLI for each prompt, with validation enabled:

| Prompt | Passed / requests | Observed rejection |
| --- | --- | --- |
| Default `translator` | 12 / 12 | None in this run |
| Original local XML prototype | 10 / 12 | Yandex Pro API and CLI added Markdown fences |
| Reviewed `experimental_xml` | 12 / 12 | None in this run |

The original prototype also contained contradictory bare-JSON/marker instructions.
The comparison does not isolate which wording caused a model response. It is not
a quality benchmark or reliability estimate and provides no evidence that XML
markup improves on the default. Earlier intermittent Gemini marker failures remain
relevant. DeepL was excluded because its translation API does not use these prompts.
The unmodified local prototype is preserved in the local review backup; it is not
published as another supported template.

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

### Google native JSON transport

Google JSON requests default to `json_transport=auto`. For registered Gemini models
with a declared native JSON capability, `json` and `linedJson` with the standard
`translator` group request `application/json` output without translation markers
or a required JSON Schema. The registry declares this capability per model; the
driver does not infer it from names or endpoints. The current Gemini entries have
it enabled; Gemma and undeclared custom configurations retain markers.

```bash
printf '%s' '{"title":"Hello"}' | bblslug --model=google:gemini-2.5-flash \
  --format=json --source-lang=EN --target-lang=DE
```

The driver uses the same automatic selection to build requests and parse responses.
Text and default document-mode HTML retain markers. HTML segment mode below
uses the JSON transport instead. Custom prompt groups retain their existing marker
contract in auto mode; a custom `translator` group without `structured_json` also
retains markers. To select a protocol explicitly, pass
`variables: ['json_transport' => 'native']` or `['json_transport' => 'markers']`,
or CLI `--variables=json_transport=markers`. A failed HTTP request or invalid
response never triggers an automatic protocol retry.

Explicit native mode requests the selected group's `structured_json` template;
missing templates fail explicitly. `structured_json` format still requires
`response_schema`, and supplying a schema selects native output even in auto mode.
Explicit markers conflict with a schema. Syntax, source structure, exact non-string
values, placeholders, and opt-in embedded JSON validation remain enabled by default.
Native JSON syntax does not guarantee semantic correctness of translated strings.
See the provider's [JSON output documentation](https://ai.google.dev/api/generate-content#v1beta.GenerationConfig).
Google transport choices are separate from DeepL's transport choices.

A 2026-10-23 auto-mode check sent JSON through API and CLI on Gemini 2.5 Flash,
2.5 Pro, 2.5 Flash-Lite, 3.8 Flash, and 4 Argon. Nine of ten requests passed the
nested JSON/code/URL/scalar checks; 4 Argon's CLI request timed out at the smoke
runner's 45-second deadline. One separately recorded CLI check with the library's
normal 120-second timeout passed in 3.49 seconds. This does not establish a cause
for the transient timeout. Four additional 2.5 Flash text/HTML API/CLI requests
passed with their existing marker protocol.

### Live provider checks

English-to-German smoke requests on 2026-10-23 used both the library and CLI,
with validation enabled, HTML/JSON URL and code filters, and embedded JSON validation.
Text cases ran on `61610e4`; HTML/JSON cases were repeated with the DeepL CLI timeout
and prompt framing fixes described in the unreleased changelog.

| Model | Text (API / CLI) | HTML (API / CLI) | JSON (API / CLI) |
| --- | --- | --- | --- |
| `openai:gpt-5-mini` | Passed / Passed | Passed / Passed | Passed / Passed |
| `anthropic:claude-haiku-4.5-20251001` | Passed / Passed | Passed / Passed | Passed / Passed |
| `google:gemini-2.5-flash` | Passed / Passed | Passed / Passed | Passed / Rejected: missing markers |
| `xai:grok-4.20-0309-non-reasoning` | Passed / Passed | Passed / Passed | Passed / Passed |
| `deepl:free` | Passed / Passed | Passed / Passed | Passed / Passed (native Document API) |
| `deepl:pro` | Passed / Passed | Passed / Passed | Passed / Passed (native Document API) |
| `yandex:gpt-lite` | Passed / Passed | Rejected markup / Rejected markup | Passed / Passed |
| `yandex:gpt-pro` | Passed / Passed | Passed / Passed | Connection timeout / Passed |

The table records the marker-based Google protocol. Those observations cover 48
cases: 44 passed, three responses were rejected by output validation, and one
connection timed out. The earlier Gemini API JSON case also missed markers, so its
later success does not establish reliability. No invalid response was accepted
and no automatic retry was added.

A separate Google native JSON check passed all six requests: API and CLI, three
runs each, on `gemini-2.5-flash`. The sample included encoded JSON, protected code,
a Unicode-escaped URL, and exact non-string values. This is evidence for the tested
native path, not a guarantee across all Gemini models. A separate Yandex Lite HTML
request again added an opening Markdown fence inside the markers and was rejected.

These are small scenarios using eight model/tier entries across six providers,
not a full model-catalogue check or a translation-quality benchmark. Native DeepL
JSON requests preserved the tested path and encoded JSON on both tiers; this does
not guarantee semantic preservation of arbitrary strings. See
[the repeatable smoke runner](tools/README.md) for commands and scope.

Yandex Lite text/JSON also passed four API/CLI smoke requests after the completion
status checks were added. That earlier run predates the source-aware HTML prefix
handling described below.

### Yandex completion status

The driver checks completion metadata before extracting text, even when translation
markers are present. Foundation API alternatives must have
`ALTERNATIVE_STATUS_FINAL`; Chat Completions and legacy completion responses must
have `finish_reason=stop`, when that field is supplied. Truncated, partial,
content-filtered, tool-call, unknown, and malformed completion states are rejected.
An explicit Chat Completions refusal is also rejected. Only the first alternative
is considered; another alternative does not replace a rejected one.

Older response shapes without completion metadata remain accepted for compatibility;
the selected framing and enabled output validation still apply. CLI provider failures leave the
source and an existing destination file unchanged. No automatic retry is performed.
The documented Yandex API format controls target JSON; this change does not add an
HTML output mode or strip Markdown wrappers from a model response.

### Yandex native JSON transport

Yandex supports JSON-object generation through
[`jsonObject` in the Foundation API](https://aistudio.yandex.ru/ru/docs/ai-studio/text-generation/api-ref/TextGeneration/completion)
and [`response_format: {type: json_object}` in Chat Completions](https://aistudio.yandex.ru/ru/docs/ai-studio/api/Chat-Completions/createChatCompletion).
The driver uses these controls with the existing generic `structured_json` prompt,
without translation markers. It does not strip JSON fences or repair escaping.

`json_transport=auto` is the default. It selects native mode for `json`/`linedJson`
only when the source root is an object, the model declares `native_json`, and the
standard `translator` group has a `structured_json` template. Native capability is
currently declared for `yandex:gpt-lite` and `yandex:gpt-pro`. HTML segment packets
are objects, so they qualify automatically. Text, document-mode HTML, array/scalar
JSON roots, other models, and custom prompt groups retain their marker protocol.

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'yandex:gpt-pro',
    text: $json,
    variables: ['folder_id' => $folderId, 'json_transport' => 'native'],
);
```

CLI overrides use `--variables=json_transport=native` or `json_transport=markers`.
Explicit native mode requires `json`/`linedJson` and a valid object-root source;
custom groups need a `structured_json` template. Explicit markers preserve the
previous protocol. `structured_json` and `response_schema` remain unsupported by
the Yandex integration; JSON-object mode does not enforce a caller-supplied schema.
There is no fallback or retry when a native request is rejected.

The response must be a JSON object even with container validation disabled.
Truncation, content-filter, refusal, and completion-state checks happen first.
Enabled schema/scalar checks and mandatory segment checks still reject changed,
missing, duplicated, or empty values as appropriate. Request-local state fixes the
selected framing at build time. `rawResponseBody` remains the raw provider response
(or the ordered raw-response array when batching is enabled). Chat Completions
usage is preserved from its top-level `usage` field.

Live checks on 2026-10-23 showed the limits of this change:

- Lite passed a short nested JSON probe with quotes, newlines, scalars, and a
  protected URL. Pro returned valid JSON but changed that probe's structure;
  the normal schema check rejected it.
- Pro completed the 179-fragment HTML example in eight native packets of 25.
  Its CLI JSON run also passed with automatic native selection, preserving code,
  URLs, and all unselected JSON bytes.
- Lite returned valid JSON but omitted two keys with packets of 25. With packets
  of 10, nine packets passed before the provider's content filter rejected packet
  10/18. Neither failure returned a partial document or triggered retries.

Native JSON fixes the wire-format problem; it does not guarantee completeness,
translation fidelity, or acceptance by the provider's content filter.

### Yandex HTML response prefixes

For HTML input whose prepared text contains no Markdown fence, the Yandex driver
handles the opening-only artifact observed in Lite responses. It removes exactly
one initial line containing three backticks and an optional `html` label. LF and
CRLF, whitespace around the label, and `HTML` casing are supported. No other fence
may remain in the response. The remaining HTML still passes placeholder, syntax,
and structure validation; the original HTTP response remains in `rawResponseBody`.

The source check considers backtick/tilde triples anywhere in prepared HTML,
including HTML entity representations. Protected code blocks are placeholders at
this stage and are restored unchanged afterward. If visible source fences are
present, this compatibility handling is
disabled and their content is not stripped. Added paired, trailing-only, nested,
inline, tilde, or unfamiliar labelled fences fail explicitly for fence-free input,
also when container validation is disabled. Text and JSON responses are unchanged.
This is not a Markdown parser or general repair of model output.

The facade forwards the request-local `responseContext` automatically. Low-level
driver integrations must pass that context to `parseResponse()`; without it, the
driver retains its previous parsing behavior and does not guess source formatting.
No shared mutable request state or automatic HTTP retry is used.

A 2026-10-23 live check passed all 12 requests: Lite/Pro, API/CLI, and text/HTML/JSON.
Both Lite HTML replies actually contained the opening artifact; the returned HTML
passed validation after it was removed. This is a small scenario, not a claim that
all possible provider output is accepted or correct.

### Secret masking in diagnostics

The facade masks the API key in HTTP diagnostic logs and provider exceptions.
Low-level `HttpClient::request()` callers supply raw secrets through
`maskPatterns`. Masking covers raw text, URL/form encoding, and up to two layers
of JSON string escaping, including hexadecimal escape case variations. Empty
patterns are ignored; overlapping secrets are matched longest first.

Request and response diagnostic copies are sanitized. Sent payloads, returned
response bodies and headers keep their original data. Network exceptions retain
the cURL error code and redact known secrets from the message.

### Verbose filter and validation diagnostics

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

### DeepL JSON transport

For `json` and `linedJson`, DeepL Free and Pro use the native
[Document API](https://developers.deepl.com/api-reference/document/upload-and-translate-a-document):
upload the prepared JSON as a multipart `.json` file, poll its status, then download
the translated JSON. The result goes through the existing placeholder restoration
and JSON syntax, structure, exact non-string value, and optional embedded JSON checks.

The source file limit is 1 MB; the facade also enforces the model registry's
existing character limit. `variables: ['document_timeout' => 120]` sets a
positive integer timeout in seconds: the upload has this HTTP timeout, and polling
plus downloading share a separate deadline of that duration. CLI variables may use
a canonical positive integer string, for example `--variables=document_timeout=45`.
Polling ignores the provider's estimated remaining time. An HTTP error, processing error, malformed
status, or deadline expiry stops the operation. Uploads and downloads are not
retried automatically. Document IDs and encryption keys are masked in debug logs
and exceptions; `rawResponseBody` contains the downloaded document.

The Document API has no `context` or `model_type` parameter. These options cause
an explicit error in native JSON mode. To retain text-endpoint options or the
previous local reconstruction behavior, select
`variables: ['json_transport' => 'text_batch']` explicitly. No automatic fallback
occurs after an unsuccessful document request. Text and default document-mode
HTML retain their existing transport. HTML segment mode uses the JSON transport.

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

### Filters inside JSON

When a filter such as `html_code` or `html_pre` receives valid JSON, it inspects
string values separately and recursively handles JSON containers/strings encoded
inside them. Escaped closing tags (`<\/code>`), quoted attributes, and Unicode
escapes are decoded for matching; protected fragments are restored from exact
source byte slices. JSON keys, numeric spellings, whitespace, and unrelated escape
sequences are not reserialized by the filter. Tag pairs in separate JSON values
cannot form one protected block. This behavior applies across providers.

The `url` filter also inspects decoded JSON string values recursively. It
recognizes supported URL schemes even when their letters, colons, slashes, hosts,
paths, or query strings use `\uXXXX` escapes. The original URL bytes are restored
without normalizing their encoding. URL-shaped property names stay unchanged;
separate JSON values cannot combine into one URL. Literal Unicode escape notation
in ordinary text is not decoded by the filter.

For ordinary HTML/text input, HTML filters use the same HTML token scan. Selected
non-void blocks require explicit closing tags. An unclosed selected block or an
incomplete selected opening tag stops processing before HTTP instead of sending
its contents unprotected. This also applies inside decoded JSON string values;
a closing tag in another value cannot complete the block. The error names the
selected tag without dumping its source contents.

This protection check also runs with `--no-validate`, `validate: false`, and
`--dry-run`. CLI errors go to stderr without a stack trace or partial output;
source, destination, and `.prepared` files are not written after filter failure.
Even for HTML elements with optional end tags, this block filter requires explicit
pairs; it does not infer DOM boundaries or repair source markup. A trailing slash
on a non-void tag, such as `<code/>`, does not replace its required closing tag.

Valid unpaired void elements such as `<br>`, `<img>`, and legacy `<param>` retain
their existing behavior. Selected `<plaintext>` protects everything from its
opening tag to EOF, including apparent closing tags, following the
[HTML plaintext parsing rule](https://html.spec.whatwg.org/multipage/parsing.html#plaintext-state).
For JSON, EOF here means the end of the decoded string value, not the surrounding
container. Comments, CDATA, attributes, and other raw-text elements remain opaque
to selected-tag matching. JSON-aware filtering still requires valid JSON; malformed
JSON is not repaired.

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
New Markdown fences are rejected even when libxml parses them as ordinary text.
Existing fence delimiter runs must retain their type, length, count, and order;
HTML entity equivalents compare as the same visible delimiters. Boundary fence
labels must also remain unchanged, while surrounding whitespace may vary. These
checks catch duplicated or changed existing fences as well as newly added ones.
The Yandex driver's narrowly specified opening-only transport prefix is handled
before the restored HTML reaches this validator.

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

HTML inside a JSON string is checked only at locations explicitly selected by
`validateHtmlJsonPointers` in the PHP API:

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'openai:gpt-5-mini',
    text: $json,
    validateHtmlJsonPointers: ['/body', '/items/0/html'],
);
```

In the CLI, repeat `--validate-html-json-pointer=/body` for each selected location.
These checks support `json`, `linedJson`, and `structured_json`. They validate
source HTML before sending the request, then check translated HTML syntax and
structure after placeholder restoration using the HTML rules above. Invalid,
missing, or non-string source selections fail before HTTP, including dry-run.
A changed result raises `HTML inside JSON mismatch`; the CLI leaves an existing
output file unchanged and writes no partial translation to stdout.

Paths use RFC 6901 JSON Pointer string syntax: `/items/0/html` selects an array
item, `~1` represents `/` in a key, and `~0` represents `~`. The empty pointer
selects a root JSON string (`--validate-html-json-pointer ''` in the CLI). Paths
have no wildcards or URI fragment encoding and do not implicitly decode JSON
stored inside another string. Field names are never inferred. Unselected strings
remain ordinary translatable content. `validate: false` or `--no-validate` disables
these checks. Validation does not repair HTML or establish translation completeness;
use filters to protect code and other text that must remain unchanged.

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
  'experimental_xml' => [
      'formats' => ['json'],
      'notes'   => 'Opt-in XML-style JSON prompt; sent verbatim, without POML compilation',
  ],
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
