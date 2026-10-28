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
    private const TEXT_CONTAINERS = [
        'address', 'article', 'aside', 'blockquote', 'button', 'caption', 'dd', 'details',
        'dialog', 'div', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'label', 'legend',
        'li', 'main', 'nav', 'option', 'p', 'section', 'summary', 'td', 'th', 'title', 'textarea',
    ];
    private const OPAQUE_ELEMENTS = [
        'pre', 'code', 'script', 'style', 'xmp', 'iframe', 'noembed', 'noframes',
        'noscript', 'plaintext', 'svg', 'math',
    ];

    private string $expectedTree = '';
    /** One byte per content candidate, in captureContent traversal order. */
    private string $requiredContent = '';

    /** @var array{leadingFence:?string,trailingFence:?string,fenceRuns:array<int,string>} */
    private array $expectedFences;

    public function __construct(string $source)
    {
        $structure = self::structure($source);
        $document = $structure['document'];
        unset($structure['document']);
        $this->expectedFences = $structure;
        // Retain exact records, not per-node PHP arrays or a second live DOM.
        foreach (self::capture($document) as $record) {
            $this->expectedTree .= $record;
        }
        foreach (self::captureContent($document, '$') as $nonempty) {
            $this->requiredContent .= $nonempty ? '1' : '0';
        }
    }

    public function validate(string $content): ValidationResult
    {
        $actual = self::structure($content);
        $document = $actual['document'];
        unset($actual['document']);
        if ($this->expectedFences !== $actual || !$this->matchesTree($document)) {
            return ValidationResult::failure([
                'Markup, non-translatable attributes, and Markdown fences must match the source HTML',
            ]);
        }
        // Equal structure guarantees that content candidates have the same order.
        $missing = [];
        $index = 0;
        foreach (self::captureContent($document, '$') as $path => $nonempty) {
            if ($this->requiredContent[$index] === '1' && !$nonempty && count($missing) < 10) {
                $missing[] = $path;
            }
            ++$index;
        }
        if ($missing !== []) {
            return ValidationResult::failure([
                'Nonempty HTML text or translatable attribute became empty at ' . implode(', ', $missing),
            ]);
        }
        return ValidationResult::success();
    }

    private function matchesTree(DOMDocument $document): bool
    {
        $offset = 0;
        foreach (self::capture($document) as $record) {
            $length = strlen($record);
            if (substr_compare($this->expectedTree, $record, $offset, $length) !== 0) {
                return false;
            }
            $offset += $length;
        }
        return $offset === strlen($this->expectedTree);
    }

    /**
     * @return array{document:DOMDocument,leadingFence:?string,trailingFence:?string,fenceRuns:array<int,string>}
     */
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
        $leadingMatch = preg_match('/\A\s*((?:`{3,}|~{3,})[^\r\n]*)\R/', $content, $leading);
        $trailingMatch = preg_match('/(?:\A|\R)[\t ]*((?:`{3,}|~{3,}))[\t ]*\s*\z/', $content, $trailing);
        $fenceMatch = preg_match_all(
            '/`{3,}|~{3,}/',
            html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $fences
        );
        if ($leadingMatch === false || $trailingMatch === false || $fenceMatch === false) {
            throw new \RuntimeException('Could not inspect Markdown fences in HTML');
        }
        return [
            'document' => $dom,
            // libxml treats fences as text; preserve delimiters and boundary labels separately.
            'leadingFence' => $leadingMatch === 1 ? rtrim($leading[1], "\t ") : null,
            'trailingFence' => $trailingMatch === 1 ? $trailing[1] : null,
            'fenceRuns' => $fences[0],
        ];
    }

    /** @return \Generator<string,bool,mixed,bool> Nonempty candidates in stable structural order. */
    private static function captureContent(DOMNode $node, string $path, bool $opaque = false): \Generator
    {
        if ($node instanceof DOMText) {
            return !$opaque && preg_match('/\A\s*\z/u', $node->nodeValue ?? '') !== 1;
        }
        if ($node instanceof DOMElement) {
            foreach (self::TRANSLATABLE_ATTRIBUTES as $attribute) {
                if ($node->hasAttribute($attribute)) {
                    yield $path . '/@' . $attribute => preg_match('/\A\s*\z/u', $node->getAttribute($attribute)) !== 1;
                }
            }
            $opaque = $opaque || in_array($node->nodeName, self::OPAQUE_ELEMENTS, true);
        }
        $hasText = false;
        $index = 0;
        foreach ($node->childNodes as $child) {
            $childPath = $child instanceof DOMText ? $path : $path . '/' . $child->nodeName . '[' . ++$index . ']';
            // Visit every child even when an earlier child already contained text.
            $childContent = self::captureContent($child, $childPath, $opaque);
            yield from $childContent;
            $hasText = $childContent->getReturn() || $hasText;
        }
        if (
            !$opaque
            && ($node instanceof DOMDocument
                || ($node instanceof DOMElement && in_array($node->nodeName, self::TEXT_CONTAINERS, true)))
        ) {
            yield $path => $hasText;
        }
        return $hasText;
    }

    /** @return \Generator<int,string> Exact node records; depth preserves nesting without child arrays. */
    private static function capture(DOMNode $node, int $depth = 0): \Generator
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
        yield serialize([
            'depth' => $depth,
            'type' => $node->nodeType,
            'name' => $node->nodeName,
            'attributes' => $attributes,
            'value' => $node instanceof DOMComment || $node instanceof DOMProcessingInstruction
                ? $node->nodeValue
                : null,
            'doctype' => $node instanceof DOMDocumentType
                ? [$node->publicId, $node->systemId, $node->internalSubset]
                : null,
        ]);
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMText) {
                yield from self::capture($child, $depth + 1);
            }
        }
    }
}
