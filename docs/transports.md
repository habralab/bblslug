# Transports, protection and validation

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

File commands follow the [example setup](usage.md#running-the-file-examples). PHP examples assume
the import, credentials and input strings from the [usage guide](usage.md#php-library).

| Transport | Provider receives | Preserved locally |
| --- | --- | --- |
| Document (default) | The prepared document | Filtered URLs and selected HTML blocks |
| Text segments | A JSON object of text fragments | Line separators, edge whitespace and protected content |
| HTML segments | A JSON object of text fragments | Markup, non-language attributes and protected blocks |
| HTML segments inside JSON | Fragments from explicitly selected strings | HTML structure and every unselected JSON byte |

Every segment response must contain exactly the requested IDs with nonempty string values.
Missing, extra or duplicate IDs and introduced placeholders fail even with `--no-validate`.
Inputs without translatable fragments make no HTTP requests. Neither transport establishes translation
accuracy or completeness; splitting fragments can reduce context across lines or inline tags.

- [Text segments](#detect-missing-fragments-in-plain-text)
- [HTML segments](#keep-html-markup-local-during-translation)
- [HTML inside JSON](#translate-selected-html-strings-inside-json)
- [Text packet count](#limit-plain-text-segment-batches) and [HTML packet count](#limit-html-segment-batches)
- [Packet byte cap](#bound-segment-packets-by-serialized-bytes) and [abbreviation hints](#keep-abbreviations-with-their-context)
- [Checkpoints](#resume-confirmed-successful-segment-packets)
- [Operation deadline](#limit-the-whole-translation-operation)
- [Filters inside JSON](#filters-inside-json)
- [Validation](#validation-and-opting-out)

## Detect missing fragments in plain text

Use `textTransport: 'segments'` / `--text-transport=segments` with `format=text`.
Each nonempty line is translated separately; protected placeholders split it into additional fragments.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --source=input.txt --translated=output.txt
```

LF, CRLF and CR separators, blank lines, edge whitespace and protected bytes remain unchanged.
Text stays literal: markup and HTML entities are not decoded. Returned fragments cannot introduce CR
or LF. UTF-8 input and a JSON template in the selected prompt group are required; caller-supplied
`response_schema` is incompatible with segments.

The default is `textTransport: 'document'`. Document mode rejects empty replies for nonempty input
but can omit paragraphs inside a nonempty reply. Segment mode detects missing IDs, not missing meaning
within a returned fragment. Output budgets and model limits apply to each serialized fragment payload.
Use a count or byte cap below to divide it into packets. Dry-run returns prepared plain text.

## Keep HTML markup local during translation

Use `htmlTransport: 'segments'` / `--html-transport=segments` to translate text without asking the
provider to rewrite markup. The default is `document`.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=html \
  --html-transport=segments --filters=url,html_code,html_pre \
  --source=input.html --translated=out.html
```

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'html',
    modelKey: 'google:gemini-flash-latest',
    text: $html,
    filters: ['url', 'html_code', 'html_pre'],
    htmlTransport: 'segments',
);
```

| Content | Segment behavior |
| --- | --- |
| Text nodes and `title`, `alt`, `aria-label` values | Translated |
| Tags, other attributes, comments and declarations | Preserved |
| `pre`, `code`, `script`, `style`, `xmp`, `iframe`, `noembed`, `noframes`, `noscript`, SVG and MathML | Preserved, including self-closing foreign roots |
| `plaintext` | Preserves the remainder of the document |
| `title` and `textarea` contents | Treated as text, including literal less-than signs |

Edge whitespace and placeholder positions stay unchanged. Entities are decoded for translation;
changed values are escaped for their HTML context. Unchanged values retain their original spelling.
CSS visibility and browser scripting state are not evaluated. Incomplete markup and unsupported
protected-block forms fail before HTTP.

This mode uses the prompt group's JSON template and the provider's JSON transport. Custom groups
need a JSON template. DeepL uses its JSON Document API by default; `json_transport=text_batch` is an
explicit alternative. Model length limits apply to the serialized fragment payload, while `prepared`
and length statistics still describe HTML. CLI dry-run checks extraction and returns prepared HTML.
The mandatory ID checks above apply alongside normal HTML validation.

## Translate selected HTML strings inside JSON

Use `htmlTransport: 'segments'` with `htmlJsonPointers` for `json` or `linedJson` input:

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'google:gemini-flash-latest',
    text: $json,
    filters: ['url', 'html_code'],
    htmlTransport: 'segments',
    htmlJsonPointers: ['/body', '/items/0/html'],
);
```

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=json \
  --html-transport=segments --html-json-pointer=/body \
  --html-json-pointer=/items/0/html --filters=url,html_code \
  --source=input.json --translated=out.json
```

Only fragments from selected strings are translated. Unselected values, keys, whitespace, property
order and number spellings remain byte-for-byte unchanged. Filters apply separately inside each
selected string. Changed strings are JSON-encoded during reconstruction and may use different escape
spellings; unchanged strings retain their original JSON token.

Selectors use RFC 6901 JSON Pointer syntax:

| Pointer | Selection |
| --- | --- |
| `/body` | A string property |
| `/items/0/html` | A string in an array element |
| `/a~1b`, `/a~0b` | Keys containing `/` or `~` |
| Empty string (`--html-json-pointer ''`) | A root JSON string |

Repeated selectors are deduplicated. Wildcards and implicit traversal into JSON stored inside strings
are unsupported. Missing or non-string selections, duplicate source object keys and malformed JSON
fail before HTTP, including dry-run and `--no-validate`. Selected HTML is also checked before and after
translation when validation is enabled.

`htmlJsonPointers` selects what to **translate**. `validateHtmlJsonPointers` only selects what to
**check**; both can be used together. JSON segments require at least one translation selector.
Document mode rejects translation selectors; `structured_json` and `response_schema` cannot be used
with segments because they describe the outer document rather than its fragments.

CLI dry-run returns filtered JSON. PHP exposes that value in `prepared`, while its dry-run `result`
remains the original source. Model limits apply to the fragment payload, excluding unselected
metadata. The normal JSON document mode translates all string values.

## Limit plain text segment batches

Set `textSegmentBatchSize: 25` / `--text-segment-batch-size=25` with text segments to send at most
25 fragments per packet. The default `null` keeps one packet.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 \
  --source=input.txt --translated=output.txt
```

The following rules apply to **both text and HTML batching**:

- Packets run sequentially. All model-length and request-body checks run before the first HTTP call.
- Each response must pass its packet's integrity checks before the next is sent. A failure reports its
  packet number and earlier completed count, stops the job and returns no partial document.
- Existing CLI destination files are kept until the complete translation succeeds. Earlier successful
  packets may already be billed; use a [checkpoint](#resume-confirmed-successful-segment-packets) to retain them.
- Prompts, context, timeouts and output-token budgets apply per packet. Requests are not retried or
  switched to another model or transport. DeepL may use several HTTP exchanges for one document packet.
- Count caps limit fragments, not bytes. Use a [byte cap](#bound-segment-packets-by-serialized-bytes)
  to split oversized fragments; otherwise an oversized fragment fails before HTTP.

With a count or byte cap, the PHP result includes `batches`: ordered summaries containing `index`
(1-based), `segments`, `httpStatus` and `consumed`. Top-level `consumed` sums reported usage;
`httpStatus` is the last packet's status. `rawResponseBody` is a JSON array of raw response **strings**
in packet order. Verbose diagnostics identify packets.

API dry-run records planned packets with status 0 and sends no HTTP. CLI dry-run returns prepared
source. Input without fragments returns empty `batches` and raw response arrays. Without a cap,
the single-response result shape is unchanged.

## Limit HTML segment batches

Set `htmlSegmentBatchSize: 25` / `--html-segment-batch-size=25` with HTML segments to send at most
25 fragments per packet. It supports HTML and selected HTML strings inside `json`/`linedJson`.
The default is `null` (one packet). The [shared batching rules](#limit-plain-text-segment-batches) apply.

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'xai:grok-4.7',
    text: $json,
    htmlTransport: 'segments',
    htmlJsonPointers: ['/body'],
    htmlSegmentBatchSize: 25,
);
```

```bash
vendor/bin/bblslug --model=xai:grok-4.7 --format=html \
  --html-transport=segments --html-segment-batch-size=25 \
  --source=input.html --translated=out.html
```

For models declaring `native_json_schema` (Yandex GPT Lite/Pro and Grok 4.7), the standard `translator`
group requests a required-key native schema for each text or HTML packet, including an unbatched
request. Each value must be a nonempty string. Custom groups, a `translator` group without a
`structured_json` template, and explicit `json_transport=markers` retain their configured protocol.
Local integrity checks still apply. Caller-supplied `response_schema` is incompatible with segments.

## Bound segment packets by serialized bytes

Set `segmentBatchMaxBytes: 4000` / `--segment-batch-max-bytes=4000` with text or HTML segments.
The positive integer cap works alone or together with a count cap; both limits apply. The default is
`null` (no byte cap).

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --segment-batch-max-bytes=4000 \
  --source=input.txt --translated=output.txt
```

The cap measures serialized fragment JSON in UTF-8 bytes, including keys, punctuation and escaping.
It excludes the provider envelope, prompt and output. Leave room for those and configure the output
budget separately through [provider options](providers.md#model-defaults). Full request guards still
run before HTTP.

Oversized fragments split at sentence-ending punctuation (`.`, `!`, `?`, `。`, `！`, `？`, optionally
followed by closing quotes, `)` or `]`) and whitespace. Longer sentences fall back to Unicode whitespace
boundaries. Fragments that already fit keep their granularity. Dotted initials, leading list numbers
and lowercase continuations stay together where the cap permits. Lexical abbreviations can still
mark a sentence boundary; use hints below for known tokens.

Words and tokens are never cut in the middle. An unbreakable word, URL or other value that cannot fit
fails before HTTP. Source separators between pieces remain unchanged. Each piece has its own required
ID, and packet summaries count those pieces. This also applies to selected HTML inside JSON.

Smaller pieces reduce context and can increase request count; they do not prove translation
completeness. CLI dry-run checks the plan while returning prepared source. The
[shared batching result fields](#limit-plain-text-segment-batches) apply; a byte cap alone does not
create checkpoints or enable retries.

## Keep abbreviations with their context

Set `segmentAbbreviations: ['Dr.', 'Prof.', 'ул.']` or repeat `--segment-abbreviation=TOKEN` alongside
a segment byte cap. The list is empty by default.

Matching ignores case and surrounding quotes, parentheses and brackets. Tokens must be nonempty
UTF-8, contain no whitespace and end in a period. A configured abbreviation stays with the next token
when that run fits a fresh piece; consecutive configured abbreviations form one run. The hard byte
cap still applies when the run cannot fit. Hints operate inside a fragment and do not merge lines,
HTML nodes or separately selected JSON strings.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --segment-batch-max-bytes=2000 \
  --segment-abbreviation=Dr. --segment-abbreviation=Prof. \
  --source-lang=EN --target-lang=DE \
  --source=input.txt --translated=output.txt
```

```php
$result = \Bblslug\Bblslug::translate(
    apiKey: $key,
    format: 'text',
    modelKey: 'openai:gpt-6-luna',
    text: 'Dr. Smith met Prof. Jones.',
    sourceLang: 'EN',
    targetLang: 'DE',
    textTransport: 'segments',
    segmentBatchMaxBytes: 2000,
    segmentAbbreviations: ['Dr.', 'Prof.'],
);
```

Keep the same hints when resuming a checkpoint; changing them changes the packet plan.

## Resume confirmed successful segment packets

Set `checkpointPath` / `--checkpoint` alongside a segment count or byte cap. Checkpoints support text,
HTML and selected HTML strings inside JSON.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 \
  --checkpoint=translation.checkpoint.json \
  --source=input.txt --translated=output.txt
```

Repeat the same command after a failed run. A checkpoint retains the contiguous prefix of confirmed,
validated packets and sends only the remainder. A completed checkpoint rebuilds the result without
HTTP. The final CLI destination is written only after complete success.

| Change on resume | Requirement |
| --- | --- |
| Source, model settings, filters, prompts, validation flags, packet layout or library source code | Use a new checkpoint path |
| API key, proxy, HTTP timeouts or response cap, operation deadline | Existing checkpoint may be reused |

Identity mismatches and corrupt complete records fail before new HTTP and preserve the file.
Each invocation gets a fresh operation time budget. Do not edit saved identity metadata to bypass checks.

Storage uses only the supplied path, which can be outside a read-only library installation. Parent
directories must exist; state must be writable. New state and lock files use `0600`. A nonblocking
exclusive lock prevents concurrent use; its empty `.lock` file remains after release. State/lock
symlinks are rejected, and CLI paths must not alias source, prepared output or destination.
Dry-run and inputs without fragments do not access checkpoints. No path means no state or spool files.

New checkpoints are version-2 JSONL journals and require PHP's `fsync()`. A confirmed append is flushed
and synced. A trailing record without a final newline is removed on reopening after identity and
complete records pass validation. A corrupt newline-terminated record is rejected. Existing version-1
JSON snapshots keep their format and use atomic replacement; format support does not bypass identity
checks. There is no automatic migration or compaction.

After a write or sync error, repeat the operation to reopen the journal. A fully written valid record
may be recovered; an incomplete record may require another request. A request can be billed before
its result is saved, so resuming unconfirmed packets can incur another charge. In-flight DeepL uploads
are not resumed.

Checkpoints contain translated content, usage and identity/checksum metadata. Credentials, raw
responses and debug logs are not stored; treat the saved translations as private data.

With checkpointing, packet summaries include `reused`; the result adds `reusedBatches` and
`consumedThisRun`. `consumed` covers the whole document, including saved usage; `consumedThisRun`
covers new successful requests and is what the CLI displays. Reused packets keep their saved status
and have empty debug fields and an empty raw-response string because responses were not stored.

## Limit the whole translation operation

Set `operationTimeoutSeconds: 60` / `--operation-timeout=60` for a shared budget in positive integer
seconds. The default `null` leaves only the existing request and provider-workflow timeouts.

```bash
vendor/bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 --operation-timeout=60 \
  --source=input.txt --translated=output.txt
```

The budget starts at `translate()` entry and covers preparation, requests, parsing, reconstruction
and callbacks. Every HTTP exchange uses the smaller of its own timeout and the remaining budget.
DeepL upload, polling waits and download share that budget; it does not restart per packet.

Expiry prevents further requests and rejects a late result. Synchronous processing and callbacks
cannot be interrupted mid-call, but their elapsed time counts. A local timeout does not cancel
remote provider work. The [batch failure rules](#limit-plain-text-segment-batches) apply.
CLI dry-run checks the option without sending requests.

## Filters inside JSON

For valid JSON, `url` and `html_TAG` filters match decoded string values, including JSON stored inside
strings. Protected fragments restore their exact original source bytes, including Unicode escapes,
escaped slashes and quoted attributes. Keys, number spellings, whitespace and unrelated escapes are
not reserialized. URL-shaped keys stay unchanged; separate JSON values cannot form one URL or HTML
block. Ordinary text's literal `\uXXXX` notation is not decoded.

| Selected HTML content | Filter boundary |
| --- | --- |
| Non-void tags, including tags with optional HTML end tags | Require an explicit closing tag; `<code/>` is not a complete block |
| Void elements such as `br`, `img` and legacy `param` | One opening tag, including attributes |
| `plaintext` | The remainder of the text, or decoded JSON string value |

Comments, CDATA, attributes and other raw-text elements are opaque to selected-tag matching.
See the [HTML plaintext rule](https://html.spec.whatwg.org/multipage/parsing.html#plaintext-state).
An unclosed selected block or incomplete selected opening tag fails before HTTP, including dry-run
and `--no-validate`. Errors name the tag without dumping its contents; CLI source, prepared and
output files remain unchanged on filter failure. Filters require valid JSON for JSON-aware matching
and do not repair malformed JSON.

## Validation and opting out

Container validation is on by default. Set `validate: false` / `--no-validate` to disable it.
Filter placeholder integrity and segment response checks remain mandatory.

| Check | Required behavior |
| --- | --- |
| JSON structure | All properties and list entries, including `null`, remain; `{}` and `[]` retain their types. Object order may change; list order matters. |
| JSON scalars | Numbers, booleans and `null` retain exact source tokens. Even `1.0` → `1e0` fails; large numbers are not rounded for comparison. |
| JSON strings | Nonempty values cannot become empty or Unicode-whitespace-only. Already empty values may remain empty or gain text. |
| HTML structure | Tag order/nesting, attribute presence, non-language values, comments and doctype remain. Text and `title`/`alt`/`aria-label` may change. |
| HTML content | Nonempty language attributes and prose containers such as paragraphs cannot become empty. Text may move across inline elements inside a container. |
| Markdown fences in HTML | Added or changed fences fail. Delimiter type, length, count, order and boundary labels remain; equivalent entities compare equally. |
| Filter placeholders | Each prepared marker retains its occurrence count; order may change. Missing, duplicated or unexpected markers fail. |

HTML comparison uses libxml's parsed tree. Attribute order and equivalent entities are accepted;
comparison does not rewrite the returned HTML. Code/preformatted, script/style, legacy raw-text,
SVG and MathML content is excluded from prose-loss checks, so retained code cannot hide an emptied
paragraph. CSS and translation meaning are not evaluated. A nonempty document, string or fragment
can still lose meaning without failing these checks. Use filters for content that must stay exact.

Enable `validateEmbeddedJson: true` / `--validate-embedded-json` to check JSON objects or arrays
encoded inside strings, recursively using the same structure, scalar and nonempty-string rules.
This supports `json`, `linedJson` and `structured_json`. Ordinary strings, malformed JSON-like source
text and encoded scalars such as `123`, `true` or `null` remain ordinary translatable text. The option
is off by default and is disabled by `--no-validate`. Errors identify the affected property/index;
output is not repaired or reserialized.

Select HTML-in-JSON locations explicitly with `validateHtmlJsonPointers`:

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'openai:gpt-6-luna',
    text: $json,
    validateHtmlJsonPointers: ['/body', '/items/0/html'],
);
```

In the CLI, repeat `--validate-html-json-pointer=/body`. These checks support `json`, `linedJson` and
`structured_json`, using the [same JSON Pointer syntax](#translate-selected-html-strings-inside-json)
as translation selectors; field names are not inferred. Selected HTML is checked before and after
translation. Invalid, missing or non-string source selections fail before HTTP, including dry-run.
`--no-validate` disables the checks. Unselected strings remain ordinary translatable content.

A mismatch raises a validation error rather than repairing or filling in output. CLI failures leave
existing destination files intact and write no partial translation to STDOUT. To skip container checks:

```bash
vendor/bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=html \
  --no-validate \
  --source=input.html \
  --translated=out.html
```
