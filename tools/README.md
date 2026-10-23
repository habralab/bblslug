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
