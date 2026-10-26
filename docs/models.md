# Model catalogue

[README](../README.md) · [Usage](usage.md) · [Providers](providers.md)

Generated from `resources/models.yaml` by `php tools/model-catalog.php`; do not edit the lists by hand.

Use `vendor/bin/bblslug --list-models` for models suited to new integrations, or
`vendor/bin/bblslug --list-models=all` for the complete registry. PHP `Bblslug::listModels()`
continues to return every registered key. Lists are sorted by provider and then natural model-name order.

## Choosing a model

Prefer a current undated provider model ID or alias in application configuration. An undated ID is not
necessarily a rolling alias: current Claude IDs, for example, can identify fixed versions without dates.
A rolling `latest` alias can change the served model, capabilities and pricing, and may point at a preview.
The library sends the selected API identifier; it does not silently replace retired models.

| Kind | Meaning |
| --- | --- |
| `alias` | Provider-maintained alias; the target may change |
| `model` | Version/family ID without a pinned build date |
| `snapshot` | Pinned provider release; retained for existing integrations |
| `preview` | Preview or release-candidate identifier |
| `tier` | DeepL service tier; choose the translation engine with `model_type` |

Lifecycle (`available` / `deprecated`) is separate from access (`standard`, `existing_customers`,
`restricted`). Standard access still requires a valid account, region, quota and supported API.
Announced shutdown dates are shown where known; an absent date means no date is recorded,
not indefinite support.
Google dates can be earliest possible retirement dates.
Anthropic “not sooner than” guarantees are not shutdown dates.

## Models for new integrations

This view omits pinned snapshots, previews, deprecated entries and access-restricted entries.
It is a discovery aid, not a claim that every listed model was live-tested for every format.

| Model key | Kind |
| --- | --- |
| `anthropic:claude-fable-5` | model |
| `anthropic:claude-fable-5.1` | model |
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
| `google:gemini-3.5-flash-lite` | model |
| `google:gemini-3.6-flash` | model |
| `google:gemini-3.8-flash` | model |
| `google:gemini-4-argon` | model |
| `google:gemini-flash-latest` | alias |
| `google:gemini-flash-lite-latest` | alias |
| `google:gemini-pro-latest` | alias |
| `google:gemma-4-26b-a4b-it` | model |
| `google:gemma-4-31b-it` | model |
| `openai:chat-latest` | alias |
| `openai:gpt-5` | alias |
| `openai:gpt-5-mini` | alias |
| `openai:gpt-5-nano` | alias |
| `openai:gpt-5-pro` | alias |
| `openai:gpt-5-search-api` | alias |
| `openai:gpt-5.2` | alias |
| `openai:gpt-5.2-pro` | alias |
| `openai:gpt-5.4` | alias |
| `openai:gpt-5.4-mini` | alias |
| `openai:gpt-5.4-pro` | alias |
| `openai:gpt-5.5` | alias |
| `openai:gpt-5.5-pro` | alias |
| `openai:gpt-5.6-luna` | alias |
| `openai:gpt-5.6-sol` | alias |
| `openai:gpt-5.6-terra` | alias |
| `openai:gpt-6-astra` | alias |
| `openai:gpt-6-luna` | alias |
| `openai:gpt-6-sol` | alias |
| `openai:gpt-6.1-sol` | alias |
| `openai:o3` | alias |
| `openai:o3-pro` | alias |
| `xai:grok-4.3` | model |
| `xai:grok-4.5` | model |
| `xai:grok-4.6` | model |
| `xai:grok-4.7` | model |
| `xai:grok-build-0.1` | model |
| `yandex:aliceai-llm` | model |
| `yandex:aliceai-llm-flash` | model |
| `yandex:deepseek-v4.1-flash` | model |
| `yandex:gpt-32k` | alias |
| `yandex:gpt-lite` | alias |
| `yandex:gpt-pro` | alias |
| `yandex:qwen3-235b-a22b-fp8` | model |
| `yandex:qwen3.6-35b-a3b` | model |
| `yandex:yandexgpt-5-pro` | model |
| `yandex:yandexgpt-5.1` | model |
| `yandex:yandexgpt-latest` | alias |

## Full registry

<details>
<summary>Show all registered models, including snapshots and legacy entries</summary>

APIs supported:

