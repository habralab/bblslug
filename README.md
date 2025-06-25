# Bblslug

**Bblslug** is a CLI translation tool using LLM-based APIs like OpenAI, Gemini, and DeepL. It formats HTML and plain text for accurate machine translation.

It supports HTML and plain text files, preserving structure, code blocks, and raw URLs using compact placeholders. Designed for automation, batch processing, and integration with scripts.

## Features

- Supports `html` and `text` formats
- Placeholder-based protection with user-defined filters:
  - `html_pre`, `html_code`, `url`, and more
- Model selection via `--model`
- Registry-based backend (DeepL Free/Pro, extensible)
- Dry-run mode with intermediate output
- Verbose mode with detailed stats
- Compact and reusable CLI

## Usage

### Basic HTML

```bash
./bin/bblslug --format=html --source=input.html --translated=output.html
```

### With filters

```bash
./bin/bblslug --format=html --source=input.html --translated=output.html --filters=url,html_pre
```

### List available models

```bash
./bin/bblslug --list-models
```

### Dry-run

```bash
./bin/bblslug --format=html --source=input.html --translated=output.html --dry-run
```

### Verbose output

```bash
./bin/bblslug --format=text --source=input.txt --translated=output.txt --verbose
```

### Alternative usage via Composer (requires linking or package installation)

```bash
vendor/bin/bblslug --format=html --source=input.html --translated=output.html
```

## Environment

Set your API key via environment:

```bash
export DEEPL_API_KEY=your-key-here
```

## Environment


```bash
export DEEPL_API_KEY=your-key-here
```

## Examples

You can find sample input files under the `examples/` directory.

## License

Internal project — not publicly licensed.
