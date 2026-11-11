# Model catalogue

[README](../README.md) · [Usage](usage.md) · [Providers](providers.md)

Generated from `resources/models.yaml` by `php tools/model-catalog.php`; do not edit the lists by hand.

Use `vendor/bin/bblslug --list-models` for models suited to new integrations, or
`vendor/bin/bblslug --list-models=all` for the complete registry. PHP `Bblslug::listModels()`
continues to return every registered key. Lists are sorted by provider and then natural model-name order.

## Choosing a model

Choose a canonical provider model ID. The built-in registry excludes provider aliases, compatibility
redirects and dated snapshots. An undated ID can identify a fixed version; the absence of a date alone
does not establish whether an ID is canonical. Some older families have no entry under this policy.
The library sends the selected API identifier; it does not silently replace retired models.

| Kind | Meaning |
| --- | --- |
| `model` | Version/family ID without a pinned build date |
| `preview` | Preview or release-candidate identifier |
| `tier` | DeepL service tier; choose the translation engine with `model_type` |

Lifecycle (`available` / `deprecated`) is separate from access (`standard`, `existing_customers`,
`restricted`). Standard access still requires a valid account, region, quota and supported API.
Announced shutdown dates are shown where known; an absent date means no date is recorded,
not indefinite support.
`shutdown` is an announced API retirement; `redirect` is an announced automatic model reroute.
`retirement not before` is a lower bound, not a scheduled retirement or a guarantee beyond that date.

## Models for new integrations

Canonical model versions remain selectable, including supported legacy versions.
This view omits previews, deprecated entries, and models with
`restricted` or `existing_customers` access.
It is a discovery aid, not a claim that every listed model was live-tested for every format.

| Model key | Kind |
| --- | --- |
| `anthropic:claude-fable-5` | model |
| `anthropic:claude-fable-5.1` | model |
| `anthropic:claude-fable-5.2` | model |
| `anthropic:claude-haiku-5.5` | model |
| `anthropic:claude-opus-4.6` | model |
| `anthropic:claude-opus-4.7` | model |
| `anthropic:claude-opus-4.8` | model |
| `anthropic:claude-opus-5` | model |
| `anthropic:claude-opus-5.5` | model |
| `anthropic:claude-sonnet-4.6` | model |
| `anthropic:claude-sonnet-5` | model |
| `anthropic:claude-sonnet-5.5` | model |
| `deepl:free` | tier |
| `deepl:pro` | tier |
| `deepseek:deepseek-flash` | model |
| `deepseek:deepseek-v4-pro` | model |
| `google:gemini-3.1-flash-lite` | model |
| `google:gemini-3.5-flash-lite` | model |
| `google:gemini-3.6-flash` | model |
| `google:gemini-3.8-flash` | model |
| `google:gemini-4-argon` | model |
| `google:gemini-4-carbon` | model |
| `google:gemma-4-26b-a4b-it` | model |
| `google:gemma-4-31b-it` | model |
| `openai:gpt-5.6-luna` | model |
| `openai:gpt-5.6-sol` | model |
| `openai:gpt-5.6-terra` | model |
| `openai:gpt-6-astra` | model |
| `openai:gpt-6-luna` | model |
| `openai:gpt-6-sol` | model |
| `openai:gpt-6.1-sol` | model |
| `openai:gpt-6.1-terra` | model |
| `xai:grok-4.3` | model |
| `xai:grok-4.5` | model |
| `xai:grok-4.6` | model |
| `xai:grok-4.7` | model |
| `xai:grok-build-0.1` | model |
| `yandex:aliceai-llm` | model |
| `yandex:aliceai-llm-flash` | model |
| `yandex:deepseek-v4.1-flash` | model |
| `yandex:qwen3.6-35b-a3b` | model |
| `yandex:yandexgpt-5-pro` | model |
| `yandex:yandexgpt-5.1` | model |

## Full registry

<details>
<summary>Show all registered models, including previews and legacy entries</summary>

APIs supported:

- anthropic:
  - `anthropic:claude-fable-5` — model; available; standard; retirement not before 2027-06-09
  - `anthropic:claude-fable-5.1` — model; available; standard; retirement not before 2027-09-01
  - `anthropic:claude-fable-5.2` — model; available; standard; retirement not before 2027-11-09
  - `anthropic:claude-haiku-5.5` — model; available; standard; retirement not before 2027-10-07
  - `anthropic:claude-mythos-5` — model; available; restricted; retirement not before 2027-06-09
  - `anthropic:claude-mythos-5.1` — model; available; restricted; retirement not before 2027-09-01
  - `anthropic:claude-mythos-5.2` — model; available; restricted; retirement not before 2027-11-09
  - `anthropic:claude-mythos-preview` — preview; deprecated; restricted
  - `anthropic:claude-opus-4.6` — model; available; standard; retirement not before 2027-02-05
  - `anthropic:claude-opus-4.7` — model; available; standard; retirement not before 2027-04-16
  - `anthropic:claude-opus-4.8` — model; available; standard; retirement not before 2027-05-28
  - `anthropic:claude-opus-5` — model; available; standard; retirement not before 2027-07-24
  - `anthropic:claude-opus-5.5` — model; available; standard; retirement not before 2027-09-22
  - `anthropic:claude-sonnet-4.6` — model; available; standard; retirement not before 2027-02-17
  - `anthropic:claude-sonnet-5` — model; available; standard; retirement not before 2027-06-30
  - `anthropic:claude-sonnet-5.5` — model; available; standard; retirement not before 2027-09-28
