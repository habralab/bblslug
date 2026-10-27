<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Diagnostics\ContentPreview;
use Bblslug\SecretMasker;
use Bblslug\Validation\EmbeddedJsonValidator;
use Bblslug\Validation\HtmlStructureValidator;
use Bblslug\Validation\HtmlValidator;
use Bblslug\Validation\JsonHtmlValidator;
use Bblslug\Validation\JsonScalarValidator;
use Bblslug\Validation\JsonStringContentValidator;
use Bblslug\Validation\JsonValidator;
use Bblslug\Validation\Schema;

/** @internal Source and result container validation within one translation operation. */
final class DocumentValidation
{
    private mixed $sourceSchema = null;
    private ?JsonHtmlValidator $jsonHtmlValidator = null;

    /** @param array<int,string> $htmlJsonPointers */
    public function __construct(
        private readonly string $format,
        private readonly string $source,
        private readonly bool $validateEmbeddedJson,
        private readonly array $htmlJsonPointers,
        private readonly bool $verbose,
        private readonly SecretMasker $masker
    ) {
    }

    /** Validate before filters and return the existing source-validation diagnostic. */
    public function validateSource(): string
    {
        if (\in_array($this->format, ['json', 'structured_json', 'linedJson'], true)) {
            $preResult = (new JsonValidator())->validate($this->source);
            if (!$preResult->isValid()) {
                throw new \RuntimeException('JSON syntax failed: ' . implode('; ', $preResult->getErrors()));
            }
            if ($this->htmlJsonPointers !== []) {
                $this->jsonHtmlValidator = new JsonHtmlValidator($this->source, $this->htmlJsonPointers);
            }
            $this->sourceSchema = Schema::capture(json_decode($this->source, false));
            return $this->verbose ? "[JSON schema captured]\n" : '';
        }
        if ($this->format === 'html') {
            $preResult = (new HtmlValidator())->validate($this->source);
            if (!$preResult->isValid()) {
                throw new \RuntimeException('HTML validation failed: ' . implode('; ', $preResult->getErrors()));
            }
            return $this->verbose ? "[HTML validation pre-pass]\n" : '';
        }
        return '';
    }

    /** Validate the restored result and return response diagnostics with the validation log. */
    public function validateTranslation(string $result, string $debugRequest, string $debugResponse): string
    {
        $valLogPost = '';
        if (\in_array($this->format, ['json', 'structured_json', 'linedJson'], true)) {
            // Capture the source schema if the session has no retained snapshot.
            if ($this->sourceSchema === null) {
                $this->sourceSchema = Schema::capture(\json_decode($this->source, false));
            }

            // JSON syntax check
            $postResult = (new JsonValidator())->validate($result);
            if (! $postResult->isValid()) {
                if ($this->verbose) {
                    $debugResponse .= "\n[Restored JSON (invalid) dump]\n";
                    $debugResponse .= ContentPreview::render($result, $this->masker, 2000) . "\n";
                }
                throw new \RuntimeException(
                    "JSON syntax broken: " . implode('; ', $postResult->getErrors())
                    . "\n\n" . $debugRequest . $debugResponse
                );
            }

            // Decode produced JSON
            /** @var mixed $parsedOut */
            $parsedOut = \json_decode($result, false);

            // Validate the structure of the JSON that will actually be returned.
            $schemaOut = Schema::capture($parsedOut);
            $schemaValidation = Schema::validate($this->sourceSchema, $schemaOut);

            if (! $schemaValidation->isValid()) {
                throw new \RuntimeException(
                    "Schema mismatch: "
                    . implode('; ', $schemaValidation->getErrors())
                    . "\n\n" . $debugRequest . $debugResponse
                );
            }
            $scalarValidation = (new JsonScalarValidator($this->source))->validate($result);
            if (!$scalarValidation->isValid()) {
                throw new \RuntimeException(
                    "JSON scalar values changed: " . implode('; ', $scalarValidation->getErrors())
                );
            }
            $stringValidation = (new JsonStringContentValidator($this->source))->validate($result);
            if (!$stringValidation->isValid()) {
                throw new \RuntimeException(
                    "JSON string content lost: " . implode('; ', $stringValidation->getErrors())
                );
            }
            if ($this->jsonHtmlValidator !== null) {
                $htmlResult = $this->jsonHtmlValidator->validate($result);
                if (!$htmlResult->isValid()) {
                    throw new \RuntimeException(
                        "HTML inside JSON mismatch: " . implode('; ', $htmlResult->getErrors())
                    );
                }
            }
            if ($this->validateEmbeddedJson) {
                $embeddedResult = (new EmbeddedJsonValidator($this->source))->validate($result);
                if (!$embeddedResult->isValid()) {
                    throw new \RuntimeException(
                        "Embedded JSON mismatch: " . implode('; ', $embeddedResult->getErrors())
                    );
                }
            }
            if ($this->verbose) {
                $valLogPost .= "[JSON schema validated]\n";
            }
        } elseif ($this->format === 'html') {
            $htmlValidator = new HtmlValidator();
            $postResult = $htmlValidator->validate($result);
            if (! $postResult->isValid()) {
                if ($this->verbose) {
                    $debugResponse .= "\n[Restored HTML (invalid) dump]\n";
                    $debugResponse .= ContentPreview::render($result, $this->masker, 2000) . "\n";
                }
                throw new \RuntimeException(
                    "HTML validation failed: "
                    . implode('; ', $postResult->getErrors())
                    . "\n\n" . $debugRequest . $debugResponse
                );
            }
            $structureResult = (new HtmlStructureValidator($this->source))->validate($result);
            if (!$structureResult->isValid()) {
                if ($this->verbose) {
                    $debugResponse .= "\n[Restored HTML (invalid) dump]\n";
                    $debugResponse .= ContentPreview::render($result, $this->masker, 2000) . "\n";
                }
                throw new \RuntimeException(
                    "HTML structure mismatch: " . implode('; ', $structureResult->getErrors())
                    . ($this->verbose ? "\n\n" . $debugRequest . $debugResponse : '')
                );
            }
            if ($this->verbose) {
                $valLogPost = "[HTML validation post-pass]\n";
            }
        } // other formats: no container validation

        return $debugResponse . ($this->verbose ? $valLogPost : '');
    }
}
