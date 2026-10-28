# Changelog

This file follows the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Release numbers follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Reduce text and HTML segment memory by storing compact source spans instead of duplicate text slices.
- Reduce memory retained by segment plans with compact typed chunk records.
- Reject segment payloads when the token scan fails instead of accepting unchecked duplicate IDs.
- Reduce segment decoding memory by scanning tokens in order and reusing validated values.
- Reduce HTML structure validation memory without weakening markup or text-loss checks.
- Preserve JSON escapes adjacent to source-literal markers during filtering, including keys and JSON stored inside strings.
- Avoid per-escape JSON position maps that could exhaust memory while protecting fragments in large or nested JSON strings.
- Avoid per-character HTML boundary maps that could exhaust memory when preparing long text spans for segment translation.

## [0.11.2] – 2026-10-27

### Changed

- Simplify segment document preparation and checkpoint result accounting while preserving PHP/CLI interfaces and provider protocols. See [upgrade guidance](docs/upgrading.md#upgrading-to-0112).
- Test PHP 8.2–8.5 and lowest admissible dependencies on PHP 8.2 in CI; update workflow runtimes and Packagist notifications.

### Fixed

- Keep request PHPDoc types parseable by the declared minimum PHPStan 2.1.

## [0.11.1] – 2026-10-27

### Changed

- Refactor translation and CLI internals while preserving public PHP signatures, CLI options and provider protocols. See [checkpoint upgrade guidance](docs/upgrading.md#upgrading-to-0111).

### Fixed

- Source segment payloads with numeric object keys raise `InvalidArgumentException` instead of `TypeError` during packet preparation.

## [0.11.0] – 2026-10-27

### Added

- Caller-supplied abbreviation hints for text/HTML segment planning in PHP and CLI.
- Native JSON Schema output for Yandex and xAI JSON requests.
- Offline documentation, catalogue and segment-recovery checks, plus an opt-in live segment acceptance suite.

### Changed

- Standard segment translations use required-key native schemas on declared Yandex Lite/Pro and Grok 4.7 models, including HTML selected inside JSON. Explicit marker requests and custom prompts retain their protocols. See [upgrade guidance](docs/upgrading.md#upgrading-to-0110).
- Model discovery is sorted by provider and natural model name. `--list-models` focuses on standard-access models for new integrations; `--list-models=all` and the PHP list include every registered key. Provider lifecycle metadata is updated.
- Grok 4.7 uses the Responses API with explicit low reasoning effort.
- Documentation is split into usage, transport, provider and upgrade guides, with runnable quickstart inputs and output-budget guidance.

### Removed

- Nineteen retired OpenAI identifiers and the shut-down Gemini 3.1 Flash-Lite preview. Removed keys fail locally; no replacement is silently selected. See [removed model keys](docs/upgrading.md#model-catalogue).

### Fixed

- Oversized segment values use sentence-sized pieces where punctuation permits, keeping dotted initials, leading list markers and lowercase continuations together when the byte cap permits. Source separators are preserved.
- Explicit xAI Chat Completions refusals are rejected before extracting translated content.
- Empty provider translations retain masked HTTP diagnostics in verbose mode; quiet errors remain concise.
- OpenAI and xAI truncation errors name the public `max_completion_tokens` option. Smoke reports classify OpenAI Responses output-limit failures as budget exhaustion.

## [0.10.0] – 2026-10-26

### Added

- Opt-in text and HTML segment transports, including explicitly selected HTML strings in JSON, sequential batching, and resumable checkpoints. See [upgrading to 0.10.0](docs/upgrading.md#upgrading-to-0100).
- A shared operation deadline for preparation and provider workflows.
- Optional recursive validation of JSON encoded in strings and HTML at selected JSON Pointers.
- Native JSON transports for Google and Yandex where supported, plus the experimental `experimental_xml` prompt.
- Masked filter previews and `tools/live-smoke.php` for small paid API and CLI checks.

### Changed

- Segment and selected HTML JSON protocols require complete, unique fragment IDs. Batching and checkpoints remain opt-in; `document` remains the default text and HTML transport.
- Validation now preserves exact JSON scalar values and container types, checks HTML structure and selected visible content. Empty provider responses and placeholder corruption are rejected even when container validation is disabled.
- DeepL JSON now defaults to the Document API; ordered string reconstruction remains available with `json_transport=text_batch`.
- Provider completion states, DeepL request limits, and diagnostic masking are checked more strictly. Invalid or incomplete responses that could previously pass may now fail.

### Fixed

- Preserved escaped URL/HTML source bytes inside nested JSON and rejected malformed protected blocks.
- Accepted reordered JSON object properties while preserving list positions and exact scalar values.
- Corrected HTML validation's handling of prior libxml errors and restored the caller's libxml error mode.
- Fixed protected void HTML elements, DeepL body-size checks, transport error codes, and OpenAI schema mode consistency.
- Prevented partial replacement of eligible local output files and checked output errors.
- Live test datasets and diagnostic assertions no longer expose credentials.
- Restored the `composer lint` and `composer phpunit` aliases and corrected supported-format documentation.

## [0.9.0] – 2026-10-23

This release supersedes the withdrawn 0.8.0. See [upgrade guidance](docs/upgrading.md#upgrading-to-090) for API and configuration changes.

### Added

- Expanded provider model registration and added machine-readable catalogue provenance.
- Generic `structured_json` with caller-provided schemas for OpenAI and Gemini, and `linedJson` with JSON container validation.
- OpenAI and xAI Responses API support, legacy OpenAI Completions, and Yandex OpenAI-compatible requests.
- Model-specific reasoning controls, DeepL engine selection, and `onFeedback` progress callbacks.
- `BBLSLUG_`-prefixed credential and model-variable environment names, which take precedence over unprefixed names.

### Changed

- The model registry and provider request protocols were updated; unavailable or removed model keys now fail locally instead of silently selecting a replacement.
- HTTP requests now have configurable connection and total timeouts, defaulting to 10 and 120 seconds.
- JSON validation checks returned values and structure without repairing incomplete output.

### Fixed

- Corrected Anthropic Messages API and DeepL authentication/endpoint handling.
- Improved provider response validation, credential masking, CLI output-write errors, and Composer distribution binaries.

## [0.8.0] – Withdrawn

The original tag contained unfinished JSON translation prototypes. A Git republication was withdrawn because Packagist retained the original immutable source/dist references. The generic prototypes were completed and integrated into **0.9.0** with their original authorship preserved. Use 0.9.0 for the updated code; changing the old tag cannot update existing Packagist snapshots or lock files. See [upgrade guidance](docs/upgrading.md#withdrawn-080).

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
