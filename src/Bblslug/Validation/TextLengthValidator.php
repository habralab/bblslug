<?php

declare(strict_types=1);

namespace Bblslug\Validation;

class TextLengthValidator implements ValidatorInterface
{
    private int $limitChars;
    private int $overheadChars;

    /**
     * @param int $limitChars   Hard cap for prepared input length (in chars)
     * @param int $overheadChars Safety buffer to account for prompts/markers/etc.
     */
    public function __construct(int $limitChars, int $overheadChars = 2000)
    {
        $this->limitChars   = max(0, $limitChars - max(0, $overheadChars));
        $this->overheadChars = $overheadChars;
    }

    public function validate(string $content): ValidationResult
    {
        $len = mb_strlen($content);
        if ($this->limitChars > 0 && $len > $this->limitChars) {
            $excess = $len - $this->limitChars;
            return ValidationResult::failure([
                sprintf(
                    'Prepared text length %d exceeds limit %d by %d chars (includes %d overhead). ' .
                    'Split input or reduce max output tokens.',
                    $len,
                    $this->limitChars,
                    $excess,
                    $this->overheadChars
                )
            ]);
        }
        return ValidationResult::success();
    }

    /**
     * Build validator from model config.
     * Uses estimated_max_chars, max_tokens and (if present) max_output_tokens.
     *
     * @param array<string,mixed> $model
     * @param int $fallbackReservePct  Reserve percent when max_output_tokens unknown (e.g. 20).
     * @param int $overheadChars       Prompt/markers safety buffer (e.g. 2000).
     */
    public static function fromModelConfig(array $model, int $fallbackReservePct = 20, int $overheadChars = 2000): self
    {
        $limits = $model['limits'] ?? [];
        if (!is_array($limits)) {
            $limits = [];
        }

        /**
         * Values can come as int or numeric-string from YAML.
         * @var array{
         *   estimated_max_chars?: int|string,
         *   max_tokens?: int|string,
         *   max_output_tokens?: int|string
         * } $limits
         */

        $estimatedMaxChars = (int) ($limits['estimated_max_chars'] ?? 0);
        $maxTokens         = (int) ($limits['max_tokens'] ?? 0);
        $maxOutTokens      = (int) ($limits['max_output_tokens'] ?? 0);

        // Prefer a token-based calculation if we know both totals.
        if ($maxTokens > 0) {
            $reservedOut = $maxOutTokens > 0
                ? $maxOutTokens
                : (int)max(1, floor($maxTokens * ($fallbackReservePct / 100)));

            $inputTokenBudget = max(0, $maxTokens - $reservedOut);
            $charsByTokens    = $inputTokenBudget * 4; // ≈ 4 chars/token heuristic

            $limitChars = $estimatedMaxChars > 0
                ? min($estimatedMaxChars, $charsByTokens)
                : $charsByTokens;
        } else {
            // No token info — rely only on estimated_max_chars
            $limitChars = $estimatedMaxChars;
        }

        return new self($limitChars, $overheadChars);
    }
}
