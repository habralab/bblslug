# Transports, protection and validation

[README](../README.md) · [Usage](usage.md) · [Transports](transports.md) · [Providers](providers.md) · [Models](models.md)

File commands use a [prepared repository checkout](usage.md#running-the-file-examples).
PHP fragments assume the import, credentials and input variables from the [usage guide](usage.md#php-library).

Document transport sends the prepared document to the provider. Optional segment transport keeps source structure local and checks responses by fragment identifier. Select these options explicitly; neither mode proves translation fidelity.

- [Detect missing fragments in plain text](#detect-missing-fragments-in-plain-text)
- [Keep HTML markup local during translation](#keep-html-markup-local-during-translation)
- [Translate selected HTML strings inside JSON](#translate-selected-html-strings-inside-json)
- [Limit plain text segment batches](#limit-plain-text-segment-batches)
- [Limit HTML segment batches](#limit-html-segment-batches)
- [Bound segment packets by serialized bytes](#bound-segment-packets-by-serialized-bytes)
- [Resume confirmed successful segment packets](#resume-confirmed-successful-segment-packets)
- [Limit the whole translation operation](#limit-the-whole-translation-operation)
- [Filters inside JSON](#filters-inside-json)
- [Validation and opting out](#validation-and-opting-out)

## Detect missing fragments in plain text

Use `--format=text --text-transport=segments` (PHP: `textTransport: 'segments'`)
to translate each nonempty source line through the provider's JSON transport.
Bblslug requires exactly one nonempty string for every fragment identifier and
reassembles the text in source order. Missing, extra, duplicate or invalid values
fail even with `--no-validate`. Returned fragments may not introduce CR or LF.
Lines containing protected placeholders are split around those placeholders;
their source bytes remain local.

```bash
bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --source=samples/quickstart/input.txt --translated=output.txt
```

Line separators (LF, CRLF and CR), blank lines, and leading/trailing Unicode
whitespace stay unchanged. Text remains literal: HTML entities and markup are not
decoded or escaped. Existing filters still apply and restore locally. Dry-run
returns prepared plain text; whitespace-only or placeholder-only input needs no
HTTP request. Segment mode requires valid UTF-8 and rejects `response_schema`.

The default is still `textTransport: 'document'` / `--text-transport=document`.
Document mode does not detect omitted paragraphs. Empty or whitespace-only
provider translations for nonempty prepared input are rejected, including with
`--no-validate`; dry-run and empty/whitespace-only input are exempt. This catches
empty replies, not omissions inside a nonempty reply. Segment mode detects missing
fragment identifiers, but cannot prove that every sentence within a returned
fragment was translated, or that the translation is accurate. Translating lines
separately may reduce fluency across line boundaries. Custom prompt groups need
a JSON template. JSON payload overhead counts toward model limits. Requests stay unsplit unless
`textSegmentBatchSize` / `--text-segment-batch-size` is set (below). Output budgets
apply to each request. `--html-segment-batch-size` applies only to HTML segments.
There is no automatic retry or fallback to document mode.

## Keep HTML markup local during translation

The optional `segments` transport sends text fragments as a JSON object with
stable identifiers. Bblslug reconstructs the HTML from the original source slices;
the provider does not rewrite its tags or non-language attributes.

```bash
bin/bblslug --model=google:gemini-flash-latest --format=html \
  --html-transport=segments --filters=url,html_code,html_pre \
  --source=samples/quickstart/input.html --translated=out.html
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

Text nodes and `title`, `alt`, and `aria-label` attribute values are translated.
`pre`, `code`, scripts, styles, legacy raw-text elements, SVG/MathML, comments,
and declarations remain local. `plaintext` protects the remainder of the document.
`title` and `textarea` contents are handled as text, including literal less-than
signs. Placeholder markers remain at their source positions and are not sent.
Original whitespace at fragment edges is retained. Entities are decoded for
translation; changed values are escaped for their HTML context, including unquoted
attributes. An unchanged value keeps its original entity spelling.
CSS visibility and browser scripting state are not evaluated.

The response must contain exactly the expected identifiers, without duplicates,
and a nonempty string for every fragment. These transport checks remain active
with `--no-validate`; they cannot restore an incomplete response safely. Output is
still subject to the normal HTML checks unless validation is disabled. An input
with no translatable fragments skips HTTP. CLI dry-run validates extraction but
continues to return prepared HTML, not the internal JSON payload.

The default remains `htmlTransport: 'document'` / `--html-transport=document`.
`segments` supports HTML input and selected HTML strings in JSON (below). It uses
the selected prompt group's JSON template and the provider's JSON transport. A custom HTML-only prompt group needs
a JSON template for this mode. DeepL uses its JSON Document API by default;
`json_transport=text_batch` remains an explicit alternative with its existing
batch limits. Length checks apply to the serialized JSON payload; returned `prepared` text
and length statistics still describe HTML. Requests remain unsplit unless explicit
batching is enabled below. There is no automatic retry or fallback to document mode.

Fragment translation can reduce fluency where grammar requires moving words
across inline tags. Keeping every fragment does not prove translation fidelity.
Extraction is conservative: incomplete markup and unsupported protected-block
forms fail before HTTP. SVG/MathML blocks, including self-closing roots, stay
local and are restored byte for byte.

## Translate selected HTML strings inside JSON

Use `htmlTransport: 'segments'` with explicit `htmlJsonPointers` to translate
only selected HTML string values. Supported formats are `json` and `linedJson`.

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
bin/bblslug --model=google:gemini-flash-latest --format=json \
  --html-transport=segments --html-json-pointer=/body \
  --html-json-pointer=/items/0/html --filters=url,html_code \
  --source=samples/quickstart/input.json --translated=out.json
```

Only HTML fragments from those strings are sent. All unselected values, keys,
whitespace, property order, and numeric tokens remain byte-for-byte unchanged.
Filters apply separately inside selected strings, leaving unselected fields alone.
Each HTML string has its own scope, including raw-text/`plaintext` handling.
Fragment identifiers are unique across the request. Reconstruction escapes each
changed HTML string as JSON; an unchanged string retains its original JSON token.
A changed selected token may normalize its JSON escape spelling, while its decoded
markup stays local. `rawResponseBody` contains the provider's fragment response;
`result` contains the assembled JSON document.

Selectors use RFC 6901 JSON Pointer syntax: `~1` for `/`, `~0` for `~`, array
indices such as `/items/0/html`, and an empty pointer for a root JSON string
(`--html-json-pointer ''`). Repeated selectors are deduplicated. There are no
wildcards, field-name guesses, or implicit traversal into JSON encoded inside
another string. Invalid/missing/non-string selections, duplicate source object
keys, and malformed source JSON fail before HTTP, including dry-run and
`--no-validate`. Segment response integrity is also mandatory. When validation is
enabled, selected HTML is checked before and after translation automatically.

`htmlJsonPointers` / `--html-json-pointer` selects content to **translate**.
`validateHtmlJsonPointers` / `--validate-html-json-pointer` only selects additional
HTML strings to **check**. Both can be used together. JSON segment mode requires
at least one translation selector; document mode rejects translation selectors.
`structured_json` and `response_schema` are incompatible with segment mode because
the provider sees a fragment object rather than the outer JSON schema.

The earlier HTML transport limitations still apply. CLI JSON dry-run outputs
filtered JSON with only the selected strings prepared; the PHP API exposes this in
`prepared` while its dry-run `result` remains the source. Transport input limits apply to the
serialized fragment payload, not to unselected metadata. This mode does not change
the default JSON translation behavior, which treats string values as translatable content.

## Limit plain text segment batches

Set `textSegmentBatchSize: 25` in PHP or `--text-segment-batch-size=25` in the CLI
with text segment transport to send at most 25 fragments per request:

```bash
bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 \
  --source=samples/quickstart/input.txt --translated=output.txt
```

The default is `null` (one request). The limit must be a positive integer and
requires `textTransport: 'segments'` / `--text-transport=segments`. Requests run
sequentially, retain the original fragment identifiers, and reconstruct source
line separators, whitespace and protected placeholders locally. All packet input
lengths and driver request limits are checked before the first HTTP call.

Each response is checked before sending the next packet, including mandatory
fragment identifiers, nonempty string values, duplicate keys, introduced
placeholders and CR/LF. A failed packet stops the operation, reports its position
and the number of earlier completed packets, and returns no partial document.
CLI output files are written only after the complete translation succeeds.
There is no automatic retry. An optional [checkpoint](#resume-confirmed-successful-segment-packets) can retain successful
packets for a later run; earlier successful packets may already have been billed. Network timeouts and output budgets apply per request.

With a batch size set, the result includes `batches` with ordered packet summaries
and aggregated `consumed` usage. `rawResponseBody` is a JSON array of raw response
strings in request order, as in HTML batching. Dry-run and inputs without
translatable fragments make no HTTP requests; an input without fragments has an
empty `batches` list and raw response array. Without the option, the existing
single-response result shape is unchanged.

The count limit does not bound characters or bytes. Without the optional byte
[byte limit](#bound-segment-packets-by-serialized-bytes), a single oversized fragment still fails before HTTP. DeepL's default
JSON Document transport performs its upload, polling and download workflow for
each packet. Its explicit `json_transport=text_batch` alternative keeps its own
request limits.

## Limit HTML segment batches

Set `htmlSegmentBatchSize: 25` in the PHP API or add
`--html-segment-batch-size=25` in the CLI to send at most 25 fragments per request.
The option requires `htmlTransport: 'segments'` / `--html-transport=segments` and
works for HTML and selected HTML strings inside `json`/`linedJson`.

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
bin/bblslug --model=xai:grok-4.7 --format=html \
  --html-transport=segments --html-segment-batch-size=25 \
  --source=samples/quickstart/input.html --translated=out.html
```

The default is `null` (no batching). Packets run sequentially, preserving the
original identifiers and source positions. Every response is checked against its
own packet before continuing, even with validation disabled. Missing, duplicate,
extra, empty, or non-string values abort the operation. A packet failure identifies
its position and the number of earlier completed packets; no further packets are
sent, no partial document is returned, and the CLI keeps existing files unchanged.
There are no automatic retries or fallback requests. A successful earlier packet
is a completed provider operation even when a later packet fails.

For registered models declaring `native_json_schema` (currently Yandex GPT Lite/Pro
and Grok 4.7), the standard `translator` group requests a native JSON Schema for
each text or HTML segment packet, including selected HTML inside JSON. Every packet
requires exactly its own identifiers with nonempty string values (`minLength: 1`),
including identifiers generated
when splitting long fragments. This also applies to a single unbatched segment
request. Custom prompt groups, a `translator` group without `structured_json`, and
explicit `json_transport=markers` keep their existing protocol. The schema does not
replace local checks or guarantee translation quality; a rejected or incomplete
response still fails. Caller-supplied `response_schema` remains incompatible with
segment transports, since its fields describe the source document rather than the
internal packet.

Model input-length checks and request-body preparation run for **all** packets
before the first HTTP call. Without the optional [byte limit](#bound-segment-packets-by-serialized-bytes), a single
oversized fragment fails; it is never clipped. The count limit counts fragments,
not characters or bytes. Smaller
packets also reduce context across inline text nodes, so translation fidelity still
requires review. Source HTML and unselected JSON remain local as before.

Batching explicitly makes multiple translation requests. Existing output-token
settings and timeouts apply **per packet**, not to the complete document. Prompts
and context are repeated. DeepL's default JSON transport starts a separate document
workflow per packet; its explicit `json_transport=text_batch` alternative retains
its own endpoint limits. Authentication, proxy, language, and model options are
reused for each request.

With a batch size configured, the result adds `batches`: ordered summaries with
`index` (1-based), `segments`, `httpStatus`, and normalized `consumed` usage.
Top-level `consumed` sums the metrics actually reported across packets; absent
metrics are not estimated. `httpStatus` describes the last packet.
`rawResponseBody` becomes a JSON array of raw response-body **strings**, one per
packet, rather than a fabricated provider response. Verbose diagnostics identify
packets. API dry-run records planned packets with status 0 and sends no HTTP;
CLI dry-run still returns prepared HTML/JSON. Inputs with no translatable text
make no requests and return an empty `batches` list. Without the option, the
existing single-response result shape remains unchanged.

## Bound segment packets by serialized bytes

Set `segmentBatchMaxBytes: 4000` in PHP or `--segment-batch-max-bytes=4000` in the
CLI with text or HTML segment transport. The cap can operate alone or together
with `textSegmentBatchSize` / `htmlSegmentBatchSize`; both limits then apply.

```bash
bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --segment-batch-max-bytes=4000 \
  --source=samples/quickstart/input.txt --translated=output.txt
```

The positive integer cap measures the serialized fragment JSON in UTF-8 bytes,
including keys, quotes, braces, commas and escaping. It does not measure the full
provider request body, prompt tokens or output size. Existing model length and
driver request-body guards still run on every packet before any HTTP request.
Choose a cap that leaves room for provider-specific overhead and the output.
A byte cap does not set the output-token budget. Configure that separately through
[provider options](providers.md#model-defaults); reasoning models may also spend tokens
or time before producing visible text. A truncated response remains a failure.

An individual fragment that exceeds the cap is split into sentence-sized pieces
where sentence-ending punctuation (`.`, `!`, `?`, `。`, `！`, `？`, with optional closing
quotes, `)` or `]`) is followed by whitespace. Longer sentences fall back to Unicode
whitespace boundaries. Values that already fit the cap retain their original
granularity. Sentence boundaries use punctuation rather than linguistic analysis;
abbreviations can also mark a boundary. Source separators between the resulting
pieces stay local and are reinserted exactly. Original identifiers stay on the first piece; additional
pieces get distinct identifiers and are joined before text/HTML reconstruction.
This also works for explicitly selected HTML strings inside JSON; unselected JSON
bytes remain unchanged. Missing pieces or invalid packet responses stop the
operation without a partial result. Packet summaries count the pieces actually
sent to the provider. Every piece has its own required identifier; this reduces the
opportunity to silently shorten repeated sentences within a long value. A smaller
byte limit alone does not prove content completeness, and sentence-sized pieces
can increase the number of requests under a fragment-count limit.

Words and tokens are never cut in the middle. An unbreakable word, URL, or an
input without suitable whitespace that cannot fit the cap causes an error before
HTTP. Protect opaque values with filters where appropriate. Smaller fragments
can reduce translation fluency and context; reconstruction checks cannot prove
translation fidelity. Byte limits do not enable retries or persistence by
themselves; use a [checkpoint](#resume-confirmed-successful-segment-packets) to resume confirmed successful packets.
CLI dry-run validates the byte plan while returning prepared source text.

The default is `null`, preserving existing count-only and single-request behavior.
Enabling the byte cap uses the same `batches`, usage aggregation and ordered raw
response array as count-based batching. Empty documents make no HTTP requests.

## Resume confirmed successful segment packets

Pass `checkpointPath: '/private/work/translation.checkpoint.json'` in PHP or
`--checkpoint=translation.checkpoint.json` in the CLI alongside a segment count
or byte cap. This works for text, HTML and selected HTML strings in JSON.

```bash
bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 \
  --checkpoint=translation.checkpoint.json \
  --source=samples/quickstart/input.txt --translated=output.txt
```

After a failed run, repeat the same command. Missing state is created before the
first HTTP request; existing state reuses its contiguous prefix of confirmed,
validated packets and sends only the rest. A fully completed checkpoint rebuilds
the translation without HTTP. Each new packet is persisted after its response
passes the mandatory segment protocol checks. Invalid or missing fragments are
never saved. The final output file is still written only after complete success.

Compatibility is checked against source bytes, effective model configuration,
translation settings, filters, actual loaded prompt templates, validation flags,
packet boundaries and library source code. A mismatch or corrupt checkpoint
fails before new HTTP requests and preserves the file. A library update also
invalidates the checkpoint conservatively. An API key or proxy may change;
HTTP environment settings and the operation timeout may also change. Each new
invocation gets a fresh operation time budget.
This allows resuming after a timeout with a longer timeout while retaining successful
packets. Changing output-token settings or packet limits changes checkpoint identity;
use a new checkpoint for that configuration instead of editing saved state.

The local JSON file contains translated fragments, normalized usage and success
statuses, plus identity/checksum metadata. It does not serialize authentication
settings, input requests, raw provider responses or debug logs. It still contains
translation content and should be handled like an output document. New state and
lock files use mode `0600`. Writes use a temporary file in the same directory and
atomic replacement. A nonblocking exclusive lock prevents two processes from
using the same checkpoint concurrently. The empty `.lock` file remains after the
run; the lock itself is released when the operation ends. Parent directories must
already exist. State/lock symlinks are rejected. CLI state and lock paths must
not alias source, prepared output or translated output paths. Dry-run and inputs
without translatable fragments do not read or create state files.

With a checkpoint enabled, `batches` includes `reused` for each packet and the
result adds `reusedBatches` and `consumedThisRun`. Existing `consumed` describes the
whole document, including historical saved usage; `consumedThisRun` includes only
new successful requests in this invocation. The CLI displays current-run usage.
A reused packet has its historical success status, empty debug fields, and an
empty string in the ordered `rawResponseBody` array because raw responses are not
stored. Without `checkpointPath`, result fields and behavior stay unchanged.

Checkpoints are trusted local state, not a signed archive or a distributed job
queue. They retain only confirmed responses: a timed-out request or a crash
between receiving and persisting a response may have been billed without a saved
record. Retrying such a packet may incur another charge. In-flight DeepL uploads
are not resumed; only completed translated packets are retained. No automatic
request retries are introduced.

## Limit the whole translation operation

Use `operationTimeoutSeconds: 60` in PHP or `--operation-timeout=60` in the CLI to
set a shared time budget in positive integer seconds. The default is `null`,
retaining the existing per-request and provider workflow timeouts.

```bash
bin/bblslug --model=google:gemini-flash-latest --format=text \
  --text-transport=segments --text-segment-batch-size=25 --operation-timeout=60 \
  --source=samples/quickstart/input.txt --translated=output.txt
```

The monotonic budget starts at `translate()` entry and includes preparation,
packet requests, parsing and reconstruction. Every HTTP exchange is capped to
the remaining budget in milliseconds, preserving any shorter existing request
or connection timeout. DeepL document uploads, polling waits, status requests
and downloads share the same operation budget; it does not restart per packet
or after upload. Polling sleeps are shortened to the time remaining.

Expiry prevents further requests and rejects a late result. A failed batch still
reports its position and the number of earlier completed packets. No partial
translation is returned, and CLI destination files are written only after the
whole translation succeeds. Already completed provider work may have been billed;
a local timeout does not cancel remote work or automatically retry it.

Synchronous local processing and user feedback callbacks are checked at operation
boundaries; they cannot be forcibly interrupted mid-call, but their elapsed time
counts and a result completed after expiry is rejected. CLI dry-run validates
the option without making HTTP requests; it keeps the existing preparation-only
path. `HttpClient::request()` also accepts an optional positive
`timeoutMilliseconds` cap, which cannot extend the resolved request timeout.

## Filters inside JSON

When a filter such as `html_code` or `html_pre` receives valid JSON, it inspects
string values separately and recursively handles JSON containers/strings encoded
inside them. Escaped closing tags (`<\/code>`), quoted attributes, and Unicode
escapes are decoded for matching; protected fragments are restored from exact
source byte slices. JSON keys, numeric spellings, whitespace, and unrelated escape
sequences are not reserialized by the filter. Tag pairs in separate JSON values
cannot form one protected block. This behavior applies across providers.

The `url` filter also inspects decoded JSON string values recursively. It
recognizes supported URL schemes even when their letters, colons, slashes, hosts,
paths, or query strings use `\uXXXX` escapes. The original URL bytes are restored
without normalizing their encoding. URL-shaped property names stay unchanged;
separate JSON values cannot combine into one URL. Literal Unicode escape notation
in ordinary text is not decoded by the filter.

For ordinary HTML/text input, HTML filters use the same HTML token scan. Selected
non-void blocks require explicit closing tags. An unclosed selected block or an
incomplete selected opening tag stops processing before HTTP instead of sending
its contents unprotected. This also applies inside decoded JSON string values;
a closing tag in another value cannot complete the block. The error names the
selected tag without dumping its source contents.

This protection check also runs with `--no-validate`, `validate: false`, and
`--dry-run`. CLI errors go to stderr without a stack trace or partial output;
source, destination, and `.prepared` files are not written after filter failure.
Even for HTML elements with optional end tags, this block filter requires explicit
pairs; it does not infer DOM boundaries or repair source markup. A trailing slash
on a non-void tag, such as `<code/>`, does not replace its required closing tag.

Selected void elements such as `<br>`, `<img>`, and legacy `<param>` are protected
as complete individual opening tags, including their attributes. They need no
closing tag. Selected `<plaintext>` protects everything from its
opening tag to EOF, including apparent closing tags, following the
[HTML plaintext parsing rule](https://html.spec.whatwg.org/multipage/parsing.html#plaintext-state).
For JSON, EOF here means the end of the decoded string value, not the surrounding
container. Comments, CDATA, attributes, and other raw-text elements remain opaque
to selected-tag matching. JSON-aware filtering still requires valid JSON; malformed
JSON is not repaired.

## Validation and opting out

For HTML and JSON formats, Bblslug performs basic syntax validation before and after translation.

JSON validation also checks the returned structure against the input. Missing
fields or list elements, including `null` values, cause a schema error. Omitted
values are not automatically restored.
Objects and arrays must retain their container types, including empty `{}` and `[]`.
Object property order may change, including in nested objects; list positions remain
significant. Non-string values are compared at their original property/index.
The provider's output is returned without reordering its properties.
Numbers, booleans, and `null` must retain their original JSON tokens; only string
values may be translated. Number comparison uses the exact JSON spelling to avoid
rounding large integers or precise decimals. Equivalent rewrites such as `1.0`
to `1e0` also cause a `JSON scalar values changed` error. This check is disabled
along with container validation by `--no-validate` or `validate: false`.

With placeholder filters enabled, translated output must preserve each prepared
marker's occurrence count. Missing, duplicated, or unexpected markers cause a
`Placeholder mismatch` error before restoration. Marker order may change. This
integrity check remains enabled with `--no-validate` or `validate: false`.

HTML validation also compares the parsed source and translated trees: tag order,
nesting, attribute presence, comments, doctype, and non-translatable attribute
values must match. Text and the values of `title`, `alt`, and `aria-label` may
change. Attribute order and equivalent entity spellings are ignored. The result
is returned unchanged; comparison does not reserialize the HTML. Newly added
Markdown fences are rejected even when libxml parses them as ordinary text.
Existing fence delimiter runs must retain their type, length, count, and order;
HTML entity equivalents compare as the same visible delimiters. Boundary fence
labels must also remain unchanged, while surrounding whitespace may vary. These
checks catch duplicated or changed existing fences as well as newly added ones.
The Yandex driver's narrowly specified opening-only transport prefix is handled
before the restored HTML reaches this validator.

Current limits: HTML comparison uses libxml's parsed tree, including its HTML
normalization, and does not verify translated text content. Use placeholder
filters to protect code or other text that must remain unchanged.

JSON encoded inside string values is checked only when explicitly enabled with
`validateEmbeddedJson: true` in the PHP API or `--validate-embedded-json` in the CLI.
This option supports `json`, `linedJson`, and `structured_json`. It detects source
strings containing valid JSON objects or arrays, then recursively checks their
translated syntax, structure, and exact non-string values using the same rules as
the outer JSON. Object key order may change. Errors report `Embedded JSON mismatch`
and the affected field or index. Output strings are not repaired or reserialized.
Ordinary strings, malformed JSON-like source text, and encoded JSON scalars such
as `123`, `true`, or `null` remain translatable. The option is off by default because
JSON-looking text can be intentional prose or a code example. `validate: false`
or `--no-validate` also disables these checks.

With validation enabled, nonempty JSON string values may not become empty or
Unicode-whitespace-only strings. A violation raises `JSON string content lost`
with the property/index path. Already empty or whitespace-only source values are
allowed; nonempty translations may be shorter. This check applies to `json`,
`linedJson`, and `structured_json`, and is disabled by `--no-validate` or
`validate: false`. It does not establish completeness inside a nonempty string.
Encoded JSON strings receive the same check recursively only when
`validateEmbeddedJson` / `--validate-embedded-json` is enabled. HTML-bearing
strings remain ordinary strings unless their HTML is explicitly selected for
validation or segment transport.

HTML validation also rejects clearing previously nonempty `title`, `alt`, or
`aria-label` values, and clearing all prose in the whole fragment or a text
container such as a paragraph, heading, list item, or table cell. Inline text may
move between `span`, `strong`, `em`, or link elements within that container;
individual text nodes need not retain text. Already empty or whitespace-only
source content may remain empty or gain text. Code/preformatted, script/style,
legacy raw-text/fallback, SVG, and MathML contents are excluded from prose checks;
retained code does not hide an emptied paragraph. The comparison does not render
CSS, check translation fidelity, or prove completeness of a nonempty container.
These checks apply to explicitly selected HTML-in-JSON too, and are disabled by
`--no-validate` / `validate: false`. Unselected JSON strings are not parsed as HTML.

HTML inside a JSON string is checked only at locations explicitly selected by
`validateHtmlJsonPointers` in the PHP API:

```php
$result = Bblslug::translate(
    apiKey: $key,
    format: 'json',
    modelKey: 'openai:gpt-6-luna',
    text: $json,
    validateHtmlJsonPointers: ['/body', '/items/0/html'],
);
```

In the CLI, repeat `--validate-html-json-pointer=/body` for each selected location.
These checks support `json`, `linedJson`, and `structured_json`. They validate
source HTML before sending the request, then check translated HTML syntax and
structure after placeholder restoration using the HTML rules above. Invalid,
missing, or non-string source selections fail before HTTP, including dry-run.
A changed result raises `HTML inside JSON mismatch`; the CLI leaves an existing
output file unchanged and writes no partial translation to stdout.

Paths use RFC 6901 JSON Pointer string syntax: `/items/0/html` selects an array
item, `~1` represents `/` in a key, and `~0` represents `~`. The empty pointer
selects a root JSON string (`--validate-html-json-pointer ''` in the CLI). Paths
have no wildcards or URI fragment encoding and do not implicitly decode JSON
stored inside another string. Field names are never inferred. Unselected strings
remain ordinary translatable content. `validate: false` or `--no-validate` disables
these checks. Validation does not repair HTML or establish translation completeness;
use filters to protect code and other text that must remain unchanged.

To skip validation, add:

```bash
bin/bblslug \
  --model=google:gemini-flash-latest \
  --format=html \
  --no-validate \
  --source=samples/quickstart/input.html \
  --translated=out.html
```
