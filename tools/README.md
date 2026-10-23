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
