<?php

declare(strict_types=1);

namespace Bblslug\Validation;

use DOMComment;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;

/** Compare parsed markup while allowing text and natural-language attribute translation. */
class HtmlStructureValidator implements ValidatorInterface
{
    private const TRANSLATABLE_ATTRIBUTES = ['title', 'alt', 'aria-label'];

    /** @var array<string,mixed> */
    private array $expected;

    public function __construct(string $source)
    {
        $this->expected = self::structure($source);
    }

    public function validate(string $content): ValidationResult
    {
        if ($this->expected === self::structure($content)) {
            return ValidationResult::success();
        }
        return ValidationResult::failure([
            'Tags, nesting, and non-translatable attributes must match the source HTML',
        ]);
    }

    /** @return array<string,mixed> */
    private static function structure(string $content): array
    {
        $isFullDoc = preg_match('/^\s*<(?:!DOCTYPE|html)(\s|>)/i', $content);
        $html = $isFullDoc ? $content : '<div>' . $content . '</div>';
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // Explicit encoding keeps literal UTF-8 and equivalent entities comparable.
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new \RuntimeException('Could not inspect HTML structure');
        }
        // Remove only the encoding instruction inserted above.
        if ($dom->firstChild instanceof DOMProcessingInstruction) {
            $dom->removeChild($dom->firstChild);
        }
        return self::capture($dom);
    }

    /** @return array<string,mixed> */
    private static function capture(DOMNode $node): array
    {
        $attributes = [];
        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                /** @var \DOMAttr $attribute */
                $attributes[$attribute->nodeName] = in_array(
                    $attribute->nodeName,
                    self::TRANSLATABLE_ATTRIBUTES,
                    true
                ) ? null : $attribute->nodeValue;
            }
            ksort($attributes, SORT_STRING);
        }
        $children = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMText) {
                $children[] = self::capture($child);
            }
        }
        return [
            'type' => $node->nodeType,
            'name' => $node->nodeName,
            'attributes' => $attributes,
            'value' => $node instanceof DOMComment || $node instanceof DOMProcessingInstruction
                ? $node->nodeValue
                : null,
            'doctype' => $node instanceof DOMDocumentType
                ? [$node->publicId, $node->systemId, $node->internalSubset]
                : null,
            'children' => $children,
        ];
    }
}
