# Model catalogue

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

Use `vendor/bin/bblslug --list-models` or `Bblslug::listModels()` to inspect the installed registry. The list below is checked against that registry by the test suite.

APIs supported:

- Anthropic (Claude):
  - `anthropic:claude-haiku-5.5` - claude-haiku-5.5
  - `anthropic:claude-sonnet-5.5` - claude-sonnet-5.5
  - `anthropic:claude-opus-5.5` - claude-opus-5.5
  - `anthropic:claude-fable-5.1` - claude-fable-5.1
  - `anthropic:claude-opus-5` - claude-opus-5
  - `anthropic:claude-sonnet-5` - claude-sonnet-5
  - `anthropic:claude-fable-5` - claude-fable-5
  - `anthropic:claude-opus-4.8` - claude-opus-4.8
  - `anthropic:claude-opus-4.7` - claude-opus-4.7
  - `anthropic:claude-sonnet-4.6` - claude-sonnet-4.6
  - `anthropic:claude-opus-4.6` - claude-opus-4.6
  - `anthropic:claude-opus-4.5-20251101` - claude-opus-4.5-20251101
  - `anthropic:claude-haiku-4.5-20251001` - claude-haiku-4.5-20251001
  - `anthropic:claude-sonnet-4.5-20250929` - claude-sonnet-4.5-20250929
  - `anthropic:claude-mythos-5` - claude-mythos-5
  - `anthropic:claude-mythos-preview` - claude-mythos-preview
  - `anthropic:claude-mythos-5.1` - claude-mythos-5.1
- DeepL:
  - `deepl:free` - DeepL API Free
  - `deepl:pro` - DeepL API Pro
- Google (Gemini and Gemma):
  - `google:gemini-2.5-flash` - Gemini 2.5 Flash
  - `google:gemini-2.5-pro` - Gemini 2.5 Pro
  - `google:gemma-4-26b-a4b-it` - gemma-4-26b-a4b-it
  - `google:gemma-4-31b-it` - gemma-4-31b-it
  - `google:gemini-flash-latest` - gemini-flash-latest
  - `google:gemini-flash-lite-latest` - gemini-flash-lite-latest
  - `google:gemini-pro-latest` - gemini-pro-latest
  - `google:gemini-2.5-flash-lite` - Gemini 2.5 Flash-Lite
  - `google:gemini-3-flash-preview` - gemini-3-flash-preview
  - `google:gemini-3.1-pro-preview` - gemini-3.1-pro-preview
  - `google:gemini-3.1-pro-preview-customtools` - gemini-3.1-pro-preview-customtools
  - `google:gemini-3.1-flash-lite-preview` - gemini-3.1-flash-lite-preview
  - `google:gemini-3.1-flash-lite` - gemini-3.1-flash-lite
  - `google:gemini-3.5-flash-lite` - gemini-3.5-flash-lite
  - `google:gemini-3.6-flash` - gemini-3.6-flash
  - `google:gemini-3.8-flash` - gemini-3.8-flash
  - `google:gemini-4-argon` - gemini-4-argon
