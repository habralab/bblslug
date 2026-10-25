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

    /** @var array<string,true> */
    private array $requiredContent;


    /** @var array<string,mixed> */
    private array $expected;

    public function __construct(string $source)
    {
        $expected = self::structure($source);
        $this->requiredContent = $expected['nonempty'];
        unset($expected['nonempty']);
        $this->expected = $expected;
    }

    public function validate(string $content): ValidationResult
    {
        $actual = self::structure($content);
        $nonempty = $actual['nonempty'];
        unset($actual['nonempty']);
        if ($this->expected !== $actual) {
            return ValidationResult::failure([
                'Markup, non-translatable attributes, and Markdown fences must match the source HTML',
            ]);
        }
        $missing = array_diff_key($this->requiredContent, $nonempty);
        if ($missing !== []) {
            return ValidationResult::failure([
                'Nonempty HTML text or translatable attribute became empty at '
                . implode(', ', array_slice(array_keys($missing), 0, 10)),
            ]);
        }
        return ValidationResult::success();
    }

    /**
     * @return array{tree:array<string,mixed>,nonempty:array<string,true>,leadingFence:?string,
     *     trailingFence:?string,fenceRuns:array<int,string>}
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
        $nonempty = [];
        self::captureContent($dom, '$', $nonempty);
        return [
            'tree' => self::capture($dom),
            'nonempty' => $nonempty,
            // libxml treats fences as text; preserve delimiters and boundary labels separately.
            'leadingFence' => $leadingMatch === 1 ? rtrim($leading[1], "\t ") : null,
            'trailingFence' => $trailingMatch === 1 ? $trailing[1] : null,
            'fenceRuns' => $fences[0],
        ];
    }

    /** @param array<string,true> $nonempty */
    private static function captureContent(DOMNode $node, string $path, array &$nonempty, bool $opaque = false): bool
    {
        if ($node instanceof DOMText) {
            return !$opaque && preg_match('/\A\s*\z/u', $node->nodeValue ?? '') !== 1;
        }
        if ($node instanceof DOMElement) {
            foreach (self::TRANSLATABLE_ATTRIBUTES as $attribute) {
                if (
                    $node->hasAttribute($attribute)
                    && preg_match('/\A\s*\z/u', $node->getAttribute($attribute)) !== 1
                ) {
                    $nonempty[$path . '/@' . $attribute] = true;
                }
            }
            $opaque = $opaque || in_array($node->nodeName, self::OPAQUE_ELEMENTS, true);
        }
        $hasText = false;
        $index = 0;
        foreach ($node->childNodes as $child) {
            $childPath = $child instanceof DOMText ? $path : $path . '/' . $child->nodeName . '[' . ++$index . ']';
            // Visit every child even when an earlier child already contained text.
            $hasText = self::captureContent($child, $childPath, $nonempty, $opaque) || $hasText;
        }
        if (
            !$opaque && $hasText
            && ($node instanceof DOMDocument
                || ($node instanceof DOMElement && in_array($node->nodeName, self::TEXT_CONTAINERS, true)))
        ) {
            $nonempty[$path] = true;
        }
        return $hasText;
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
