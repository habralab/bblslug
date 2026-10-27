<?php

declare(strict_types=1);

namespace Bblslug\Tests\Tools;

use Bblslug\Tools\Smoke\Failure;
use PHPUnit\Framework\TestCase;

final class SmokeFailureTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/tools/smoke/Failure.php';
    }

    public function testVerboseBillingTextCannotHideAProtocolOrBudgetFailure(): void
    {
        $message = "CLI failure\nStarting batch\n\033[31mError:\033[0m Translation was truncated (finish_reason=length)"
            . "\nHeaders: billing-account\nBody: quota exceeded";
        self::assertSame('Translation was truncated (finish_reason=length)', Failure::primary($message));
        self::assertSame('output_budget', Failure::category($message));
        self::assertSame('output_budget', Failure::category('OpenAI response is incomplete: max_output_tokens'));
        self::assertSame('response_or_assertion', Failure::category("Missing segment s1\nBilling: quota exceeded"));
    }

    public function testOnlyTheActualAccountFailureBlocksOtherCases(): void
    {
        self::assertSame('account_or_model', Failure::category("CLI failure\nError: batch failed: HTTP 402 declined"));
        self::assertSame('account_or_model', Failure::category('HTTP 404 model not found'));
        self::assertSame('response_or_assertion', Failure::category('HTTP 429 rate limit, retry later'));
        self::assertSame('timeout', Failure::category('Operation timed out after 120000 milliseconds'));
    }
}
