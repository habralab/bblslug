<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\OperationDeadline;
use PHPUnit\Framework\TestCase;

final class OperationDeadlineTest extends TestCase
{
    public function testBudgetShrinksAndDoesNotReset(): void
    {
        $time = 10.0;
        $deadline = new OperationDeadline(2, static function () use (&$time): float {
            return $time;
        });
        self::assertSame(2000, $deadline->remainingMilliseconds());
        $time = 11.25;
        self::assertSame(750, $deadline->remainingMilliseconds());
        $time = 12.0;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(28);
        $deadline->check();
    }

    public function testInvalidAndVeryLargeBudgets(): void
    {
        self::assertGreaterThan(0, (new OperationDeadline(PHP_INT_MAX))->remainingMilliseconds());
        $this->expectException(\InvalidArgumentException::class);
        new OperationDeadline(0);
    }
}
