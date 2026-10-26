# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.10.0] – 2026-10-26

### Added

- Plain text `segments` transport (`textTransport` / `--text-transport`) with
  required fragment IDs and local preservation of line separators, whitespace,
  and protected placeholders. Missing, duplicate, non-string, or empty fragments
  fail independently of optional container validation.
- HTML `segments` transport (`htmlTransport` / `--html-transport`), including HTML
  strings explicitly selected by `htmlJsonPointers` / `--html-json-pointer` in
  `json` and `linedJson`. Markup and protected blocks remain local. Selected-HTML
  JSON preserves every byte outside the selected values, including numeric tokens.
- Sequential text/HTML packets using `textSegmentBatchSize`,
  `htmlSegmentBatchSize`, and the corresponding CLI options. The shared
  `segmentBatchMaxBytes` / `--segment-batch-max-bytes` limit measures serialized
  fragment JSON; whitespace splitting retains local separators. All requests
  are preflighted before the first HTTP call; an unsplittable token fails locally.
- Shared `operationTimeoutSeconds` / `--operation-timeout` deadline across
  preparation, requests, and provider workflows, including DeepL polling/download.
- Opt-in `checkpointPath` / `--checkpoint` for batched segments. Resume validates
  the source/model/settings/code fingerprint, checksums, and every cached packet
  before HTTP. Only a confirmed prefix is saved, using atomic writes and a file
  lock. Results distinguish historical usage from `consumedThisRun` and expose
  reused packets; CLI usage reflects the current run.
- Optional recursive validation of JSON objects/arrays encoded inside strings
  (`validateEmbeddedJson` / `--validate-embedded-json`). Optional HTML validation
  at explicit JSON Pointers (`validateHtmlJsonPointers` /
  `--validate-html-json-pointer`). No consumer-specific field names are inferred.
- Native Google JSON transport without a required response schema, and native
  Yandex JSON-object transport on declared-capable models. `json_transport`
  accepts `auto`, `native`, or `markers`; automatic selection respects the root,
  format, model capabilities, and prompt group. Unsupported/custom combinations
  retain markers; native response framing is checked without JSON escape repair.
- Opt-in `experimental_xml` JSON prompt prototype. The standard `translator`
  remains the default; XML markup is sent verbatim without a POML compiler.
- Bounded, masked URL/HTML filter previews through the optional
  `DebuggableFilterInterface`, and masked restored-container diagnostics.
- `tools/live-smoke.php` for paid synthetic API/CLI checks with credentials kept
  in the environment and sanitized local reports.

### Fixed

- HTML validation reads only the current parse's errors and restores the caller's
  libxml error-handling mode. Previous XML errors no longer invalidate valid HTML.
  The invalid-HTML diagnostic fixture now uses an unterminated attribute instead
  of relying on platform-dependent handling of a trailing literal `<`.
- Live PHPUnit datasets no longer contain API keys or Yandex folder IDs. Tests
  resolve credentials after setup and run only with exactly `RUN_LIVE_TESTS=1`.
  Skipped tests and failed diagnostic assertions cannot echo credential values
  through dataset or assertion formatting; controlled regressions inspect JUnit
  and console output without contacting providers.
- OpenAI/xAI reject present non-success completion reasons even when markers or
  native JSON are valid. Explicit Responses statuses must be `completed`;
  missing legacy metadata remains supported. OpenAI uses the same merged
  defaults/options protocol selection when building and parsing schema requests.
- DeepL checks the serialized 128 KiB body limit for every text, HTML, and
  `text_batch` request, including unchanged HTML and multibyte/context expansion.
  Native document uploads retain their separate source-size limit.
- Selected void HTML elements (`html_img`, `html_br`, `html_input`, and others)
  are protected as complete individual opening tags, including in recursively
  encoded JSON. They previously accepted the filter name but matched no block.
