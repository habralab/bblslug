<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\ValidationResult;

final class ValidationResultTest extends TestCase
{
    /** @test */
    public function successAndFailureFactoriesWork(): void
    {
        $ok = ValidationResult::success();
        $this->assertTrue($ok->isValid());
        $this->assertSame([], $ok->getErrors());

        $fail = ValidationResult::failure(['err1', 'err2']);
        $this->assertFalse($fail->isValid());
        $this->assertSame(['err1', 'err2'], $fail->getErrors());
    }
}
