<?php

declare(strict_types=1);

namespace Bblslug\Validation;

use Bblslug\Validation\ValidationResult;

interface ValidatorInterface
{
    /**
     * Validate the container content (HTML, Markdown, etc.).
     *
     * @param string $content
     * @return ValidationResult
     */
    public function validate(string $content): ValidationResult;
}