- deepl:
  - `deepl:free` — tier; available; standard
  - `deepl:pro` — tier; available; standard
- deepseek:
  - `deepseek:deepseek-flash` — model; available; standard
  - `deepseek:deepseek-v4-pro` — model; available; standard
- google:
  - `google:gemini-2.5-flash` — model; deprecated; existing_customers; redirect 2026-12-01; replacement google:gemini-3.5-flash-lite
  - `google:gemini-2.5-flash-lite` — model; available; existing_customers
  - `google:gemini-2.5-pro` — model; deprecated; existing_customers; redirect 2026-12-01; replacement google:gemini-3.8-flash
  - `google:gemini-3-flash-preview` — preview; available; standard; replacement google:gemini-3.6-flash
  - `google:gemini-3.1-flash-lite` — model; available; standard; retirement not before 2027-05-07; replacement google:gemini-3.5-flash-lite
  - `google:gemini-3.1-pro-preview` — preview; available; standard
  - `google:gemini-3.1-pro-preview-customtools` — preview; available; standard
  - `google:gemini-3.5-flash-lite` — model; available; standard
  - `google:gemini-3.6-flash` — model; available; standard
  - `google:gemini-3.8-flash` — model; available; standard
  - `google:gemini-4-argon` — model; available; standard
  - `google:gemini-4-carbon` — model; available; standard
  - `google:gemma-4-26b-a4b-it` — model; available; standard
  - `google:gemma-4-31b-it` — model; available; standard
- openai:
  - `openai:gpt-5.3-codex` — model; deprecated; standard; shutdown 2027-04-01; replacement openai:gpt-6-sol
  - `openai:gpt-5.5-cyber-preview` — preview; deprecated; restricted; shutdown 2027-02-01; replacement openai:gpt-6-cyber
  - `openai:gpt-5.6-luna` — model; available; standard
  - `openai:gpt-5.6-sol` — model; available; standard
  - `openai:gpt-5.6-terra` — model; available; standard
  - `openai:gpt-6-astra` — model; available; standard
  - `openai:gpt-6-cyber` — model; available; restricted
  - `openai:gpt-6-luna` — model; available; standard
  - `openai:gpt-6-sol` — model; available; standard
  - `openai:gpt-6.1-sol` — model; available; standard
  - `openai:gpt-6.1-terra` — model; available; standard
- xai:
  - `xai:grok-4.3` — model; available; standard
  - `xai:grok-4.5` — model; available; standard
  - `xai:grok-4.6` — model; available; standard
  - `xai:grok-4.7` — model; available; standard
  - `xai:grok-build-0.1` — model; available; standard
- yandex:
  - `yandex:aliceai-llm` — model; available; standard
  - `yandex:aliceai-llm-flash` — model; available; standard
  - `yandex:deepseek-v4-flash` — model; deprecated; standard; shutdown 2026-11-15; replacement yandex:deepseek-v4.1-flash
  - `yandex:deepseek-v4.1-flash` — model; available; standard
  - `yandex:qwen3.6-35b-a3b` — model; available; standard
  - `yandex:yandexgpt-5-lite` — model; deprecated; standard; shutdown 2026-11-15; replacement yandex:deepseek-v4.1-flash
  - `yandex:yandexgpt-5-pro` — model; available; standard
  - `yandex:yandexgpt-5.1` — model; available; standard

This list mirrors the registry. Last broad lifecycle review: **2026-11-11**.

</details>

## Lifecycle sources and maintenance

Lifecycle metadata lives in `resources/models.yaml`: `kind`, `status`, `access`, optional
`shutdown_date`, `retirement_not_before`, `redirect_date`, `replacement`, `checked_at` and
`lifecycle_source`. Sources apply to the provider-hosted API,
not similarly named models on third-party hosting. Verified retired IDs are excluded rather than redirected.

- [anthropic](https://platform.claude.com/docs/en/about-claude/model-deprecations)
- [deepl](https://developers.deepl.com/api-reference/translate/request-translation)
- [deepseek](https://api-docs.deepseek.com/updates/)
- [google](https://aistudio.google.com/docs/deprecations)
- [openai](https://developers.openai.com/api/docs/deprecations)
- [xai](https://docs.x.ai/developers/pricing)
- [yandex](https://aistudio.yandex.ru/ru/docs/ai-studio/release-notes/deprecations)

API payload IDs, exclusions and dated historical plain-text observations are retained in
[model-catalog.json](../resources/model-catalog.json). Historical checks are not current availability guarantees.
When updating a provider, check its lifecycle source, update metadata and exclusions, regenerate this page,
then run `php tools/model-catalog.php --check` and the registry tests.
Keep removed keys in `resources/unavailable-models.json` with a reason and, when verified, a currently
registered replacement. PHP and CLI use this separate history for errors; removed keys stay unselectable.
No provider API is contacted by generation.
