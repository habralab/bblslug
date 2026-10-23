# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Opt-in `validateEmbeddedJson` API argument and `--validate-embedded-json` CLI
  flag validate JSON objects/arrays encoded inside source string values,
  recursively checking translated syntax, structure, and exact non-string values.
  Disabled by default; ordinary strings and encoded scalar values remain translatable.

### Fixed

- HTML tag filters protect complete outermost blocks when the selected tag is
  nested inside itself. A token scan ignores apparent tags inside comments,
  CDATA, attributes, and raw-text/RCDATA elements and restores the original bytes
  without DOM serialization. Deep nesting does not use recursive regex matching.

- HTML tag filters match the complete selected tag name, so `html_pre` no longer
  hides `<prelude>` and `html_code` no longer hides `<code-block>`. Closing-tag
  whitespace is accepted, tag names are treated literally, and a regex failure
  stops filtering instead of silently sending unprotected content for translation.

- DeepL `json`/`linedJson` now use native document upload, bounded status polling,
  and download by default. Live Free/Pro checks preserve the previously changed
  path quotation marks, including an encoded JSON object. Existing result
  validators still run; document credentials are masked in logs and errors.
  The source limit is 1 MB; `document_timeout` configures the waiting deadline.
- The previous JSON value batching remains available explicitly through
  `json_transport=text_batch`. Native document translation rejects unsupported
  `context`/`model_type` options; errors do not trigger a silent transport fallback.
- Driver request metadata may include an HTTP `workflow` continuation and an
  upload timeout, handled by the facade without changing its public arguments.

- DeepL HTML requests wrap normal-text placeholders in non-translatable spans
  tied to each request. This fixes the observed movement of a protected code block
  into a link. Only generated wrappers are removed; malformed or missing wrappers
  are rejected. Attribute, comment, and raw-text markers are left untouched.

- DeepL's explicit `text_batch` JSON transport sends string values as an ordered batch and
  reconstructs JSON locally, including string-encoded nested containers. Keys,
  non-string tokens, and container whitespace are not sent for translation.
  Returned strings are safely escaped; incomplete batches are rejected.
- DeepL JSON requests with no translatable values complete locally without HTTP.
  Driver requests may supply per-request `responseContext` and `localResult`;
  the facade handles these without changing existing method signatures.
- DeepL text/HTML responses no longer decode literal former JSON pseudo-tags.

- HTML validation rejects newly introduced Markdown fences at document boundaries,
  including an opening-only fence observed in a real Yandex Lite response.

- HTML validation rejects changes to the parsed tag tree, non-translatable
  attributes, comments, and doctype. Text and `title`, `alt`, `aria-label` values
  may translate; attribute ordering and equivalent entity spellings may change.

- Removed obsolete cURL close and reflection accessibility calls that emit
  deprecation warnings on PHP 8.5. PHP 8.2 remains supported.

- JSON validation accepts reordered object properties at any depth while retaining
  array positions and exact non-string values at each property/index. Returned
  JSON keeps the provider's property order.

- JSON validation rejects changes to numbers, booleans, and `null`, including
  numeric changes hidden by PHP floating-point rounding. Numeric tokens must keep
  their exact JSON spelling; translated string values remain unrestricted.

- Filtered translations reject missing, duplicated, or unexpected placeholder
  markers before restoration. Marker order may change; occurrence counts must
  match the prepared input, even when container validation is disabled.

- URL filtering preserves escaped quote boundaries inside JSON strings and HTML
  attributes, recognizes escaped slashes at nested JSON encoding depths, and
  supports standard `mailto:` links. Matched URL bytes are restored unchanged.

- URL and HTML filters preserve literal placeholder-like text, including partial
  and overlapping markers. Restoration avoids cascading replacements and remains
  safe when filter instances process multiple inputs.

### Known limitations

- HTML tag filters do not yet recognize JSON-escaped closing tags such as
  `<\/code>`. Unclosed selected elements remain unprotected; only complete
  explicit tag pairs are captured.

- DeepL string contents remain translatable. Native document checks passed the
  observed path scenario, but neither container validation nor a small live sample
  guarantees semantic preservation of all technical data. The explicit
  `text_batch` transport still changes path quotation marks in the observed case.

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
