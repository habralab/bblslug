<?php

namespace Bblslug\Validation;

use Bblslug\Validation\ValidationResult;
use Bblslug\Validation\ValidatorInterface;

class JsonValidator implements ValidatorInterface
{
    /**
     * Validate JSON syntax using json_decode
     *
     * @param string $content
     * @return ValidationResult
     */
    public function validate(string $content): ValidationResult
    {
        try {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ValidationResult::failure([
                'JSON syntax error: ' . $e->getMessage()
            ]);
        }
        return ValidationResult::success();
    }
}
