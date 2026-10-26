# Live translation smoke checks

`php tools/live-smoke.php` sends real, potentially billed translation requests.
Run from a development checkout with Composer dependencies installed and credentials
already present in the environment. It does not change the model registry or retry
failed requests.

```bash
php tools/live-smoke.php

BBLSLUG_SMOKE_MODELS=deepl:free,deepl:pro \
BBLSLUG_SMOKE_FORMATS=html,json \
BBLSLUG_SMOKE_ENTRIES=api,cli \
BBLSLUG_SMOKE_REPORT_DIR=/tmp/bblslug-deepl-smoke \
php tools/live-smoke.php
```

To choose a non-default prompt explicitly for both entry points:

```bash
BBLSLUG_SMOKE_MODELS=openai:gpt-5-mini,google:gemini-2.5-flash \
BBLSLUG_SMOKE_FORMATS=json \
BBLSLUG_SMOKE_PROMPT_KEY=experimental_xml \
BBLSLUG_SMOKE_REPORT_DIR=/tmp/bblslug-xml-smoke \
php tools/live-smoke.php
```

`BBLSLUG_SMOKE_PROMPT_KEY` defaults to `translator`; summary rows record the prompt
key. Select formats that the chosen group provides. The XML group has no native
`structured_json` template, so use the marker protocol for that comparison.

To force Google's native JSON transport instead of the default automatic selection:

```bash
BBLSLUG_SMOKE_MODELS=google:gemini-2.5-flash \
BBLSLUG_SMOKE_FORMATS=json \
BBLSLUG_SMOKE_GOOGLE_JSON_TRANSPORT=native \
BBLSLUG_SMOKE_REPORT_DIR=/tmp/bblslug-gemini-native \
php tools/live-smoke.php
```

The transport override accepts `auto` (default), `markers`, or `native`, applies
only to Google JSON cases, and is recorded in each corresponding summary row.
Auto mode uses declared model capabilities with the standard translator template;
custom JSON-only prompt groups retain their marker protocol. Use `markers` to
reproduce the historical protocol checks explicitly.

Defaults cover the eight model/tier entries in the README live-check table, all
three formats (`text,html,json`), and both entry points (`api,cli`). Credentials
prefer `BBLSLUG_` names with unprefixed fallback, matching the application. Missing
credentials cause a nonzero exit. Yandex also needs its folder ID. Authentication,
billing, quota, and missing-model errors skip the remaining cases for that model.
Other failures are recorded and the run continues. Use a fresh report directory
for each run to avoid retaining artifacts from earlier runs.

The default report directory is `/tmp/bblslug-live-smoke`, created with mode 0700.
`summary.json` is updated after each case; per-case files contain synthetic output
and diagnostics, masked using the configured credentials and folder ID. Keep these
local; the report is diagnostic evidence, not a public log upload mechanism.
Exit status is zero only when all requested cases pass (a changed unprotected path
is recorded as an observation, not a container failure).

The checks cover nonempty translated output, valid outer and recursively encoded
JSON, exact non-string values, protected code and URL restoration, and diagnostic
key masking. An unprotected Windows path is observed separately: ordinary strings
are translatable. These are small English-to-German requests, not semantic quality
evaluation, throughput measurement, or an exhaustive model catalogue test.
`linedJson`, native `structured_json`, and every legacy model are outside this
runner's scope. Controlled provider failures are tested offline by
`CliProviderFailureTest` and the provider/transport unit suites; this runner does
not deliberately exhaust real quotas or submit bad real credentials.

The 2026-10-23 run made 48 initial requests on `61610e4`, then 32 HTML/JSON requests
with the timeout and prompt fixes. The README shows the latest result per case,
retaining the original 16 text results. DeepL CLI JSON changed from local timeout
validation errors to successful uploads on both tiers. Gemini JSON was inconsistent
about markers; Yandex Lite HTML remained rejected; one Yandex Pro connection timed
out. Do not treat a single later success as a fix for those provider failures.