- Numeric transport error codes survive document polling/download and batch
  wrapping. Document-workflow deadline expiry uses timeout code 28; error messages
  remain sanitized without retaining the original unmasked transport exception.
- Restored `composer lint` and `composer phpunit` aliases alongside `test:*`
  commands, and corrected the README example's supported-format list.

- Nonempty prepared input can no longer produce a successful empty or
  whitespace-only provider translation, even with `--no-validate`. Empty source,
  dry-run, and local no-translation results retain their existing behavior.
- JSON validation rejects previously nonempty strings becoming empty or Unicode
  whitespace, with property/index diagnostics. Recursive encoded strings receive
  the same check only when embedded JSON validation is enabled.
- HTML validation rejects cleared prose in text containers and cleared nonempty
  `title`, `alt`, or `aria-label` attributes. Inline text may move within its
  container; retained opaque code, script/style, SVG, or MathML text cannot hide
  lost prose. The same rules apply at explicitly selected HTML-in-JSON locations.
- JSON validation accepts reordered object properties while preserving array
  positions, container types, and exact numbers/booleans/null at each location.
  Large integers, precise decimals, and numeric spelling changes no longer hide
  behind floating-point rounding. Output is not repaired or reordered.
- HTML validation preserves tag structure, non-translatable attributes, comments,
  doctype, and Markdown fence delimiters and boundary labels. Attribute order and
  equivalent entity spellings remain permitted.
- Placeholder restoration rejects missing, duplicated, or unexpected markers,
  including with container validation disabled. Literal marker-like source text
  and overlapping placeholders no longer collide or cascade during restoration.
- URL/HTML filters preserve exact escaped source bytes inside ordinary and
  recursively encoded JSON strings. Complete nested HTML blocks and full tag
  names are matched without spanning separate JSON strings. Comments, CDATA,
  attributes, and raw-text contexts do not create false tag boundaries.
- Unclosed protected HTML blocks, incomplete tags, unknown filters, and malformed
  selected tag names now fail explicitly before HTTP. Self-closing SVG/MathML
  blocks remain protected. Long URL matching failures stop translation instead
  of silently dropping protection.
- DeepL JSON defaults to native document upload/poll/download with bounded waits,
  a 1 MB source limit, masked document credentials, and CLI-compatible positive
  integer `document_timeout` values. Unsupported document options fail explicitly;
  failures do not silently select another transport.
- DeepL's explicit `json_transport=text_batch` translates string values in order
  and reconstructs JSON locally with safe escaping; no-value JSON completes
  without HTTP. Literal technical strings remain translatable.
- DeepL HTML protects normal-text placeholders in request-local non-translatable
  spans, preventing observed movement of a protected code block into a link.
  Only generated wrappers are removed; invalid wrappers fail restoration.
- Yandex rejects truncated, filtered, refused, tool-call, and invalid completion
  states before accepting translated content. Its narrow observed opening-only
  HTML fence compatibility path preserves source fences and rejects other
  introduced wrappers. Chat Completions usage reaches the common normalizer.
- Request/error diagnostics mask API keys, Yandex folder IDs, proxies, and their
  URL/form/JSON-escaped representations. Raw translation data remains unchanged.
- Invalid, negative, fractional, or unrepresentable usage counters retain the
  existing zero fallback. Aggregate overflow omits only the affected metric;
  CLI output does not invent a zero total.
- CLI diagnostic-write failures no longer contaminate stdout. Invalid ordinary
  output paths fail before paid requests. Regular local outputs are staged and
  atomically replaced; provider/validation failures leave existing files intact.
- Reserved model variables cannot override facade/CLI format, dry-run, prompt,
  context, verbosity, or internal response context.
- Removed obsolete cURL/reflection calls deprecated by PHP 8.5; PHP 8.2 remains
  supported. Composer distribution archives now include the README.
- Prompt instructions consistently describe boundary markers and prohibit added
  Markdown fences; strict response validation remains authoritative.

