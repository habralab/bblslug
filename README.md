# Bblslug

**Bblslug** is a versatile translation tool that can be used as both a **CLI utility** and a **PHP library**.

It leverages LLM-based APIs to translate plain text or HTML while preserving structure, code blocks, and URLs via placeholder filters.

APIs supported:

- Anthropic (Claude):
  - `anthropic:claude-haiku-3.5` - Claude Haiku 3.5 (latest)
  - `anthropic:claude-opus-4` - Claude Opus 4 (20250514)
  - `anthropic:claude-sonnet-4` - Claude Sonnet 4 (20250514)
- DeepL:
  - `deepl:free` - DeepL free tier
  - `deepl:pro` - DeepL pro tier
- Google (Gemini):
  - `google:gemini-2.0-flash` - Gemini 2.0 Flash
  - `google:gemini-2.5-flash` - Gemini 2.5 Flash
  - `google:gemini-2.5-flash-lite` - Gemini 2.5 Flash Lite
  - `google:gemini-2.5-pro` - Gemini 2.5 Pro
- OpenAI (GPT):
  - `openai:gpt-4` - OpenAI GPT-4
  - `openai:gpt-4-turbo` - OpenAI GPT-4 Turbo
  - `openai:gpt-4o` - OpenAI GPT-4o
  - `openai:gpt-4o-mini` - OpenAI GPT-4o Mini
- Yandex:
  - `yandex:gpt-lite` - YandexGPT Lite
  - `yandex:gpt-pro` - YandexGPT Pro
  - `yandex:gpt-32k` - YandexGPT Pro 32K

## Features

- Supports **HTML** and **plain text** (`--format=text|html`)
- Placeholder-based protection with filters: `html_pre`, `html_code`, `url`, etc.
- Model selection via `--model=vendor:name`
- Fully configurable backend registry (via `resources/models.yaml`)
- **Dry-run** mode to preview placeholders without making API calls
- **Variables** (`--variables`) to send or override model-specific options
- **Verbose** mode (`--verbose`) to print request previews
- Can be invoked as a CLI tool or embedded in PHP code

## Installation

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
  ```

  **NB!** Some vendors require additional parameters, e.g. `YANDEX_FOLDER_ID`.

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

### Translate an HTML file and write to another file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=html \
  --source=input.html \
  --translated=output.html
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

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --variables=foo=bar,foo2=bar2 \
  --source=in.txt \
  --translated=out.txt
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
    format:   'html',                          // 'text' or 'html'
    modelKey: 'vendor:model',                  // Model identifier (e.g. deepl:free, openai:gpt-4o, etc.)
    text:     $text,                           // Source text or HTML
    // optional:
    // Additional context/prompt pass to model
    context:    'Translate as a professional technical translator',
    filters:    ['url','html_code'],           // List of placeholder filters
    proxy:      getenv('BBLSLUG_PROXY'),       // Optional proxy URI (http://..., socks5h://...)
    sourceLang: 'DE',                          // Source language code (optional; autodetect if null)
    targetLang: 'EN',                          // Target language code (optional; default from driver settings)
    variables:  ['foo'=>'bar'],                // model-specific overrides
    verbose:    true,                          // If true, returns debug request/response
);
echo $result['result'];
```

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

You can find sample input files under the `samples/` directory.

## License

This project is licensed under the MIT License – see the [LICENSE](LICENSE) file for details.
