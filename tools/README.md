# Development tools

## Offline documentation checks

From a repository checkout with Composer dependencies installed:

```bash
php tools/model-catalog.php --check
php tools/check-docs.php
```

The catalogue check compares the generated model page with the registry and fails when a recorded retirement date has arrived, requiring a source review rather than an automatic model substitution. Regenerate it with
`php tools/model-catalog.php` after updating model metadata and lifecycle sources.
The example checker uses temporary files, fake credentials and dry-run preparation for documented
CLI and PHP calls, including rendered CLI help. It does not verify provider availability, translation quality,
checkpoint persistence or successful real HTTP requests. Explicit live-suite and installation commands are skipped.

## Live provider checks

`tools/live-smoke.php` makes real API requests through both the library and CLI. Requests may cost money and consume provider quota. Run it from a development checkout with Composer dependencies and provider credentials configured in the environment.

```bash
php tools/live-smoke.php
```

By default, it checks the configured model list with `text`, `html`, and `json` through both `api` and `cli`. Set these environment variables to select cases:

| Variable | Default | Meaning |
| --- | --- | --- |
| `BBLSLUG_SMOKE_MODELS` | Eight models configured in the script | Comma-separated `vendor:model` keys |
| `BBLSLUG_SMOKE_FORMATS` | `text,html,json` | Comma-separated formats supported by the runner |
| `BBLSLUG_SMOKE_ENTRIES` | `api,cli` | Either or both entry points |
| `BBLSLUG_SMOKE_PROMPT_KEY` | `translator` | Prompt group used for both entry points |
| `BBLSLUG_SMOKE_GOOGLE_JSON_TRANSPORT` | `auto` | Google JSON only: `auto`, `markers`, or `native` |
| `BBLSLUG_SMOKE_REPORT_DIR` | system temp directory plus `/bblslug-live-smoke` | Directory for sanitized reports |

For example, choose explicit models, formats, and entry points (the chosen model keys must exist in the registry):

```bash
BBLSLUG_SMOKE_MODELS=openai:gpt-6-luna,google:gemini-flash-latest \
BBLSLUG_SMOKE_FORMATS=json BBLSLUG_SMOKE_ENTRIES=api \
php tools/live-smoke.php
```

Credential lookup prefers each non-empty `BBLSLUG_<NAME>` variable, then its unprefixed equivalent. Yandex also requires `YANDEX_FOLDER_ID` (or `BBLSLUG_YANDEX_FOLDER_ID`). Unless explicitly set through `BBLSLUG_HTTP_TIMEOUT` and `BBLSLUG_HTTP_CONNECT_TIMEOUT`, the small suite uses 45 seconds total and 10 seconds to connect. A provider authentication, billing, quota, or missing-model error skips further cases for that model; other failures are recorded and the run continues. The exit status is nonzero if any case fails or is skipped.

The report directory is created with mode `0700`. `summary.json` is updated as cases finish; text reports contain synthetic output and diagnostics, with configured credentials and folder IDs masked. Keep reports local. Use a new report directory for each run when you need to retain separate results.

The checks cover nonempty translated output, JSON decoding and selected invariants, protected content, and diagnostic key masking. They are small synthetic English-to-German requests, not a translation-quality benchmark. This runner does not cover every prompt, format, model, or provider failure mode.

## Segment acceptance suite

Select `BBLSLUG_SMOKE_SUITE=segments` to check large text, HTML and selected HTML inside JSON,
plus Russian-to-English abbreviation boundaries. The default `small` suite above remains unchanged.
The segment suite requires exactly one explicit mode: `BBLSLUG_SMOKE_OFFLINE=1` or
`BBLSLUG_SMOKE_LIVE=1`. Offline mode is rejected by the small suite.

The offline mode uses a controlled HTTP client in child processes, requires no credentials,
and runs in CI. It exercises the real library and CLI checkpoint workflows:

```bash
BBLSLUG_SMOKE_SUITE=segments BBLSLUG_SMOKE_OFFLINE=1 php tools/live-smoke.php
```

The live mode makes paid requests. Start with a limited selection:

