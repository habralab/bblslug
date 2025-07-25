# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