- OpenAI:
  - `openai:gpt-4-turbo-2024-04-09` - gpt-4-turbo-2024-04-09; scheduled shutdown 2026-10-23
  - `openai:gpt-5.6-luna` - gpt-5.6-luna
  - `openai:gpt-5-search-api` - gpt-5-search-api
  - `openai:gpt-5.5-2026-04-23` - gpt-5.5-2026-04-23
  - `openai:gpt-5.4-2026-03-05` - gpt-5.4-2026-03-05
  - `openai:gpt-3.5-turbo-0125` - gpt-3.5-turbo-0125; scheduled shutdown 2026-10-23
  - `openai:gpt-5-nano` - OpenAI GPT-5 Nano
  - `openai:gpt-5.4-mini` - gpt-5.4-mini
  - `openai:o1-pro` - o1-pro; scheduled shutdown 2026-10-23
  - `openai:gpt-5-mini` - OpenAI GPT-5 Mini
  - `openai:gpt-5-mini-2025-08-07` - gpt-5-mini-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:o4-mini-2025-04-16` - o4-mini-2025-04-16; scheduled shutdown 2026-10-23
  - `openai:gpt-5.2-pro` - gpt-5.2-pro
  - `openai:gpt-5-pro-2025-10-06` - gpt-5-pro-2025-10-06; scheduled shutdown 2026-12-11
  - `openai:gpt-5.6-sol` - gpt-5.6-sol
  - `openai:gpt-5.5-pro` - gpt-5.5-pro
  - `openai:gpt-5-2025-08-07` - gpt-5-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:gpt-5.2-pro-2025-12-11` - gpt-5.2-pro-2025-12-11
  - `openai:gpt-5.5-pro-2026-04-23` - gpt-5.5-pro-2026-04-23
  - `openai:gpt-5.4-pro` - gpt-5.4-pro
  - `openai:gpt-4o-2024-11-20` - gpt-4o-2024-11-20; scheduled shutdown 2027-01-14
  - `openai:gpt-5.1` - gpt-5.1; scheduled shutdown 2027-04-01
  - `openai:gpt-5-pro` - gpt-5-pro
  - `openai:gpt-5.4-mini-2026-03-17` - gpt-5.4-mini-2026-03-17
  - `openai:gpt-5.4` - gpt-5.4
  - `openai:gpt-4.1-nano` - gpt-4.1-nano; scheduled shutdown 2026-10-23
  - `openai:gpt-3.5-turbo-16k` - gpt-3.5-turbo-16k
  - `openai:gpt-4.1` - gpt-4.1; scheduled shutdown 2027-01-14
  - `openai:gpt-5.5` - gpt-5.5
  - `openai:o3-pro-2025-06-10` - o3-pro-2025-06-10; scheduled shutdown 2026-12-11
  - `openai:chat-latest` - chat-latest
  - `openai:gpt-5.4-pro-2026-03-05` - gpt-5.4-pro-2026-03-05
  - `openai:o3-2025-04-16` - o3-2025-04-16; scheduled shutdown 2026-12-11
  - `openai:gpt-5.2-2025-12-11` - gpt-5.2-2025-12-11
  - `openai:gpt-3.5-turbo` - gpt-3.5-turbo; scheduled shutdown 2026-10-23
  - `openai:gpt-4o-2024-05-13` - gpt-4o-2024-05-13; scheduled shutdown 2026-10-23
  - `openai:gpt-5.6-terra` - gpt-5.6-terra
  - `openai:o3-mini` - o3-mini; scheduled shutdown 2026-10-23
  - `openai:gpt-5.4-nano` - gpt-5.4-nano; scheduled shutdown 2027-04-01
  - `openai:o1-pro-2025-03-19` - o1-pro-2025-03-19; scheduled shutdown 2026-10-23
  - `openai:gpt-4-turbo` - OpenAI GPT-4 Turbo; scheduled shutdown 2026-10-23
  - `openai:o3-pro` - o3-pro
  - `openai:gpt-4o-2024-08-06` - gpt-4o-2024-08-06; scheduled shutdown 2027-01-14
  - `openai:o3` - o3
  - `openai:gpt-4.1-mini-2025-04-14` - gpt-4.1-mini-2025-04-14; scheduled shutdown 2027-01-14
  - `openai:o3-mini-2025-01-31` - o3-mini-2025-01-31; scheduled shutdown 2026-10-23
  - `openai:gpt-5.1-2025-11-13` - gpt-5.1-2025-11-13
  - `openai:gpt-4-0613` - gpt-4-0613; scheduled shutdown 2026-10-23
  - `openai:gpt-4o` - OpenAI GPT-4o; scheduled shutdown 2027-01-14
  - `openai:gpt-4.1-mini` - gpt-4.1-mini; scheduled shutdown 2027-01-14
  - `openai:gpt-4o-mini` - OpenAI GPT-4o Mini; scheduled shutdown 2027-01-14
  - `openai:gpt-6-sol` - gpt-6-sol
  - `openai:gpt-5.2` - gpt-5.2
  - `openai:o1` - o1; scheduled shutdown 2026-10-23
  - `openai:gpt-5.4-nano-2026-03-17` - gpt-5.4-nano-2026-03-17
  - `openai:gpt-6.1-sol` - OpenAI GPT-6.1 Sol
  - `openai:o1-2024-12-17` - o1-2024-12-17; scheduled shutdown 2026-10-23
  - `openai:gpt-5.3-codex` - gpt-5.3-codex; scheduled shutdown 2027-04-01
  - `openai:gpt-5-search-api-2025-10-14` - gpt-5-search-api-2025-10-14
  - `openai:gpt-5` - OpenAI GPT-5
  - `openai:gpt-4.1-nano-2025-04-14` - gpt-4.1-nano-2025-04-14; scheduled shutdown 2026-10-23
  - `openai:gpt-4.1-2025-04-14` - gpt-4.1-2025-04-14; scheduled shutdown 2027-01-14
  - `openai:gpt-4o-mini-2024-07-18` - gpt-4o-mini-2024-07-18; scheduled shutdown 2027-01-14
  - `openai:gpt-4` - OpenAI GPT-4; scheduled shutdown 2026-10-23
  - `openai:gpt-5-nano-2025-08-07` - gpt-5-nano-2025-08-07; scheduled shutdown 2026-12-11
  - `openai:o4-mini` - o4-mini; scheduled shutdown 2026-10-23
  - `openai:gpt-6-astra` - OpenAI GPT-6 Astra
  - `openai:gpt-6-luna` - OpenAI GPT-6 Luna
  - `openai:gpt-5.6-cyber` - gpt-5.6-cyber
  - `openai:gpt-rosalind-research` - gpt-rosalind-research
  - `openai:gpt-rosalind-discovery` - gpt-rosalind-discovery
  - `openai:gpt-4-1106-preview` - gpt-4-1106-preview; scheduled shutdown 2026-10-23
