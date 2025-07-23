# Bblslug

**Bblslug** is a versatile translation tool that can be used as both a **CLI utility** and a **PHP library**.

It leverages LLM-based APIs (DeepL, OpenAI, Gemini) to translate plain text or HTML while preserving structure, code blocks, and URLs via placeholder filters.

## Features

- Supports **html** and **plain text** (`--format=text|html`)
- Placeholder-based protection with filters: `html_pre`, `html_code`, `url`, etc.
- Model selection via `--model=vendor:name` (`deepl:free`, `deepl:pro`, `openai:gpt-4o`, `gemini:1.5-pro`, …)
- Fully configurable backend registry
- **Dry-run** mode to preview placeholders without making API calls
- **Verbose** mode (`--verbose`) to print request previews
- Can be invoked as a CLI tool or embedded in PHP code

## Installation

```bash
composer require habralab/bblslug
chmod +x vendor/bin/bblslug
```

## CLI Usage

1. **Always specify a model** with `--model=vendor:name` option.

2. **Export your API key(s)** before running:

 ```bash
 export DEEPL_FREE_API_KEY=...
 export DEEPL_PRO_API_KEY=...
 export OPENAI_API_KEY=...
 export GEMINI_API_KEY=...
 ```

3. **Input / output**:

 - If `--source` is omitted, Bblslug reads from **STDIN**.
 - If `--translated` is omitted, Bblslug writes to **STDOUT**.

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
    modelKey:   'deepl:pro',                     // Model identifier (e.g. deepl:free, deepl:pro, openai:gpt-4o)
    apiKey:     getenv('DEEPL_PRO_API_KEY'),     // API key for the chosen model
    format:     'html',                          // 'text' or 'html'
    text:       $text,                           // Source text or HTML

    // optional parameters:
    context:    null,                            // Additional context/prompt (DeepL: context)
    dryRun:     false,                           // If true, only prepare placeholders, no API call
    filters:    ['url', 'html_code'],            // List of placeholder filters
    sourceLang: null,                            // Source language code (optional; autodetect if null)
    targetLang: null,                            // Target language code (optional; default from registry)
    verbose:    true                             // If true, prints debug request/response to stderr
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