### Compatibility and known limitations

- Text and HTML still default to `document`. A nonempty document response may
  omit prose; validation does not establish semantic completeness. Segment IDs
  detect missing fragments, not omissions inside a returned nonempty fragment or
  translation fidelity. Smaller independent fragments may reduce fluency.
- HTML content checks do not render CSS or require each inline text node to stay
  nonempty. Opaque/raw-text blocks are excluded from prose checks. JSON strings
  are not interpreted as HTML or nested JSON without explicit selection/opt-in.
- `--no-validate` / `validate: false` disables optional container/content checks;
  placeholder integrity, required segment protocol, and the empty-response guard
  remain mandatory. Newly rejected invalid inputs/outputs previously could appear
  successful. Custom segment prompt groups need a JSON template.
- Batching is sequential and opt-in. No automatic HTTP retry or transport fallback
  is added. Byte caps measure serialized fragment JSON, not the full HTTP body or
  exact token counts. CPU work and callbacks observe the shared deadline at
  boundaries; it does not preempt an arbitrary blocking callback.
- A checkpoint cannot guarantee exactly-once billing for a request interrupted
  before its confirmed result was saved, or resume an in-flight DeepL document job.
  It contains translated values and usage, so it should be treated as private data.
- Atomic replacement covers eligible regular local files. Streams, hard links,
  some ownership/permission combinations, and other compatibility cases use direct
  writes; ACLs/extended attributes are not copied to a new inode. See the README.
- Protected non-void HTML blocks require explicit closing tags; malformed markup
  is not repaired. DeepL technical string contents may change and must be protected
  explicitly when their bytes must remain unchanged.
- Provider access, quotas, output budgets, content filters, and availability remain
  external constraints. Registry support is not a live test of every listed model.

### Verification

- PHP 8.2.34 and 8.5.11: 1162 unit tests, 6592 assertions on each; PHPCS and
  PHPStan 2.3.2 at maximum level pass.
- Paid acceptance on 2026-10-25, before the audit corrections: 48/48 small
  segment cases across Gemini 2.5 Flash,
  GPT-5 mini, Claude Haiku 4.5, Grok 4.20 non-reasoning, Yandex Lite/Pro, and
  DeepL Free/Pro, using API/CLI and text/HTML/selected HTML-in-JSON. Checkpoint
  reuse, protected fragments, and unselected JSON bytes were checked. This run
  used DeepL `text_batch`; earlier native JSON Document results remain recorded.
- After the audit corrections, 112/112 recorded provider response packets from
  those 48 cases pass the current driver decoders. DeepL replays use synthetic
  value-count contexts and verify decoding only. Negative completion states,
  credential-free PHPUnit/JUnit output, body limits, void filtering, and transport
  codes have controlled regressions; no additional paid requests were made.
