# Bblslug

**Bblslug** is a versatile translation tool that can be used as both a **CLI utility** and a **PHP library**.

It leverages LLM-based APIs to translate plain text, HTML and JSON while preserving structure, code blocks, and URLs via placeholder filters.

APIs supported:

- Anthropic (Claude):
  - `anthropic:claude-haiku-5.5` - Claude Haiku 5.5 (low effort)
  - `anthropic:claude-sonnet-5.5` - Claude Sonnet 5.5 (low effort)
  - `anthropic:claude-opus-5.5` - Claude Opus 5.5 (low effort)
- DeepL:
  - `deepl:free` - DeepL free tier
  - `deepl:pro` - DeepL pro tier
- Google (Gemini):
  - `google:gemini-2.5-flash` - Gemini 2.5 Flash
  - `google:gemini-2.5-flash-lite` - Gemini 2.5 Flash Lite
  - `google:gemini-2.5-pro` - Gemini 2.5 Pro
- OpenAI (GPT):
  - `openai:gpt-6-luna` - GPT-6 Luna (efficient, low reasoning effort)
  - `openai:gpt-6.1-sol` - GPT-6.1 Sol (general purpose, low reasoning effort)
  - `openai:gpt-6-astra` - GPT-6 Astra (most capable, higher cost, low reasoning effort)
  - `openai:gpt-5` - OpenAI GPT-5
  - `openai:gpt-5-mini` - OpenAI GPT-5 Mini
  - `openai:gpt-5-nano` - OpenAI GPT-5 Nano
  - `openai:gpt-4o` - OpenAI GPT-4o
  - `openai:gpt-4o-mini` - OpenAI GPT-4o Mini
  - `openai:gpt-4` - OpenAI GPT-4
  - `openai:gpt-4-turbo` - OpenAI GPT-4 Turbo
- Yandex:
  - `yandex:gpt-lite` - YandexGPT Lite
  - `yandex:gpt-pro` - YandexGPT Pro
  - `yandex:gpt-32k` - YandexGPT Pro 32K
- X.ai:
  - `xai:grok-4` - Grok 4
  - `xai:grok-3` - Grok 3
  - `xai:grok-3-mini` - Grok 3 Mini

## Features

- Supports **HTML**, **JSON** and **plain text** (`--format=text|html|json`)
- Placeholder-based protection with filters: `html_pre`, `html_code`, `url`, etc.
- Model selection via `--model=vendor:name`
- Fully configurable backend registry (via `resources/models.yaml`)
- **Dry-run** mode to preview placeholders without making API calls
- **Variables** (`--variables`) to send or override model-specific options
- **Verbose** mode (`--verbose`) to print request previews
- Can be invoked as a CLI tool or embedded in PHP code
- **Validation** of container syntax for HTML or JSON; disable with `--no-validate`