An additional 2026-10-23 native Google JSON run passed six requests (three API/CLI
pairs), with the same nested JSON/code/URL/scalar checks enabled. A diagnostic
Yandex Lite HTML request reproduced an added opening Markdown fence; it remained
a validation failure. Sandbox DNS failures before the native live run are not
provider output failures and are not included in that six-request result.

The 2026-10-23 prompt review made 36 requests across six model/tier entries and
both entry points: default `translator` passed 12/12, the original local XML
prototype passed 10/12, and the reviewed `experimental_xml` passed 12/12. The
original prototype's Yandex Pro responses added Markdown fences and were rejected.
It was loaded under a temporary local key for comparison and is not a shipped
prompt group. See the README for scope and limitations of this single-sample run.

The subsequent automatic-selection check covered five Gemini models with ten
API/CLI JSON requests: nine passed, and 4 Argon CLI timed out at 45 seconds. A
separate single check with the normal library timeout of 120 seconds passed in
3.49 seconds; the first timeout remains recorded. Four 2.5 Flash text/HTML requests
also passed. These observations do not claim every catalogue entry was live-tested.

After source-aware Yandex HTML opening-prefix handling, a 12-request Lite/Pro run
passed through API/CLI for text, HTML, and JSON. Both raw Lite HTML replies retained
the observed opening-only fence while the returned HTML passed the normal checks.
Native JSON-envelope probes were diagnostic only: passing a JSON-encoded HTML
input corrupted closing tags, while a full marker prompt caused a Lite whitespace
loop ending at the token limit. No native HTML-envelope mode was shipped.

### End-to-end acceptance, 2026-10-25

A separate local acceptance harness exercised the accumulated segment transport,
packet limits, checkpoints, API and CLI changes. These were small synthetic
Russian-to-English inputs: 252 characters of text, 342 characters of HTML, and
644 characters of JSON containing two explicitly selected HTML strings. This
harness is not the `live-smoke.php` runner described above.

Every segment case used a count limit of four, a serialized payload limit of
512 bytes, a checkpoint, and a 60-second operation timeout (45-second HTTP and
10-second connection limits). Text used `textTransport=segments`; HTML and JSON
used `htmlTransport=segments`, with JSON pointers `/body` and
`/nested/items/0/html`. DeepL used `json_transport=text_batch`; an additional API
JSON case on each tier exercised `json_transport=document`.

| Configuration | Passed cases | Other outcome |
| --- | ---: | --- |
| `google:gemini-2.5-flash` | 6/6 | — |
| `openai:gpt-5-mini` | 6/6 | — |
| `anthropic:claude-haiku-4.5-20251001` | 6/6 | — |
| `xai:grok-4.20-0309-non-reasoning` | 6/6 | — |
| `yandex:gpt-lite` | 6/6 | — |
| `yandex:gpt-pro` | 5/6 | CLI JSON: connection timed out after 10 seconds |
| `deepl:free` | 7/7 | Includes the additional JSON Document case |
| `deepl:pro` | 7/7 | Includes the additional JSON Document case |

The first run exposed unmasked Yandex folder IDs in verbose request diagnostics.
API authorization keys were masked. The fix extends request and error masking to
folder IDs and proxies; dry-run and malformed-response regression tests cover the
folder ID. Both Yandex configurations were then rerun. The table retains the
connection timeout from that rerun: it occurred before any packet completed, and
both the source and existing destination remained unchanged. No automatic retry
was used to replace that result with a success.

All 49 successful cases checked source preservation, exact protected code/URLs,
HTML structure, text line separators where applicable, and a full-checkpoint
rerun with identical output and no new translation requests. Selected-HTML JSON
also preserved every byte outside the selected strings, including large numbers
and numeric spelling. Diagnostics and checkpoint files passed the configured
credential/folder-ID checks. No residual Cyrillic was observed in the selected
translatable text; this is not a semantic translation-quality assessment.

The existing `live-smoke.php` runner separately passed 12/12 default document-mode
cases: Gemini 2.5 Flash and Yandex Lite, API and CLI, text/HTML/ordinary JSON. This
small-sample success does not remove the previously documented risk of omitted
prose in large document-mode translations, or prove fidelity for arbitrary
HTML-bearing JSON strings.

