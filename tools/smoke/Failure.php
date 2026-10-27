<?php

declare(strict_types=1);

namespace Bblslug\Tools\Smoke;

final class Failure
{
    public static function primary(string $message): string
    {
        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', $message) ?? 'Unprintable smoke error';
        if (preg_match('/(?:^|\n)Error:\s*([^\n]+)/', $clean, $match) === 1) {
            return trim($match[1]);
        }
        return trim(explode("\n", $clean, 2)[0]);
    }

    public static function category(string $message): string
    {
        $primary = self::primary($message);
        if (
            preg_match(
                '/HTTP (?:401|402|403|404|456)\b|credit balance|quota exceeded|insufficient_quota|billing/i',
                $primary
            )
        ) {
            return 'account_or_model';
        }
        if (preg_match('/truncated|finish_reason=length|output.*token.*limit/i', $primary)) {
            return 'output_budget';
        }
        if (preg_match('/timed out|timeout|deadline/i', $primary)) {
            return 'timeout';
        }
        if (preg_match('/checkpoint|identity mismatch/i', $primary)) {
            return 'checkpoint';
        }
        if (preg_match('/empty translation|empty.*output|translation.*empty/i', $primary)) {
            return 'empty_response';
        }
        return 'response_or_assertion';
    }
}
