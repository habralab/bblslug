# Bblslug

**Bblslug** is a versatile translation tool that can be used as both a **CLI utility** and a **PHP library**.

It leverages LLM-based APIs to translate plain text or HTML while preserving structure, code blocks, and URLs via placeholder filters.

APIs supported:

- Anthropic (Claude):
 - `anthropic:claude-haiku-3.5` - Claude Haiku 3.5 (latest)
 - `anthropic:claude-opus-4` - Claude Opus 4 (20250514)
 - `anthropic:claude-sonnet-4` - Claude Sonnet 4 (20250514)
- DeepL
 - `deepl:free` - DeepL free tier
 - `deepl:pro` - DeepL pro tier
- Google (Gemini)
 - `google:gemini-2.0-flash` - Gemini 2.0 Flash
 - `google:gemini-2.5-flash` - Gemini 2.5 Flash
 - `google:gemini-2.5-flash-lite` - Gemini 2.5 Flash Lite
 - `google:gemini-2.5-pro` - Gemini 2.5 Pro
- OpenAI (GPT)
 - `openai:gpt-4` - OpenAI GPT-4
 - `openai:gpt-4-turbo` - OpenAI GPT-4 Turbo
 - `openai:gpt-4o` - OpenAI GPT-4o
 - `openai:gpt-4o-mini` - OpenAI GPT-4o Mini

## Features

- Supports **html** and **plain text** (`--format=text|html`)
- Placeholder-based protection with filters: `html_pre`, `html_code`, `url`, etc.
- Model selection via `--model=vendor:name` (`deepl:pro`, `google:gemini-2.5-flash`, `openai:gpt-4o`, …)
- Fully configurable backend registry
- **Dry-run** mode to preview placeholders without making API calls
- **Verbose** mode (`--verbose`) to print request previews
- Can be invoked as a CLI tool or embedded in PHP code

## Installation

```bash
composer require habr/bblslug
chmod +x vendor/bin/bblslug
```

## CLI Usage

1. **Always specify a model** with `--model=vendor:name` option.

2. **Export your API key(s)** before running:

 ```bash
 export ANTHROPIC_API_KEY=...
 export DEEPL_FREE_API_KEY=...
 export DEEPL_PRO_API_KEY=...
 export GOOGLE_API_KEY=...
 export OPENAI_API_KEY=...
 ```

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

### Pipe STDIN → file

```bash
vendor/bin/bblslug \
  --model=vendor:name \
  --format=text \
  --source=input.txt
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
  --format=text
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

## PHP Library Usage

You can embed Bblslug in your PHP project:

```php
<?php
require 'vendor/autoload.php';

use Bblslug\Bblslug;

// Load input text or HTML from file
$text   = file_get_contents('input.html');

// Call library translate method
$result = Bblslug::translate(
    apiKey:     getenv('DEEPL_PRO_API_KEY'),     // API key for the chosen model
    format:     'html',                          // 'text' or 'html'
    modelKey:   'deepl:pro',                     // Model identifier (e.g. deepl:free, deepl:pro, openai:gpt-4o)
    text:       $text,                           // Source text or HTML

    // optional parameters:
    context:    null,                            // Additional context/prompt (DeepL: context)
    dryRun:     false,                           // If true, only prepare placeholders, no API call
    filters:    ['url', 'html_code'],            // List of placeholder filters
    proxy:      getenv('BBLSLUG_PROXY'),         // Optional proxy URI (http://..., socks5h://...)
    sourceLang: null,                            // Source language code (optional; autodetect if null)
    targetLang: null,                            // Target language code (optional; default from driver settings)
    verbose:    true,                            // If true, prints debug request/response to stderr
);

// Result output example
// $result = [
//   'original'    => '...',      // Original input
//   'prepared'    => '...',      // After placeholder filters
//   'result'      => '...',      // Translated result
//   'lengths'     => [           // Character counts
//     'original'   => 123,
//     'prepared'   => 100,
//     'translated' => 130
//   ],
//   'filterStats' => [           // Placeholder stats
//     ['filter'=>'url','count'=>5], …
// ];

echo $result['result'];
```

## Examples

You can find sample input files under the `examples/` directory.

## License

This project is licensed under the MIT License – see the [LICENSE](LICENSE) file for details.
