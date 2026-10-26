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

Credential lookup prefers each non-empty `BBLSLUG_<NAME>` variable, then its unprefixed equivalent. Yandex also requires `YANDEX_FOLDER_ID` (or `BBLSLUG_YANDEX_FOLDER_ID`). The runner sets HTTP timeouts to 45 seconds total and 10 seconds to connect. A provider authentication, billing, quota, or missing-model error skips further cases for that model; other failures are recorded and the run continues. The exit status is nonzero if any case fails or is skipped.

The report directory is created with mode `0700`. `summary.json` is updated as cases finish; text reports contain synthetic output and diagnostics, with configured credentials and folder IDs masked. Keep reports local. Use a new report directory for each run when you need to retain separate results.

The checks cover nonempty translated output, JSON decoding and selected invariants, protected content, and diagnostic key masking. They are small synthetic English-to-German requests, not a translation-quality benchmark. This runner does not cover every prompt, format, model, or provider failure mode.

## PHPUnit live integration suite

The PHPUnit `integration` suite makes paid requests only when `RUN_LIVE_TESTS` is exactly `1`. By default it chooses the first registry model for each vendor that has credentials. Set `BBLSLUG_LIVE_MODEL` to select one exact model:

```bash
RUN_LIVE_TESTS=1 BBLSLUG_LIVE_MODEL=google:gemini-flash-latest \
  vendor/bin/phpunit --testsuite integration --testdox
```

Credentials use the same `BBLSLUG_`-first lookup and unprefixed fallback; Yandex needs its folder ID. These tests check basic translation and container invariants. They do not measure translation quality.