An offline CLI acceptance harness on PHP 8.2 and 8.5 covered all three segment
formats with HTTP 402, timeouts, missing fragment IDs, and malformed JSON after a
confirmed packet. Failures preserved the old output, left stdout empty, and
retained the confirmed prefix. Resume sent only the unsaved packets; a fully
cached run sent none. Changed source/model/context, corrupt checkpoints, and
invalid output parents failed before HTTP. These controlled failures complement
the real success cases; this run did not deliberately exhaust provider balances.

This is evidence for the named configurations and samples, not every registered
model, large-document throughput, or guaranteed translation completeness.

### Follow-up acceptance for release preparation

After the empty-response, cleared-JSON-string, and cleared-HTML-content fixes, a
fresh run passed **48/48** segment scenarios on the same eight configurations
listed above, through API and CLI for text, HTML, and selected HTML-in-JSON. The
count/byte limits and checkpoints were unchanged. Every case also passed a full
checkpoint reuse run with identical output and no fresh translation requests.
The two additional DeepL JSON Document cases from the earlier 50-case run were
not repeated; this follow-up used `json_transport=text_batch` on both DeepL tiers.

A fresh default document-mode check passed **12/12** cases on Gemini 2.5 Flash and
Yandex Lite (text/HTML/JSON, API/CLI). The previous Yandex Pro connection timeout
remains recorded above; a later success does not establish that network timeouts
cannot recur or that a provider's translation is always complete.

The offline three-format failure/resume acceptance was repeated on PHP 8.2 and
8.5, with zero failed checks. HTTP 402, timeout, missing IDs, and malformed JSON
retained the confirmed prefix and preserved the old destination; resume sent
only unsaved packets and a full cache sent none. The unit suite passed on both
versions: **1117 tests, 6421 assertions**, with PHPCS and PHPStan at maximum level
also passing. These results prepare the next release; no additional models or
large-document quality claims are implied.

### Audit correction verification, 2026-10-26

The release review found gaps outside the previous small success matrix: live
PHPUnit datasets exposed credentials, OpenAI/xAI accepted non-success completion
states with markers, OpenAI default-schema build/parse modes disagreed, DeepL
body-size checks were conditional, selected void filters matched nothing, and
workflow/batch errors lost transport codes. All six now have targeted regressions.
The README format example and legacy Composer script aliases were also corrected.

PHP 8.2.34 and 8.5.11 passed **1159 unit tests, 6584 assertions** each. PHPCS,
PHPStan max, `composer validate --strict`, and the restored `lint`/`phpunit`
aliases passed. PHPUnit isolation checks use fixture keys, inspect stdout/stderr
and JUnit on both skipped and intentionally failed tests, and prevent real HTTP.
The offline three-format failure/resume acceptance passed on both PHP versions.

Updated drivers also accepted **112/112 recorded response packets** from the
previous 48-case API/CLI matrix across all eight configurations. These were
sanitized response-body replays, without network or new provider charges. The
DeepL dummy contexts check response counts/decoding only; they do not replay
full document reconstruction. Negative finish-state handling is covered by
controlled tests, since the recorded successful responses contained no such
states. The 2026-10-25 live results above remain historical evidence, not a fresh
post-audit live run or a semantic translation-quality guarantee.

The first Ubuntu release CI run exposed a diagnostic-fixture portability issue:
a trailing literal `<` was accepted as text by that libxml version. The fixture
now uses an unterminated attribute to exercise the invalid-HTML diagnostic path.
A separate regression confirmed and fixed stale libxml errors contaminating valid
HTML checks and the error-handling mode being reset. The updated local suites
passed **1162 unit tests, 6592 assertions** on both PHP versions; PSR-12 and
PHPStan max remained clean. The earlier failed CI run is retained in GitHub.

The next CI run passed PHPUnit and reached PHPStan 2.3.2, while the earlier local
analysis used 2.1.22. Its stricter array-shape checks exposed partially assembled
metadata and unproved decoded-key types. Records are now assembled with all
required fields, string IDs are checked at the decoding boundary, and a redundant
CLI guard was removed. The same official PHPStan 2.3.2 passes at maximum level
locally, without a baseline or relaxed checks.
