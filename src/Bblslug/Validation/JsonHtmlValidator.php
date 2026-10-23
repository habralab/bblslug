<?php

declare(strict_types=1);

namespace Bblslug\Validation;

/** Check HTML only at JSON string locations explicitly selected by the caller. */
class JsonHtmlValidator implements ValidatorInterface
{
    /** @var array<string,HtmlStructureValidator> */
    private array $validators = [];

    /** @param array<int,mixed> $pointers RFC 6901 JSON Pointers, without URI fragment encoding. */
    public function __construct(string $source, array $pointers)
    {
        $document = json_decode($source, false, 512, JSON_THROW_ON_ERROR);
        foreach ($pointers as $pointer) {
            if (
                !is_string($pointer)
                || ($pointer !== '' && $pointer[0] !== '/')
                || preg_match('/~(?![01])/', $pointer)
            ) {
                throw new \InvalidArgumentException('Invalid HTML JSON Pointer; use RFC 6901 string syntax');
            }
            $html = self::resolve($document, $pointer);
            $syntax = (new HtmlValidator())->validate($html);
            if (!$syntax->isValid()) {
                throw new \InvalidArgumentException(
                    'HTML syntax failed at JSON Pointer ' . self::label($pointer)
                    . ': ' . implode('; ', $syntax->getErrors())
                );
            }
            $this->validators[$pointer] = new HtmlStructureValidator($html);
        }
    }

    public function validate(string $content): ValidationResult
    {
        try {
            $document = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ValidationResult::failure(['Invalid translated JSON']);
        }
        $errors = [];
        foreach ($this->validators as $pointer => $validator) {
            try {
                $html = self::resolve($document, $pointer);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                continue;
            }
            if (!(new HtmlValidator())->validate($html)->isValid()) {
                $errors[] = 'HTML syntax changed at JSON Pointer ' . self::label($pointer);
            } elseif (!$validator->validate($html)->isValid()) {
                $errors[] = 'HTML structure changed at JSON Pointer ' . self::label($pointer);
            }
        }
        return $errors === [] ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    private static function resolve(mixed $document, string $pointer): string
    {
        $value = $document;
        $tokens = $pointer === '' ? [] : explode('/', substr($pointer, 1));
        foreach ($tokens as $token) {
            // Decode simultaneously: ~01 means the literal token ~1, not a slash.
            $token = strtr($token, ['~1' => '/', '~0' => '~']);
            if ($value instanceof \stdClass && property_exists($value, $token)) {
                $value = $value->{$token};
            } elseif (
                is_array($value)
                && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $token)
                && array_key_exists($token, $value)
            ) {
                $value = $value[$token];
            } else {
                throw new \InvalidArgumentException(
                    'JSON Pointer does not resolve to a string: ' . self::label($pointer)
                );
            }
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('JSON Pointer does not resolve to a string: ' . self::label($pointer));
        }
        return $value;
    }

    private static function label(string $pointer): string
    {
        return json_encode($pointer, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
