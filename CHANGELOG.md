# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