- anthropic:
  - `anthropic:claude-fable-5` — model; available; standard
  - `anthropic:claude-fable-5.1` — model; available; standard
  - `anthropic:claude-haiku-4.5-20251001` — snapshot; available; standard
  - `anthropic:claude-haiku-5.5` — model; available; standard
  - `anthropic:claude-mythos-5` — model; available; restricted
  - `anthropic:claude-mythos-5.1` — model; available; restricted
  - `anthropic:claude-mythos-preview` — preview; deprecated; restricted
  - `anthropic:claude-opus-4.5-20251101` — snapshot; available; standard
  - `anthropic:claude-opus-4.6` — model; available; standard
  - `anthropic:claude-opus-4.7` — model; available; standard
  - `anthropic:claude-opus-4.8` — model; available; standard
  - `anthropic:claude-opus-5` — model; available; standard
  - `anthropic:claude-opus-5.5` — model; available; standard
  - `anthropic:claude-sonnet-4.5-20250929` — snapshot; deprecated; standard; shutdown 2026-11-30; replacement anthropic:claude-sonnet-5.5
  - `anthropic:claude-sonnet-4.6` — model; available; standard
  - `anthropic:claude-sonnet-5` — model; available; standard
  - `anthropic:claude-sonnet-5.5` — model; available; standard
- deepl:
  - `deepl:free` — tier; available; standard
  - `deepl:pro` — tier; available; standard
- google:
  - `google:gemini-2.5-flash` — model; available; existing_customers
  - `google:gemini-2.5-flash-lite` — model; available; existing_customers
  - `google:gemini-2.5-pro` — model; available; existing_customers
  - `google:gemini-3-flash-preview` — preview; available; standard
  - `google:gemini-3.1-flash-lite` — model; deprecated; standard; shutdown 2027-05-07; replacement google:gemini-3.5-flash-lite
  - `google:gemini-3.1-pro-preview` — preview; available; standard
  - `google:gemini-3.1-pro-preview-customtools` — preview; available; standard
  - `google:gemini-3.5-flash-lite` — model; available; standard
  - `google:gemini-3.6-flash` — model; available; standard
  - `google:gemini-3.8-flash` — model; available; standard
  - `google:gemini-4-argon` — model; available; standard
  - `google:gemini-flash-latest` — alias; available; standard
  - `google:gemini-flash-lite-latest` — alias; available; standard
  - `google:gemini-pro-latest` — alias; available; standard
  - `google:gemma-4-26b-a4b-it` — model; available; standard
  - `google:gemma-4-31b-it` — model; available; standard
- openai:
  - `openai:chat-latest` — alias; available; standard
  - `openai:gpt-4.1` — alias; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4.1-2025-04-14` — snapshot; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4.1-mini` — alias; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4.1-mini-2025-04-14` — snapshot; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4o` — alias; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4o-2024-08-06` — snapshot; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4o-2024-11-20` — snapshot; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4o-mini` — alias; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-4o-mini-2024-07-18` — snapshot; deprecated; standard; shutdown 2027-01-14
  - `openai:gpt-5` — alias; available; standard
  - `openai:gpt-5-2025-08-07` — snapshot; deprecated; standard; shutdown 2026-12-11
  - `openai:gpt-5-mini` — alias; available; standard
  - `openai:gpt-5-mini-2025-08-07` — snapshot; deprecated; standard; shutdown 2026-12-11
  - `openai:gpt-5-nano` — alias; available; standard
  - `openai:gpt-5-nano-2025-08-07` — snapshot; deprecated; standard; shutdown 2026-12-11
  - `openai:gpt-5-pro` — alias; available; standard
  - `openai:gpt-5-pro-2025-10-06` — snapshot; deprecated; standard; shutdown 2026-12-11
  - `openai:gpt-5-search-api` — alias; available; standard
  - `openai:gpt-5-search-api-2025-10-14` — snapshot; available; standard
  - `openai:gpt-5.1` — alias; deprecated; standard; shutdown 2027-04-01
  - `openai:gpt-5.1-2025-11-13` — snapshot; available; standard
  - `openai:gpt-5.2` — alias; available; standard
  - `openai:gpt-5.2-2025-12-11` — snapshot; available; standard
  - `openai:gpt-5.2-pro` — alias; available; standard
  - `openai:gpt-5.2-pro-2025-12-11` — snapshot; available; standard
  - `openai:gpt-5.3-codex` — alias; deprecated; standard; shutdown 2027-04-01
  - `openai:gpt-5.4` — alias; available; standard
  - `openai:gpt-5.4-2026-03-05` — snapshot; available; standard
  - `openai:gpt-5.4-mini` — alias; available; standard
  - `openai:gpt-5.4-mini-2026-03-17` — snapshot; available; standard
  - `openai:gpt-5.4-nano` — alias; deprecated; standard; shutdown 2027-04-01
  - `openai:gpt-5.4-nano-2026-03-17` — snapshot; available; standard
  - `openai:gpt-5.4-pro` — alias; available; standard
  - `openai:gpt-5.4-pro-2026-03-05` — snapshot; available; standard
  - `openai:gpt-5.5` — alias; available; standard
  - `openai:gpt-5.5-2026-04-23` — snapshot; available; standard
  - `openai:gpt-5.5-pro` — alias; available; standard
  - `openai:gpt-5.5-pro-2026-04-23` — snapshot; available; standard
  - `openai:gpt-5.6-cyber` — alias; available; restricted
  - `openai:gpt-5.6-luna` — alias; available; standard
  - `openai:gpt-5.6-sol` — alias; available; standard
  - `openai:gpt-5.6-terra` — alias; available; standard
  - `openai:gpt-6-astra` — alias; available; standard
  - `openai:gpt-6-luna` — alias; available; standard
  - `openai:gpt-6-sol` — alias; available; standard
  - `openai:gpt-6.1-sol` — alias; available; standard
  - `openai:gpt-rosalind-discovery` — alias; available; restricted
  - `openai:gpt-rosalind-research` — alias; available; restricted
  - `openai:o3` — alias; available; standard
  - `openai:o3-2025-04-16` — snapshot; deprecated; standard; shutdown 2026-12-11
  - `openai:o3-pro` — alias; available; standard
  - `openai:o3-pro-2025-06-10` — snapshot; deprecated; standard; shutdown 2026-12-11