The GPT-6 entries were checked against [official OpenAI documentation](https://developers.openai.com/api/docs/guides/latest-model) on 2026-10-22.
They use Chat Completions with `reasoning_effort=low` and omit `temperature`, which is unsupported with reasoning enabled.
Older OpenAI model identifiers remain available for existing integrations; registry presence does not guarantee access for your API account.

The Claude 5.5 entries use the [Anthropic Messages API](https://platform.claude.com/docs/en/api/messages/create)
with `effort=low`, no `temperature`, and an initial output budget of 4096 tokens.
Use `--variables=maxTokens=8192,effort=low` to override that budget and effort.
Retired Claude 3.5/4 and Gemini 2.0 model keys have been removed, rather than redirected
to different models. Their retirement is documented by [Anthropic](https://platform.claude.com/docs/en/about-claude/model-deprecations)
and [Google](https://ai.google.dev/gemini-api/docs/deprecations).

## Installation

Requires PHP 8.2 or newer with the cURL, DOM, and mbstring extensions.

```bash
composer require habr/bblslug
chmod +x vendor/bin/bblslug
```

## CLI Usage

### Prepare

1. **Always specify a model** with `--model=vendor:name` option.

2. **Export your API key(s)** before running:

  ```bash
  export ANTHROPIC_API_KEY=...
  export DEEPL_FREE_API_KEY=...
  export DEEPL_PRO_API_KEY=...
  export GOOGLE_API_KEY=...
  export OPENAI_API_KEY=...
  export YANDEX_API_KEY=... && export YANDEX_FOLDER_ID=...
  export XAI_API_KEY=...
  ```

  **NB!** Some vendors require additional parameters, e.g. `YANDEX_FOLDER_ID`.

  To use a credential-specific prefix, set `BBLSLUG_` before the usual environment
  variable name (for example, `BBLSLUG_OPENAI_API_KEY` and
  `BBLSLUG_YANDEX_FOLDER_ID`). A non-empty prefixed value takes precedence; the
  unprefixed variable remains a fallback.

3. **Input / output**:

  - If `--source` is omitted, Bblslug reads from **STDIN**.
  - If `--translated` is omitted, Bblslug writes to **STDOUT**.

4. **Optional proxy**:

  To route requests through a proxy (e.g. HTTP or SOCKS5), use the `--proxy` option or set the `BBLSLUG_PROXY` environment variable:

  ```bash
  # using CLI flag
  vendor/bin/bblslug --proxy="http://localhost:8888" ...
  
  # or set it globally
  export BBLSLUG_PROXY="socks5h://127.0.0.1:9050"
  ```

  This works for all HTTP requests and supports authentication (`http://user:pass@host:port`).


### Show available models

```bash
vendor/bin/bblslug --list-models
```

### Show available prompt templates

```bash
vendor/bin/bblslug --list-prompts
```

### Translate an HTML file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html
```

### Translate an JSON file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=json \
  --source=input.json \
  --translated=output.json
```

### Translate an HTML file and write to another file with filters

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --filters=url,html_code,html_pre
```

### Add translation context (prompt), source and target language

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html \
  --target-lang=EN \
  --source-lang=DE \
  --context="Translate as a professional technical translator"
```

### Pass model-specific variables

For OpenAI, `--variables=reasoning_effort=low,max_completion_tokens=8192` can set
the reasoning effort and output budget (including reasoning tokens). These parameters
must be supported by the selected model. GPT-6 reasoning requests should omit temperature.

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --variables=foo=bar,foo2=bar2 \
  --source=in.txt \
  --translated=out.txt
```

### Choose a different prompt template

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=out.html \
  --prompt-key=smart-legal
```

### Dry-run placeholders only

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --filters=url \
  --source=input.txt \
  --dry-run
```

### Verbose mode (prints request preview to stderr)

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --verbose \
  --source=input.html \
  --translated=out.html
```

### Pipe STDIN → file

```bash
cat input.txt | vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --translated=out.txt
```

### Pipe STDIN → STDOUT

```bash
echo "Hello world" | vendor/bin/bblslug \
  --model=vendor:name \
  --format=text > translated.out
```


### Disable validation

For HTML and JSON formats, Bblslug performs basic syntax validation before and after translation. To skip this step, add:

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --no-validate \
  --source=input.html \
  --translated=out.html
```

### Statistics

- **Usage metrics**

  After each translation (when not in dry-run), Bblslug prints to stderr a summary of consumed usage metrics, for example:

  ```
  Usage metrics:
      Tokens:
          Total:       1074
          -----------------
          Prompt:      631
          Completion:  443
  ```

## PHP Library Usage

You can embed Bblslug in your PHP project.

### Quickstart

1. **Install:**

  ```bash
  composer require habr/bblslug
  ```

2. **Require & Import:**

  ```php
  require 'vendor/autoload.php';
  use Bblslug\Bblslug;
  ```

### Translate

Text translation function example:

```php
$text = file_get_contents('input.html');
$result = Bblslug::translate(
    apiKey:   getenv('MODEL_API_KEY'),         // API key for the chosen model
    format:   'html',                          // 'text', 'html' of 'json'
    modelKey: 'vendor:model',                  // Model identifier (e.g. deepl:free, openai:gpt-4o, etc.)
    text:     $text,                           // Source text to be translated
    // optional:
    // Additional context/prompt pass to model
    context:    'Translate as a professional technical translator',
    filters:    ['url','html_code'],           // List of placeholder filters
    onFeedback: $verbose ? static function (string $message, string $level = 'info'): void { error_log("[$level] $message"); } : null, // Optional progress callback
    promptKey: 'translator',                   // which prompt template to use
    proxy:      getenv('BBLSLUG_PROXY'),       // Optional proxy URI (http://..., socks5h://...)
    sourceLang: 'DE',                          // Source language code (optional; autodetect if null)
    targetLang: 'EN',                          // Target language code (optional; default from driver settings)
    validate: false,                           // perform or skip syntax validation for container formats
    variables:  ['foo'=>'bar'],                // model-specific overrides
    verbose:    true,                          // If true, returns debug request/response
);
echo $result['result'];
```

`onFeedback` is an optional callback for the progress information, accepts (string $message, string $level),  
where `$level` includes `'info' | 'warning' | 'error'`. Send null to swhitch off.

Result structure:

```php
[
  'original'        => string,   // Original input
  'prepared'        => string,   // After placeholder filters
  'result'          => string,   // Translated result
  'httpStatus'      => int,      // HTTP status
  'debugRequest'    => string,   // Request debug
  'debugResponse'   => string,   // Response debug
  'rawResponseBody' => string,   // Response body
  'consumed'        => [                  // Normalized usage metrics
     'tokens' => [
       'total'     => int,                // Total tokens consumed
       'breakdown' => [                   // Per-type breakdown
         'prompt'      => int,            // Name depeds of model
         'completion'  => int,            // Name depeds of model
       ],
     ],
     // additional categories if supported by the model...
  'lengths'         => [         // Text length statistics
     'original'    => int,       // - original text
     'prepared'    => int,       // - after placeholder filters
     'translated'  => int,       // - returned translated text
  ],
  'filterStats'     => [         // Placeholder stats
     ['filter'=>'url','count'=>3], …
  ],
]
```

### List available models

```php
$modelsByVendor = Bblslug::listModels();

foreach ($modelsByVendor as $vendor => $models) {
    echo "Vendor: {$vendor}\n";
    foreach ($models as $key => $config) {
        printf("  - %s: %s\n", $key, $config['notes'] ?? '(no notes)');
    }
}
```

Returns an array like:

```php
[
  'deepl'  => ['deepl:free' => […], 'deepl:pro' => […]],
  'openai' => ['openai:gpt-4' => […], …],
  …
]
```

### List available prompts

```php
$prompts = Bblslug::listPrompts();

foreach ($prompts as $key => $info) {
    $formats = implode(', ', $info['formats']);
    echo "{$key} ({$formats})";
    if (! empty($info['notes'])) {
        echo " – {$info['notes']}";
    }
    echo "\n";
}
```

Returns an array like:

```php
[
  'translator' => [
      'formats' => ['text', 'html', 'json'],
      'notes'   => 'professional translator template',
  ],
  'legal'      => [
      'formats' => ['text'],
      'notes'   => 'legal-style translator template',
  ],
  // …
]
```

### Error handling

```php
try {
    $res = Bblslug::translate(...);
} catch (\InvalidArgumentException $e) {
    // invalid model, missing API key, etc.
} catch (\RuntimeException $e) {
    // HTTP error, parse failure, driver-specific error
}
```



## Samples

### Local live smoke checks

Run the live suite with an exact `BBLSLUG_LIVE_MODEL`, or `all` for the first model
of each vendor with credentials. Use `deepl:pro` to check the Pro tier separately.
Credentials use the `BBLSLUG_` prefix first, with unprefixed names as a fallback.
Yandex also requires `BBLSLUG_YANDEX_FOLDER_ID` or `YANDEX_FOLDER_ID`.
The JSON sample includes a JSON-encoded string with backslashes and quotes.
These checks call paid APIs and verify basic translation and container invariants;
they are not a translation-quality benchmark.

Locally, with the corresponding environment variables configured:

```bash
RUN_LIVE_TESTS=1 BBLSLUG_LIVE_MODEL=openai:gpt-6-luna \
  vendor/bin/phpunit --testsuite integration --testdox
```

### Input samples

You can find sample input files under the `samples/` directory.

## License

This project is licensed under the MIT License – see the [LICENSE](LICENSE) file for details.