- Yandex AI Studio:
  - `yandex:gpt-lite` - Yandex GPT Lite
  - `yandex:gpt-pro` - Yandex GPT Pro
  - `yandex:gpt-32k` - Yandex GPT Pro 32K
  - `yandex:aliceai-llm` - aliceai-llm
  - `yandex:aliceai-llm-flash` - aliceai-llm-flash
  - `yandex:yandexgpt-5.1` - yandexgpt-5.1
  - `yandex:yandexgpt-5-pro` - yandexgpt-5-pro
  - `yandex:yandexgpt-5-lite` - yandexgpt-5-lite
  - `yandex:deepseek-v4-flash` - deepseek-v4-flash
  - `yandex:deepseek-v4.1-flash` - deepseek-v4.1-flash
  - `yandex:qwen3-235b-a22b-fp8` - qwen3-235b-a22b-fp8
  - `yandex:gpt-oss-120b` - gpt-oss-120b
  - `yandex:gpt-oss-20b` - gpt-oss-20b
  - `yandex:qwen3.6-35b-a3b` - qwen3.6-35b-a3b
  - `yandex:yandexgpt-latest` - yandexgpt-latest
  - `yandex:yandexgpt-rc` - yandexgpt-rc
- xAI (Grok):
  - `xai:grok-4.20-0309-reasoning` - grok-4.20-0309-reasoning
  - `xai:grok-4.20-0309-non-reasoning` - grok-4.20-0309-non-reasoning
  - `xai:grok-4.20-multi-agent-0309` - grok-4.20-multi-agent-0309
  - `xai:grok-4.3` - grok-4.3
  - `xai:grok-4.5` - grok-4.5
  - `xai:grok-4.6` - grok-4.6
  - `xai:grok-4.7` - grok-4.7
  - `xai:grok-build-0.1` - grok-build-0.1

The catalogue was checked on **2026-10-22**. It includes available legacy,
preview, restricted-access, and dated snapshot models, not only current flagships.
Retired identifiers that merely redirect to replacements are excluded. Normal
provider-maintained rolling aliases are retained; they are not pinned snapshots.

This list mirrors the registry and `--list-models`. Account permissions, regional
availability, and model retirement dates still apply. Sources, API identifiers,
and exclusion evidence are recorded in [model-catalog.json](../resources/model-catalog.json).
