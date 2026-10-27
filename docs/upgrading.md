# Upgrading

Read the changes between your installed version and the version you are adopting. Released compatibility notes below describe the behavior introduced by each release.

## Upgrading to 0.11.0

### Translation settings

The PHP API adds `segmentAbbreviations` as the last optional argument; existing positional arguments
keep their order. The CLI equivalent is repeatable `--segment-abbreviation=TOKEN`. Hints are opt-in
and require `segmentBatchMaxBytes` / `--segment-batch-max-bytes`.

With a byte cap, oversized segment values now split at sentence-ending whitespace where punctuation
permits, falling back to word boundaries to respect the cap. Dotted initials, leading list markers,
lowercase continuations and explicit abbreviation hints keep context where the cap permits.
This changes fragment identifiers and packet boundaries; values that already fit keep their granularity.
See [segment planning](transports.md#bound-segment-packets-by-serialized-bytes).

The standard `translator` segment transports now request required-key native schemas for the declared
Yandex Lite/Pro and Grok 4.7 models. Every current packet requires all its identifiers and nonempty string
values. Explicit `json_transport=markers` and custom prompts retain their configured protocol.
Caller-provided `response_schema` is also supported for Yandex and xAI JSON document requests;
it cannot describe segment transport payloads. See [native structured JSON](providers.md#native-structured-json).

Use a new checkpoint file after updating the library. Checkpoint identity includes library source code,
so checkpoints from 0.10.0 are rejected before HTTP even when their input and options are unchanged.
Do not edit saved identities to bypass this check. Complete pending translations with the original
library version before upgrading, or start a new translation and budget for its requests.

### Model catalogue

The default CLI model listing now focuses on undated standard-access models without announced deprecation. Use
`--list-models=all` to inspect every registered model, including snapshots, previews, deprecated entries
and models with `restricted` or `existing_customers` access. `Bblslug::listModels()`
still returns every registered key; its order is now deterministic by provider and natural model name.

The following retired keys are removed; selecting one fails locally instead of sending a doomed request:

- `google:gemini-3.1-flash-lite-preview` (shutdown 2026-05-25)
- `openai:gpt-3.5-turbo` (shutdown 2026-10-23)
- `openai:gpt-3.5-turbo-0125` (shutdown 2026-10-23)
- `openai:gpt-3.5-turbo-16k` (shutdown 2024-09-13)
- `openai:gpt-4` (shutdown 2026-10-23)
- `openai:gpt-4-0613` (shutdown 2026-10-23)
- `openai:gpt-4-1106-preview` (shutdown 2026-10-23)
- `openai:gpt-4-turbo` (shutdown 2026-10-23)
- `openai:gpt-4-turbo-2024-04-09` (shutdown 2026-10-23)
- `openai:gpt-4.1-nano` (shutdown 2026-10-23)
- `openai:gpt-4.1-nano-2025-04-14` (shutdown 2026-10-23)
- `openai:gpt-4o-2024-05-13` (shutdown 2026-10-23)
- `openai:o1` (shutdown 2026-10-23)
- `openai:o1-2024-12-17` (shutdown 2026-10-23)
- `openai:o1-pro` (shutdown 2026-10-23)
- `openai:o1-pro-2025-03-19` (shutdown 2026-10-23)
- `openai:o3-mini` (shutdown 2026-10-23)
- `openai:o3-mini-2025-01-31` (shutdown 2026-10-23)
- `openai:o4-mini` (shutdown 2026-10-23)
- `openai:o4-mini-2025-04-16` (shutdown 2026-10-23)

Served snapshots remain selectable. Do not rename a retired ID to another model under the same key;
choose an explicit replacement using the [provider lifecycle sources](models.md#lifecycle-sources-and-maintenance).
Historical live observations in the machine catalogue do not override retirement announcements.

## Upgrading to 0.10.0

Segment transports, batching, selected HTML-in-JSON, and checkpoints are opt-in. Text and HTML continue to use `document` by default. To use segments, select `textTransport=segments` or `htmlTransport=segments` (CLI: `--text-transport=segments` or `--html-transport=segments`). Configure batch sizes separately; segment identifiers are required and missing or duplicate identifiers fail the request.

For HTML strings inside JSON, explicitly select JSON Pointers with `htmlJsonPointers` or repeat `--html-json-pointer`. Recursive validation of JSON encoded in strings uses `validateEmbeddedJson` or `--validate-embedded-json`; HTML validation at selected pointers uses `validateHtmlJsonPointers` or repeat `--validate-html-json-pointer`. Batch limits are `textSegmentBatchSize`, `htmlSegmentBatchSize`, and `segmentBatchMaxBytes` (CLI: `--text-segment-batch-size`, `--html-segment-batch-size`, and `--segment-batch-max-bytes`). The operation deadline is `operationTimeoutSeconds` / `--operation-timeout`; checkpoint files use `checkpointPath` / `--checkpoint`.

Validation is stricter: JSON values and container types must be preserved, selected HTML content and structure are checked, and empty provider responses or placeholder damage fail even when optional container validation is disabled. Review workflows that relied on those outputs being accepted. `--no-validate` does not disable placeholder integrity or required segment checks.

DeepL JSON defaults to the native Document API. To keep ordered string-batch reconstruction or use text-endpoint-only options such as `context` and `model_type`, explicitly select `variables: ['json_transport' => 'text_batch']` (CLI: `--variables=json_transport=text_batch`). There is no automatic fallback after a failed document request. See [DeepL transport options](providers.md#deepl-json-transport).

Checkpoints contain translated content and usage; keep them private. A request interrupted before its confirmed result is saved may be billed again on resume. See the CLI help and library options for batch limits, deadline, and checkpoint configuration.

## Upgrading to 0.9.0

Consumers pinned to `0.8.0` must move to `0.9.0` or a later release; Packagist's immutable published reference for the withdrawn tag cannot be replaced. Update the version constraint and lock file, for example:

```bash
composer require habr/bblslug:^0.11
```

`structured_json` now requires a caller-supplied response schema and is implemented for OpenAI and Gemini. `linedJson` validates JSON output and does not repair malformed or incomplete results. Translation input remains a string.

Review custom `ModelDriverInterface` implementations: `parseResponse()` now accepts optional `array $options = []`. The callback was appended to `Bblslug::translate()`, preserving existing positional argument order. Removed, retired, or invalid model keys now fail locally; update model names instead of relying on compatibility aliases. Removed keys include `anthropic:claude-haiku-3.5`, `anthropic:claude-opus-4`, `anthropic:claude-sonnet-4`, `google:gemini-2.0-flash`, `yandex:llama`, and `yandex:llama-lite`; obsolete `xai:grok-4`, `xai:grok-3`, and `xai:grok-3-mini` redirects were also removed. Requests also use finite default connection and total timeouts (10 and 120 seconds); configure `BBLSLUG_HTTP_CONNECT_TIMEOUT` and `BBLSLUG_HTTP_TIMEOUT` when needed. Requests are not automatically retried.

Credential and model-variable lookup now prefers non-empty `BBLSLUG_`-prefixed names over unprefixed equivalents. PHP 8.2 or newer, cURL, DOM, and mbstring are required. JSON validation may reject output that omits values or changes object/array structure; it does not repair the provider response.

## Withdrawn 0.8.0

The original `0.8.0` tag included unfinished generic JSON prototypes, and its attempted republication was withdrawn because Packagist retained the original immutable source and distribution references. The completed prototypes shipped in `0.9.0`. Existing lock files and published `0.8.0` references are not updated by moving the Git tag; consumers should update their dependency to `0.9.0` or later.
