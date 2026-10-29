# Samples

Use the authored [quickstart inputs](quickstart/) with the [README](../README.md),
[usage](../docs/usage.md#running-the-file-examples) and [transport](../docs/transports.md) examples.
Composer installations include them under `vendor/habr/bblslug/samples/quickstart/`; copy the files
into your working directory or supply your own paths. Run the installed CLI as `vendor/bin/bblslug`.
From a checkout, the CLI is `bin/bblslug` and inputs are under `samples/quickstart/`.

| Path | Purpose and expected behavior |
|---|---|
| `quickstart/input.txt` | English plain text, a URL, and literal `code`/`pre` blocks for `url,html_code,html_pre` filters. Text segments preserve line separators. |
| `quickstart/input.html` | Complete English HTML with text nodes, `title` and `aria-label`, a link, and protected `code`/`pre` blocks. Suitable for document or HTML segment transport. No remote assets are embedded. |
| `quickstart/input.json` | English `message`, HTML in `/body` and `/items/0/html`, and numeric `count: 2`. Default JSON translation handles string values; the two HTML pointers select only those strings for HTML segments. `message` and `count` stay unchanged in that selected mode. |
| `quickstart/translation.schema.json` | Native provider response schema describing `input.json`. Use with document transport and `--format=structured_json --response-schema=samples/quickstart/translation.schema.json` on supported providers; incompatible with segments. It describes types and required fields, not translation quality or exact scalar values. Bblslug's ordinary JSON validation checks exact non-string source values. |
| [HTML fragments](html_fragments/) | Historical manual inputs. `ru_fragment.html` and `ru_short.html` are ordinary examples; `ru_fragment_corrupted.html` is intentionally malformed negative input for validation experiments and may be rejected. It is not a successful quickstart example. Source provenance and reuse permissions are unverified. |
| [Literary fragments](literary_fragments/README.md) | Sixteen source-checked original-language excerpts and one explicit Latin placeholder. Lengths vary; not a gold-standard translation benchmark. |
| [Technical fragments](tech_fragments/README.md) | Classical excerpts with per-file verification status, and historical modern article copies. |

The four files in `quickstart/` are original repository examples under the [repository MIT license](../LICENSE). They contain no real credentials or fetched content; `example.invalid` URLs are placeholders. Historical third-party payloads retain source-specific rights and are not all MIT licensed. Bblslug does not load linked remote assets; a browser or active preview may do so when opening historical HTML.

Quickstart samples are inputs, not expected translations. Ordinary translation commands contact the chosen provider and may incur charges. `--dry-run` prepares input without a translation request, but the CLI still requires its configured API-key environment variable. See the usage guide for setup, dry-run behavior and validation limits.