- xai:
  - `xai:grok-4.3` — model; available; standard
  - `xai:grok-4.5` — model; available; standard
  - `xai:grok-4.6` — model; available; standard
  - `xai:grok-4.7` — model; available; standard
  - `xai:grok-4.20-0309-non-reasoning` — snapshot; available; standard
  - `xai:grok-4.20-0309-reasoning` — snapshot; available; standard
  - `xai:grok-4.20-multi-agent-0309` — snapshot; available; standard
  - `xai:grok-build-0.1` — model; available; standard
- yandex:
  - `yandex:aliceai-llm` — model; available; standard
  - `yandex:aliceai-llm-flash` — model; available; standard
  - `yandex:deepseek-v4-flash` — model; deprecated; standard; shutdown 2026-11-15; replacement yandex:deepseek-v4.1-flash
  - `yandex:deepseek-v4.1-flash` — model; available; standard
  - `yandex:gpt-32k` — alias; available; standard
  - `yandex:gpt-lite` — alias; available; standard
  - `yandex:gpt-oss-20b` — model; deprecated; standard; shutdown 2026-10-30
  - `yandex:gpt-oss-120b` — model; deprecated; standard; shutdown 2026-10-30
  - `yandex:gpt-pro` — alias; available; standard
  - `yandex:qwen3-235b-a22b-fp8` — model; available; standard
  - `yandex:qwen3.6-35b-a3b` — model; available; standard
  - `yandex:yandexgpt-5-lite` — model; deprecated; standard; shutdown 2026-11-15; replacement yandex:deepseek-v4.1-flash
  - `yandex:yandexgpt-5-pro` — model; available; standard
  - `yandex:yandexgpt-5.1` — model; available; standard
  - `yandex:yandexgpt-latest` — alias; available; standard
  - `yandex:yandexgpt-rc` — preview; available; standard

This list mirrors the registry. Lifecycle review: **2026-10-26**.

</details>

## Lifecycle sources and maintenance

Lifecycle metadata lives in `resources/models.yaml`: `kind`, `status`, `access`, optional
`shutdown_date` / `replacement`, `checked_at` and `lifecycle_source`. Sources apply to the provider-hosted API,
not similarly named models on third-party hosting. Verified retired IDs are excluded rather than redirected.

- [anthropic](https://platform.claude.com/docs/en/about-claude/model-deprecations)
- [deepl](https://developers.deepl.com/api-reference/translate/request-translation)
- [google](https://ai.google.dev/gemini-api/docs/deprecations)
- [openai](https://developers.openai.com/api/docs/deprecations)
- [xai](https://docs.x.ai/developers/models)
- [yandex](https://aistudio.yandex.ru/ru/docs/ai-studio/concepts/generation/models)

API payload IDs, exclusions and dated historical plain-text observations are retained in
[model-catalog.json](../resources/model-catalog.json). Historical checks are not current availability guarantees.
When updating a provider, check its lifecycle source, update metadata and exclusions, regenerate this page,
then run `php tools/model-catalog.php --check` and the registry tests.
No provider API is contacted by generation.
