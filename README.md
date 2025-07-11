# Bblslug

**Bblslug** is a CLI translation tool using LLM-based APIs like OpenAI, Gemini, and DeepL. It formats HTML and plain text for accurate machine translation.

It supports HTML and plain text files, preserving structure, code blocks, and raw URLs using compact placeholders. Designed for automation, batch processing, and integration with scripts.

## Features

- Supports `html` and `text` formats
- Placeholder-based protection with user-defined filters:
  - `html_pre`, `html_code`, `url`, and more
- Model selection via `--model`
- Registry-based backend (DeepL, OpenAI, Gemini — fully configurable)
- Dry-run mode with intermediate output
- Verbose mode with detailed stats
- Compact and reusable CLI

## Usage

You must select a model via the `--model=vendor:name` option. This option is required.

To view all available models, run:

```bash
./bin/bblslug --list-models
```

Set the necessary API keys before running:

```bash
export DEEPL_FREE_API_KEY=your-key-for-deepl-free-here
export DEEPL_PRO_API_KEY=your-key-for-deepl-pro-here
export OPENAI_API_KEY=your-key-for-openai-here
export GEMINI_API_KEY=your-key-for-gemini-here
```

### Basic HTML

```bash
./bin/bblslug --model=vendor:name --format=html --source=input.html --translated=output.html
```

### With filters

```bash
./bin/bblslug --model=vendor:name --format=html --source=input.html --translated=output.html --filters=url,html_pre
```

### Dry-run

```bash
./bin/bblslug --model=vendor:name --format=html --source=input.html --translated=output.html --dry-run
```

### Verbose output

```bash
./bin/bblslug --model=vendor:name --format=text --source=input.txt --translated=output.txt --verbose
```

### Alternative usage via Composer (requires linking or package installation)

```bash
vendor/bin/bblslug --model=vendor:name --format=html --source=input.html --translated=output.html
```

## Examples

You can find sample input files under the `examples/` directory.

## License

Private project, not yet open-source.
