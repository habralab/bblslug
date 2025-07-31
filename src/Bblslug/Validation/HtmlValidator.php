<?php

namespace Bblslug\Validation;

use Bblslug\Validation\ValidationResult;
use Bblslug\Validation\ValidatorInterface;
use DOMDocument;

class HtmlValidator implements ValidatorInterface
{
    /**
     * Validate basic HTML structure using DOMDocument.
     * Supports both full documents and fragments by wrapping fragments.
     * Ignores generic libxml 'Tag ... invalid' warnings for HTML5 tags.
     *
     * @param string $content  HTML document or fragment to validate
     * @return ValidationResult
     */
    public function validate(string $content): ValidationResult
    {
        // Enable internal error handling
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        // Wrap fragments in a div to ensure valid context
        $isFullDoc = preg_match('/^\s*<(?:!DOCTYPE|html)(\s|>)/i', $content);
        $html = $isFullDoc
            ? $content
            : '<div>' . $content . '</div>';

        // Parse HTML
        $dom->loadHTML(
            $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        // Gather parse errors
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (empty($errors)) {
            return ValidationResult::success();
        }

        // Filter out generic 'Tag ... invalid' warnings
        $messages = [];
        foreach ($errors as $error) {
            $msg = trim($error->message);
            // Ignore libxml warnings about unknown tags (semantic HTML5 tags)
            if (preg_match('/Tag \w+ invalid/', $msg)) {
                continue;
            }
            $messages[] = $msg;
        }

        // If all errors ignored, consider valid
        if (empty($messages)) {
            return ValidationResult::success();
        }

        return ValidationResult::failure($messages);
    }
}
