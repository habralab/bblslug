# Babelium

**Babelium** is a CLI-powered DeepL translation wrapper inspired by classic sci-fi visions of instant language translation devices.

It supports HTML and plain text files, preserving structure, code blocks, and raw URLs using compact placeholders. Designed for automation, batch processing, and integration with scripts.

## Features

- Supports `html` and `text` formats
- Placeholder-based protection for:
  - `<pre>`, `<code>` blocks
  - URL attributes and raw links
- Dry-run mode with intermediate output
- Free/Pro DeepL endpoint toggle
- Compact and reusable CLI

## Usage

### HTML

```bash
./bin/babelium --format=html --source=input.html --translated=output.html
```

### Text

```bash
./bin/babelium --format=text --source=notes.txt --translated=notes_en.txt
```

### Dry-run

Save intermediate version with placeholders only:

```bash
./bin/babelium --format=html --source=input.html --translated=output.html --dry-run
```

### Alternative usage via Composer (requires linking or package installation)

```bash
vendor/bin/babelium --format=html --source=input.html --translated=output.html
```

## Environment

Set your DeepL API key via environment:

```bash
export DEEPL_API_KEY=your-key-here
```

## License

Internal project — not publicly licensed.


### Direct without Composer

If you're not using Composer install, you can run the script directly:

```bash
php babelium.php --format=html --source=input.html --translated=output.html
```