```bash
BBLSLUG_SMOKE_SUITE=segments BBLSLUG_SMOKE_LIVE=1 \
BBLSLUG_SMOKE_MODELS=google:gemini-flash-latest \
BBLSLUG_SMOKE_CASES=large BBLSLUG_SMOKE_FORMATS=text \
php tools/live-smoke.php
```

Each case starts through PHP, terminates that process after one saved packet, resumes through the CLI,
and reads the completed checkpoint through PHP and CLI without HTTP. Checks include packet limits,
all repeated technical markers and the final marker, selected abbreviation facts, protected HTML,
text line separators and whitespace, unselected JSON source bytes, and current-run versus total usage.
Changed source, byte cap, abbreviation hints and damaged record checksums must fail before HTTP
and preserve the saved prefix and destination. Offline mode also injects an HTTP 402 failure before resuming.
These authored fixture checks do not establish general translation quality or semantic completeness.

| Variable | Segment-suite default | Meaning |
| --- | --- | --- |
| `BBLSLUG_SMOKE_CASES` | `large,abbreviations` | Comma-separated fixture names |
| `BBLSLUG_SMOKE_FORMATS` | `text,html,json` | Selected formats |
| `BBLSLUG_SMOKE_MODELS` | Eight models in live mode; Google Flash alias offline | Exact registered keys; offline supports only its controlled Google client |
| `BBLSLUG_SMOKE_ENTRIES` | `api,cli` | Both are required for this composite check |
| `BBLSLUG_SMOKE_PROMPT_KEY` | `translator` | Only the standard translator prompt is supported |
| `BBLSLUG_SMOKE_REPORT_DIR` | A unique directory under system temp | Must be new or empty; newly created directories use mode `0700` |
| `BBLSLUG_SMOKE_OPERATION_TIMEOUT` | `360` | Per-operation seconds, from 1 to 3600 |
| `BBLSLUG_HTTP_TIMEOUT` | `120` | Per-request seconds, unless explicitly supplied |
| `BBLSLUG_HTTP_CONNECT_TIMEOUT` | `10` | Connection timeout seconds |
| `BBLSLUG_SMOKE_VARIABLES` | `{}` | JSON object mapping model keys to explicit string-valued request settings |

Default output budgets are 16384 for Google and 8192 for other LLMs; DeepL document timeout is 120 seconds.
Overrides accept `maxTokens`, `max_completion_tokens`, `maxOutputTokens`, `reasoning_effort`, `effort`,
`json_transport`, `document_timeout`, and `temperature`. Provider support still applies; credentials
and Yandex folder IDs stay in the environment. The small-suite Google transport switch does not apply here;
use a `json_transport` override instead.

For example, an explicit OpenAI profile can disable reasoning rather than use the model's default:

```bash
BBLSLUG_SMOKE_SUITE=segments BBLSLUG_SMOKE_LIVE=1 \
BBLSLUG_SMOKE_MODELS=openai:gpt-6-luna BBLSLUG_SMOKE_CASES=large BBLSLUG_SMOKE_FORMATS=html \
BBLSLUG_SMOKE_VARIABLES='{"openai:gpt-6-luna":{"reasoning_effort":"none"}}' \
php tools/live-smoke.php
```

The suite never retries provider requests or silently changes a model or reasoning setting.
A reasoning model can exhaust its output budget before producing a translation; that is a failed case.
The report records the selected request settings, source and packet plans, sanitized child diagnostics,
per-case results and source-code hashes. `summary.json` is updated after each case. A missing credential,
a skipped case or any failed assertion gives a nonzero exit status. Keep report directories local.

## PHPUnit live integration suite

The PHPUnit `integration` suite makes paid requests only when `RUN_LIVE_TESTS` is exactly `1`. By default it chooses the first registry model for each vendor that has credentials. Set `BBLSLUG_LIVE_MODEL` to select one exact model:

```bash
RUN_LIVE_TESTS=1 BBLSLUG_LIVE_MODEL=google:gemini-flash-latest \
  vendor/bin/phpunit --testsuite integration --testdox
```

Credentials use the same `BBLSLUG_`-first lookup and unprefixed fallback; Yandex needs its folder ID. These tests check basic translation and container invariants. They do not measure translation quality.
