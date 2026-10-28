# Provider options and protocols

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

File commands follow the [example setup](usage.md#running-the-file-examples).
PHP fragments assume the import, credentials and input variables from the [usage guide](usage.md#php-library).

Choose a model from the [model catalogue](models.md) and configure its provider options below. Account access and provider limits apply. Failed requests are not automatically retried or sent to another model.

- [Model defaults](#model-defaults)
- [Native structured JSON](#native-structured-json)
- [Google native JSON transport](#google-native-json-transport)
- [Grok 4.7 request settings](#grok-47-request-settings)
- [Yandex completion status](#yandex-completion-status)
- [Yandex native JSON transport](#yandex-native-json-transport)
- [Yandex HTML response prefixes](#yandex-html-response-prefixes)
- [DeepL JSON transport](#deepl-json-transport)
- [DeepL HTML placeholders](#deepl-html-placeholders)

## Model defaults

The GPT-6 defaults use Chat Completions with `reasoning_effort=low` and omit `temperature`.
Responses-only models use the Responses API with its own request and usage fields.
Older OpenAI identifiers remain available; check your account for access.

OpenAI's `max_completion_tokens` covers both reasoning and visible output. A request can
reach that limit before producing any visible translation, even with `reasoning_effort=low`.
A `length` finish reason or an incomplete Responses result is rejected, including when
the visible text contains translation markers. GPT-6 Luna also supports
`--variables=reasoning_effort=none,max_completion_tokens=8192` (PHP:
`variables: ['reasoning_effort' => 'none', 'max_completion_tokens' => 8192]`) for callers
who choose to disable reasoning. Model and provider support for this option varies. See [GPT-6 Luna settings](https://developers.openai.com/api/docs/models/gpt-6-luna).

The Claude 5.5 entries use the [Anthropic Messages API](https://platform.claude.com/docs/en/api/messages/create)
with `effort=low`, no `temperature`, and an initial output budget of 4096 tokens.
Use `--variables=maxTokens=8192` to override the output budget. Use `--variables=effort=low,maxTokens=8192` for models supporting effort; older
Claude snapshots omit unsupported thinking and effort parameters.

DeepL Free and Pro are service tiers. Select any documented translation engine
with `--variables=model_type=quality_optimized`, `prefer_quality_optimized`, or
`latency_optimized`. The provider default applies when omitted. These options select an engine within the chosen tier.

Yandex models using the OpenAI-compatible API retain the `yandex:` prefix and the
same credentials/folder variables. The model determines the endpoint.

## Native structured JSON

Use `format: 'structured_json'` with an explicit JSON Schema in
`variables['response_schema']`. This mode is implemented for OpenAI Chat
Completions/Responses, Google Gemini, Yandex Foundation/Chat Completions, and
xAI Chat Completions/Responses; the selected model must support native
structured output. Unsupported providers and legacy OpenAI Completions are
rejected locally. Provider schema restrictions and access rules still apply.

```php
$translation = \Bblslug\Bblslug::translate(
    apiKey: $apiKey,
    format: 'structured_json',
    modelKey: 'openai:gpt-6-luna',
    text: '{"message":"Hello"}',
    targetLang: 'DE',
    variables: ['response_schema' => [
        'type' => 'object',
        'properties' => ['message' => ['type' => 'string']],
        'required' => ['message'],
        'additionalProperties' => false,
    ]]
);
```

The CLI reads a schema from a JSON file. The bundled schema describes the bundled JSON input:

```bash
vendor/bin/bblslug --model=openai:gpt-6-luna --format=structured_json \
  --response-schema=translation.schema.json --source=input.json --target-lang=DE
```

Input and output are JSON **strings**. Native mode sends the
schema unchanged and reads the response without translation markers. OpenAI, xAI and Yandex Chat Completions use strict schema mode by default;
PHP callers may override `strict` with a boolean and `response_schema_name` with a name of 1–64 letters,
digits, underscores, or dashes. A schema supplied with `format: 'json'` also enables
native mode. The library still validates returned JSON syntax and source structure;
it does not independently implement every JSON Schema constraint.

`linedJson` selects an alternative JSON translation prompt with the same container
validation as `json`. It processes one JSON document, not JSON Lines, and does not
silently repair malformed output. Neither mode assumes particular field names
or a document domain. Use the existing context or a custom prompt for application
rules. Custom prompt templates must provide the requested format; native mode
requests the `structured_json` template.

## Google native JSON transport

Google JSON requests default to `json_transport=auto`. For registered Gemini models
with a declared native JSON capability, `json` and `linedJson` with the standard
`translator` group request `application/json` output without translation markers
or a required JSON Schema. Current Gemini entries declare this capability; Gemma and custom configurations
without it retain markers.

```bash
printf '%s' '{"title":"Hello"}' | vendor/bin/bblslug --model=google:gemini-flash-latest \
  --format=json --source-lang=EN --target-lang=DE
```

Text and default document-mode HTML retain markers. [HTML segment mode](transports.md#keep-html-markup-local-during-translation)
uses the JSON transport instead. Custom prompt groups retain their existing marker
contract in auto mode; a custom `translator` group without `structured_json` also
retains markers. To select a protocol explicitly, pass
`variables: ['json_transport' => 'native']` or `['json_transport' => 'markers']`,
or CLI `--variables=json_transport=markers`. A failed HTTP request or invalid
response never triggers an automatic protocol retry.

Explicit native mode requests the selected group's `structured_json` template;
missing templates fail explicitly. `structured_json` format still requires
`response_schema`, and supplying a schema selects native output even in auto mode.
Explicit markers conflict with a schema. Syntax, source structure, exact non-string
values, placeholders, and opt-in embedded JSON validation remain enabled by default.
Native JSON syntax does not guarantee semantic correctness of translated strings.
See the provider's [JSON output documentation](https://ai.google.dev/api/generate-content#v1beta.GenerationConfig).
Google transport choices are separate from DeepL's transport choices.

## Grok 4.7 request settings

`xai:grok-4.7` uses the Responses API with
`reasoning_effort=low`. Its output budget can be overridden with
`variables: ['max_completion_tokens' => 8192]`; the driver maps that option to
`max_output_tokens` on this endpoint. For Grok's Responses API, this caps the visible
answer; reasoning and function-call tokens do not count toward that cap. Reasoning
can still increase latency. For large packets, set an appropriate `BBLSLUG_HTTP_TIMEOUT`
alongside the shared operation deadline, or reduce the packet byte cap.
`none` is not accepted by this model. Other Grok entries use their configured protocol and settings.
For JSON formats, `response_schema` selects native JSON Schema output with the
existing `structured_json` template and no markers. Responses uses `text.format`;
Chat Completions uses `response_format`. Without a schema, document-mode requests
retain their marker contract. See [xAI structured outputs](https://docs.x.ai/developers/model-capabilities/text/structured-outputs).

See [xAI reasoning settings](https://docs.x.ai/developers/model-capabilities/text/reasoning) and
[output limits and timeouts](https://docs.x.ai/developers/model-capabilities/text/best-practices#streaming-and-timeouts).

Grok can return HTTP 200 and Responses status `completed` with only the output markers
and no translation body. Bblslug rejects that response for nonempty input, preserves
an existing CLI destination, and does not retry. This is distinct from a truncated
response; increasing the output limit is not a verified fix for this case.

For fragment-level required output, explicitly select
[text segments](transports.md#detect-missing-fragments-in-plain-text) or
[HTML segments](transports.md#keep-html-markup-local-during-translation), including selected HTML inside JSON.
With the standard `translator` prompt, Grok 4.7 uses a native JSON Schema for each packet,
requiring every fragment identifier and nonempty string values. This provides a stronger
output contract; it does not prove that all meaning within each string was translated.
The default document transport and explicit marker requests retain the marker protocol.

## Yandex completion status

Yandex completion metadata is checked before text is extracted, even when translation
markers are present. Foundation API alternatives must have
`ALTERNATIVE_STATUS_FINAL`; Chat Completions and legacy completion responses must
have `finish_reason=stop`, when that field is supplied. Truncated, partial,
content-filtered, tool-call, unknown, and malformed completion states are rejected.
An explicit Chat Completions refusal is also rejected. Only the first alternative
is considered; another alternative does not replace a rejected one.

Older response shapes without completion metadata remain accepted for compatibility;
the selected framing and enabled output validation still apply. CLI provider failures leave the
source and an existing destination file unchanged. No automatic retry is performed.
The Yandex API format controls target JSON; it does not add an HTML output mode
or strip Markdown wrappers.

## Yandex native JSON transport

Yandex supports JSON-object generation through
[`jsonObject` in the Foundation API](https://aistudio.yandex.ru/ru/docs/ai-studio/text-generation/api-ref/TextGeneration/completion)
and [`response_format: {type: json_object}` in Chat Completions](https://aistudio.yandex.ru/ru/docs/ai-studio/api/Chat-Completions/createChatCompletion).
These controls use the `structured_json` prompt without translation markers. JSON fences
and escaping are not repaired.

`json_transport=auto` is the default. It selects native mode for `json`/`linedJson`
only when the source root is an object, the model declares `native_json`, and the
standard `translator` group has a `structured_json` template. Native capability is
currently declared for `yandex:gpt-lite` and `yandex:gpt-pro`. HTML segment packets
are objects, so they qualify automatically. Text, document-mode HTML, array/scalar
JSON roots, other models, and custom prompt groups retain their marker protocol.

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'yandex:gpt-pro',
    text: $json,
    variables: ['folder_id' => $folderId, 'json_transport' => 'native'],
);
```

CLI overrides use `--variables=json_transport=native` or `json_transport=markers`.
Explicit native mode requires `json`/`linedJson` and a valid object-root source;
custom groups need a `structured_json` template. Explicit markers select the marker protocol. `structured_json` requires `response_schema`. Supplying a schema
with `json` or `linedJson` also selects native output: Foundation uses `jsonSchema.schema`,
and Chat Completions uses `response_format.type=json_schema`. Yandex native requests
require an object-root source. Explicit markers conflict with a response schema.
There is no fallback or retry when a native request is rejected.
See [Yandex schema output](https://aistudio.yandex.ru/en/docs/ai-studio/operations/generation/completions-structured).

The response must be a JSON object even with container validation disabled.
Truncation, content-filter, refusal, and completion-state checks happen first.
Enabled schema/scalar checks and mandatory segment checks still reject changed,
missing, duplicated, or empty values as appropriate. `rawResponseBody` remains the raw provider response
(or the ordered raw-response array when batching is enabled). Chat Completions
usage is preserved from its top-level `usage` field.

Native JSON does not guarantee completeness, translation fidelity, or acceptance by the provider's content filter.

## Yandex HTML response prefixes

For HTML input whose prepared text contains no Markdown fence, the Yandex driver
handles the opening-only artifact observed in Lite responses. It removes exactly
one initial line containing three backticks and an optional `html` label. LF and
CRLF, whitespace around the label, and `HTML` casing are supported. No other fence
may remain in the response. The remaining HTML still passes placeholder, syntax,
and structure validation; the original HTTP response remains in `rawResponseBody`.

The source check considers backtick/tilde triples anywhere in prepared HTML,
including HTML entity representations. Protected code blocks are placeholders at
this stage and are restored unchanged afterward. If visible source fences are
present, this compatibility handling is
disabled and their content is not stripped. Added paired, trailing-only, nested,
inline, tilde, or unfamiliar labelled fences fail explicitly for fence-free input,
also when container validation is disabled. Text and JSON responses are unchanged.
This is not a Markdown parser or general repair of model output.

Low-level driver integrations must pass the request’s `responseContext` to
`parseResponse()` for this handling. The facade forwards it automatically.

## DeepL JSON transport

For `json` and `linedJson`, DeepL Free and Pro use the native
[Document API](https://developers.deepl.com/api-reference/document/upload-and-translate-a-document):
upload the prepared JSON as a multipart `.json` file, poll its status, then download
the translated JSON. The result goes through the existing placeholder restoration
and JSON syntax, structure, exact non-string value, and optional embedded JSON checks.

The source file limit is 1 MB; the facade also enforces the model registry's
existing character limit. `variables: ['document_timeout' => 120]` sets a
positive integer timeout in seconds: the upload has this HTTP timeout, and polling
plus downloading share a separate deadline of that duration. CLI variables may use
a canonical positive integer string, for example `--variables=document_timeout=45`.
Polling ignores the provider's estimated remaining time. An HTTP error, processing error, malformed
status, or deadline expiry stops the operation. Uploads and downloads are not
retried automatically. Document IDs and encryption keys are masked in debug logs
and exceptions; `rawResponseBody` contains the downloaded document.

The Document API has no `context` or `model_type` parameter. These options cause
an explicit error in native JSON mode. To retain text-endpoint options or the
previous local reconstruction behavior, select
`variables: ['json_transport' => 'text_batch']` explicitly. No automatic fallback
occurs after an unsuccessful document request. Text and default document-mode
HTML retain their existing transport. HTML segment mode uses the JSON transport.

In `text_batch` mode, decoded nonempty string values are sent as an
[ordered text batch](https://developers.deepl.com/api-reference/translate/request-translation).
Keys, container whitespace, and exact non-string tokens remain local; returned
strings are JSON-encoded before insertion. Encoded JSON objects/arrays are processed
recursively. This mode has a 128 KiB request-body limit and can change punctuation
inside technical strings, as observed with ASCII quotes in a path.

Both modes finish locally (`httpStatus` is `0`) when no translatable string values
remain. Empty strings, whitespace-only values, and placeholder-only values are
not translated.

Low-level integrations must forward each request's `responseContext` to
`parseResponse()`. For native JSON, they must also execute its optional `workflow`
continuation after the upload, using authenticated HTTP with the supplied timeout
and mask patterns, then parse the downloaded response. The optional `localResult`
means no HTTP exchange is needed. `Bblslug::translate()` handles this automatically.

## DeepL HTML placeholders

For HTML requests, markers in normal text are temporarily wrapped in spans using
[DeepL's `translate="no"` support](https://developers.deepl.com/docs/translate/translating-html).
Only wrappers created for that request are removed before restoring the original
fragments. User markup is not reserialized. Markers in attributes, comments, CDATA,
and raw-text/RCDATA elements such as `script`, `style`, `title`, and `textarea`
are left without these wrappers.

A missing, duplicated, malformed, or modified transport wrapper causes an error.
Post-validation still compares the restored HTML tree, so the wrappers do not
bypass structure checks. The 128 KiB request-body limit is checked
after wrapping so transport overhead cannot silently exceed the provider limit.