- Default document-mode smoke on 2026-10-25: 12/12 cases on Gemini 2.5 Flash and Yandex Lite.
  Offline failure/resume acceptance passed for all three formats on both PHP
  versions. This is not exhaustive model-catalogue or translation-quality testing.
  See [the acceptance record](tools/README.md#follow-up-acceptance-for-release-preparation)
  for scope and the separately retained earlier connection timeout.

## [0.9.0] – 2026-10-23

This release supersedes the withdrawn `v0.8.0` Git release. Packagist retains
published version references, so the updated code is distributed under a new
version number. Consumers pinned to `0.8.0` must update their constraint.

### Added

- Integrated the developer's generic JSON prototypes: `structured_json` uses an
  explicit caller-provided `response_schema` with OpenAI Chat Completions/Responses
  or Gemini native schema output; `linedJson` selects an alternative prompt with
  ordinary JSON container validation. Neither mode assumes application field names.
- Added CLI `--response-schema=FILE` for native structured JSON. Native responses
  do not use translation markers. Gemini thought parts are excluded from output.
- Expanded the six-provider registry to 132 entries as checked on 2026-10-22:
  72 OpenAI, 17 Anthropic, 17 Google, 16 Yandex, eight xAI models, and two DeepL
  service tiers. Includes served legacy models, previews, dated snapshots, and
  restricted-access models. Availability remains dependent on the provider and account.
- Added `resources/model-catalog.json` with provider API identifiers, source links,
  exclusions, and results of plain-text translation checks.
- Added OpenAI and xAI Responses API requests and response parsing, legacy OpenAI
  Completions requests, and Yandex OpenAI-compatible requests alongside the
  Foundation API. The registry selects the API required by each model.
- Added model-specific reasoning settings for OpenAI, Claude adaptive thinking and
  effort, Gemini thinking levels, and DeepL engine selection through `model_type`.
- Added the optional `onFeedback` translation progress callback, wired to verbose
  CLI output. It was described in the 0.7.0 notes but absent from that tagged code.
- CLI credentials and required model variables accept non-empty `BBLSLUG_` prefixed
  environment variables before their unprefixed equivalents; explicit CLI values win.
- Added unit tests for translation, drivers, validation, filters, HTTP, CLI output,
  and registry/documentation consistency. Live integration tests are opt-in and
  can select an exact model with `BBLSLUG_LIVE_MODEL`.

### Fixed

- Anthropic now uses the Messages API. DeepL now uses header authentication and
  the correct Pro endpoint.
- OpenAI and xAI response parsing handles the corresponding API response shapes,
  refusals, and incomplete responses rather than accepting them as translations.
- JSON validation checks the actual returned document. Missing fields or list
  elements, including `null`, are rejected rather than silently validating a
  repaired tree while returning incomplete output.
- The alternative JSON prompt now requires consistent output markers rather than
  simultaneously requesting marked output and JSON alone. Test YAML files use
  unique names so concurrent test runs do not overwrite each other's fixtures.
- JSON structure checks distinguish objects from arrays, including empty
  containers and objects with numeric keys.
- Translation HTTP and provider parsing errors mask API keys in URLs, response
  bodies, and diagnostic logs, including encoded and JSON-escaped forms.
- HTTP request logs mask URL credentials in verbose and dry-run modes. Requests
  have configurable connection and total timeouts, defaulting to 10 and 120
  seconds; network exceptions preserve the cURL error code.
- CLI exits with an error when translated or dry-run prepared output cannot be
  fully written.
- Composer distributions include the CLI executable. Composer binary proxies use
  the consuming project's autoloader.
- README examples and CLI help describe credential precedence, prompt selection,
  dry-run behavior, and validation limits. Removed duplicated provider guidance.

### Compatibility and upgrade notes

- PHP **8.2 or newer**, cURL, DOM, and mbstring are required. The PHP floor matches
  the existing Symfony YAML dependency; older manifests understated it.
- `onFeedback` is appended to `Bblslug::translate()` so the original positional
  argument order is preserved. Named callback arguments remain supported.
- `ModelDriverInterface::parseResponse()` accepts an optional third
  `array $options = []` argument. Update custom driver implementations to that
  signature; direct two-argument calls to built-in drivers remain supported.
- Compared with the withdrawn tag, `structured_json` now requires an explicit
  schema and is limited to the two implemented providers; the CLI can supply it
  with `--response-schema`. Translation input remains a string. The old array
  input declaration did not provide a working array translation path.
- `linedJson` rejects malformed or structurally incomplete output instead of
  attempting to repair quotes with a regular expression. It is not JSON Lines.
- Removed retired or invalid keys: `anthropic:claude-haiku-3.5`,
  `anthropic:claude-opus-4`, `anthropic:claude-sonnet-4`,
  `google:gemini-2.0-flash`, `yandex:llama`, and `yandex:llama-lite`.
  Removed compatibility redirects `xai:grok-4`, `xai:grok-3`, and
  `xai:grok-3-mini`, which no longer select those models at the provider.
  Removed keys fail locally; the library does not silently substitute a model.
- JSON outputs accepted by earlier structure checks can now fail validation if
  they omit values or change an object into an array. Object key order is still
  compared strictly.
- Requests that previously waited indefinitely can now time out. Override
  `BBLSLUG_HTTP_CONNECT_TIMEOUT` and `BBLSLUG_HTTP_TIMEOUT` when needed. Invalid
  timeout settings fail locally. Requests are not automatically retried.
- **Upgrade consumers pinned to `0.8.0` to `0.9.0`.** For example, run
  `composer require habr/bblslug:0.9.0`, review the updated source/dist reference,
  and commit both `composer.json` and `composer.lock`. Existing `composer install`
  commands continue to use their locked copy.
- Packagist's [version immutability policy](https://packagist.org/about/version-immutability)
  prevents replacing stable source/dist references when a Git tag moves. The
  attempted Git republication of `0.8.0` was therefore withdrawn. Cache clearing
  and update webhooks do not replace that snapshot; use `0.9.0` instead. A VCS
  override is not required for this release.

### Verification and known limitations

- The release candidate passes 209 unit tests with 2,073 assertions on PHP 8.2,
  8.3, 8.4, and 8.5. PHP_CodeSniffer and PHPStan at maximum level pass. PHP 8.5
  emits deprecation notices for existing cURL cleanup and test reflection calls.
- Live checks on 2026-10-23 confirmed nested JSON translation through OpenAI Chat
  Completions, OpenAI Responses, and Gemini native schema output. `linedJson`
  passed small JSON translations with OpenAI and Gemini after correcting its
  contradictory marker instructions. These checks cover representative models,
  not every registered model or arbitrary production input.
- Provider checks recorded on 2026-10-22 confirmed plain-text translation for 123
  registry entries. Eight entries were unavailable to the test credentials, and
  `openai:gpt-5-pro` timed out. Registry presence is not a successful live test.
- HTML and JSON have not been verified across every model. DeepL JSON and Yandex
  HTML/JSON remain known problem areas from earlier live checks. This release
  does not promise reliable structured-content translation with every provider.
- JSON checks cover syntax and captured structure, not translation quality or
  preservation of numeric and boolean values. JSON encoded inside a string is not validated
  independently. Escaping and placeholder restoration can still produce invalid
  output, which validation rejects instead of repairing.
- HTML validation checks parsing, not preservation of the original DOM tree.
  Placeholder loss and duplication are not checked automatically.
- Model retirement dates and access restrictions still apply after release;
  the catalog is a dated record, not a guarantee of continuing availability.

## [0.8.0] – Withdrawn

The original tag contained unfinished JSON translation prototypes. A Git
republication was withdrawn because Packagist retained the original immutable
source/dist references. The generic prototypes were completed and integrated
into **0.9.0** with their original authorship preserved. Use 0.9.0 for the updated
code; changing the old tag cannot update existing Packagist snapshots or lock files.

## [0.7.0] – 2025-08-09

### Added
- **JSON translation**  
  - New `--format=json` in CLI and API.  
  - `JsonValidator` (syntax) and `Schema` (structure capture/compare) for pre- and post-translation checks of JSON containers.  
  - DeepLDriver: JSON support via safe punctuation placeholders and restoration on parse.
  - Samples: added some JSON samples.
- **Multiple prompt templates**  
  - `promptKey` in `Bblslug::translate()` and CLI flag `--prompt-key`.  
  - `Bblslug::listPrompts()` and `--list-prompts` to inspect available templates.  
  - All drivers aligned to the new templating.
- **Progress callback**  
  - `onFeedback(?callable)` progress callback with levels `info|warning|error`, wired to `--verbose` in CLI.
- **Input length guard**  
  - `TextLengthValidator` — early check of prepared input against model limits (`max_tokens`, `max_output_tokens`, `estimated_max_chars`, ~4 chars/token heuristic).
- **Models**  
  - OpenAI: add GPT-5 family (`gpt-5`, `gpt-5-mini`, `gpt-5-nano`).  
  - X.ai: removed deprecated `grok-3-fast` and `grok-3-mini-fast` due to anouncement.

### Changed
- **Registry & usage metrics**  
  - OpenAI: expose reasoning tokens (`completion_tokens_details.reasoning_tokens`); set explicit limits for GPT-4o / GPT-4o-mini / GPT-4 / GPT-4-turbo.  
  - Google: account for `thoughts` in usage; keep `gemini-2.0-flash` defined and listed after the 2.5 family.  
  - xAI: normalized usage keys; explicit limits for `grok-4`, `grok-3`, `grok-3-mini`.
- **Docs & CLI**  
  - README updated: model list, `onFeedback`, examples with `promptKey` and `--list-prompts`.  
  - CLI: shows progress messages when `--verbose` is enabled.

### Fixed
- **Truncation handling**  
  - OpenAI & Anthropic: detect `finish_reason=length` and fail fast with a clear error.  

### Breaking changes
- **X.ai Grok models**  
  - Models `grok-3-fast` and `grok-3-mini-fast` has been completely removed.

## [0.6.0] – 2025-08-01

### Added
- **HTML validation**  
  - `ValidatorInterface`, `ValidationResult` and `HtmlValidator` to perform pre- and post-translation syntax checks on HTML documents and fragments  
  - `--no-validate` CLI flag and `validate` option in `Bblslug::translate()` to disable validation  
- **Centralized prompts**  
  - New `resources/prompts.yaml` with `translator.text` and `translator.html` templates  
  - `Prompts::render()` to load and substitute variables into system prompts  
- **Usage metrics**  
  - `UsageExtractor` normalizes raw vendor usage data into a common schema  
  - CLI now reports “Usage metrics” (total + breakdown) after each translation  
- **Improved CLI**  
  - Extracted CLI logic into `src/Bblslug/Console/Cli.php` with `Cli::run()` entrypoint  
  - `bblslug.php` now invokes `\Bblslug\Console\Cli::run()`  
  - New `--list-models` command via `Bblslug::listModels()`  
  - `--variables` to pass or override model-specific options  
- **Models registry & drivers**  
  - Support for vendor-level grouping in `resources/models.yaml` (flattened into `vendor:model` keys)  
  - Added Yandex Foundation Models (`YandexDriver`) and xAI Grok (`XaiDriver`) support  
  - `ModelDriverInterface::parseResponse()` now returns `[ 'text' => ..., 'usage' => ... ]`  
  - `ModelRegistry::getVariables()` to fetch required env vars (e.g. `YANDEX_FOLDER_ID`)  
- **Samples**  
  - `samples/html_fragments/ru_fragment.html` and `..._corrupted.html` for validation tests  
  - Restructured `samples/tech_fragments/` into `classical/` and new `modern/` sets with fresh examples  

### Changed
- **README & Help**  
  - Document new validation (`--no-validate`), variables, usage-metrics and new vendors (Yandex, xAI)  
  - Expanded CLI examples and Quickstart sections  
- **Debug logging**  
  - When `--verbose` is used, “[Validation pre-pass]” is prepended to request log and “[Validation post-pass]” appended to response  
- **ModelRegistry**  
  - Renamed and relocated driver classes under `Models/Drivers/`  
  - Registered new `yandex` and `xai` vendors in `getDriver()`  
- **Bblslug::translate()**  
  - Signature updated to accept `bool $validate` and `array $variables`  
  - Merged `variables` into driver options and extracted usage via `UsageExtractor`  

### Removed
- **Legacy CLI bootstrap** (`Bblslug::runFromCli()`, old `Help::printModelList(ModelRegistry)`)  
- **Obsolete test** `tests/DummyTest.php`  

## [0.5.0] – 2025-07-25

### Added
- **New model support**:
  - **Anthropic Claude** (Haiku 3.5, Sonnet 4, Opus 4) via `AnthropicDriver`
  - **Google Gemini** (2.0 Flash, 2.5 Flash, 2.5 Flash-Lite, 2.5 Pro) via `GoogleDriver`
  - **OpenAI GPT Chat** (GPT-4, GPT-4 Turbo, GPT-4o, GPT-4o Mini) via `OpenAiDriver`
- **HTTP proxy** support (`--proxy` flag / `BBLSLUG_PROXY`) in `HttpClient` and CLI
- **YAML-based registry** (`resources/models.yaml`) replacing the PHP model config

### Changed
- **Registry keys & auth flags**:
  - Renamed `gemini:*` → `google:gemini-*`
  - Added `ANTHROPIC_API_KEY` and updated `--api-key-google` / `GOOGLE_API_KEY`
- Updated `README.md` & `Help.php` to list supported models
- Minor cleanups: JSON payload alignment, code/style tweaks, help text refinements

### Removed
- Legacy PHP registry (`resources/models.php`)


## [0.4.0] - 2025-07-21

### Added
- **Model driver abstraction**  
  Introduce `ModelDriverInterface` and `DeepLDriver`
  to encapsulate per-vendor request/response logic.
- **HTTP client wrapper**  
  Add `HttpClient::request()` with support for verbose logging,
  dry-run mode and `maskPatterns`.
- **DeepL parameters**  
  Support optional `--source-lang`, `--target-lang` and `--context` (prompt)
  flags in CLI and API.
- **CLI flags**  
  Add `--context`, `--source-lang` and `--target-lang` to `runFromCli()`.

### Changed
- **DeepL defaults**  
  Move default `target_lang` & `formality` into registry `defaults`,
  drop HTML-only `prompt_html` in favor of generic `context`.
- **API signatures**  
  Extend `translate()` and `runFromCli()` to accept the new parameters.
- **Help & docs**  
  Sort option lists alphabetically, translate all comments/docblocks to English,
  update README and `resources/models.php`.

### Removed
- **Legacy client**  
  Remove the old `LLMClient` in favor of `HttpClient` + drivers.


## [0.3.0] - 2025-06-25

### Changed
- Project renamed from **Babelium** to **Bblslug**
  - All namespaces changed from `Babelium\` to `Bblslug\`
  - Main entry file renamed: `babelium.php` → `bblslug.php`
  - Binary renamed: `bin/babelium` → `bin/bblslug`
  - Updated references in `composer.json`, `README.md`, and help output
- Description updated to reflect broader LLM support,
  removing DeepL-centric wording


## [0.2.1] - 2025-06-25

### Changed
- Help output refined to better group options and improve clarity

### Fixed
- If no filters were specified, the filter statistics section
  now explicitly notes that no filters were applied


## [0.2.0] - 2025-04-09

### Added
- Model registry system with support for multiple LLM providers
- `--model`, `--list-models`, `--filters` CLI options
- URL and HTML tag filters (e.g. `html_pre`, `html_code`)
- Placeholder manager with compact, reversible mapping
- Dry-run mode now saves `.prepared` intermediate files
- Verbose mode shows placeholder statistics and counts
- Example HTML file with varied content

### Changed
- Core logic moved under `src/Babelium` with modular layout
- Translation logic abstracted to `LLMClient`
- Help output improved, grouped, and colorized

### Removed
- Old placeholder handling hardcoded into Babelium.php


## [0.1.0] - 2025-04-09

### Added
- Initial CLI interface for DeepL-based translation
- Format support: `html`, `text`
- Placeholder protection for code, tags, URLs
- Dry-run mode
- Composer autoload and bin entry
- Project structure with `src/`, `bin/`, and bootstrap
- Readme, license, other meta files
