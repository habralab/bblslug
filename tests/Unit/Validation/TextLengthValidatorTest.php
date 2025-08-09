<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\TextLengthValidator;

final class TextLengthValidatorTest extends TestCase
{
    /** @test */
    public function respectsLimitWithOverhead(): void
    {
        // Internal limit = limit - overhead.
        $v = new TextLengthValidator(limitChars: 1000, overheadChars: 200);
        // => internal limit is 800
        $ok  = str_repeat('a', 800);
        $bad = str_repeat('a', 801);

        $this->assertTrue($v->validate($ok)->isValid());
        $this->assertFalse($v->validate($bad)->isValid());
    }

    /** @test */
    public function zeroInternalLimitMeansNoCap(): void
    {
        // If overhead >= limit => internal limit becomes 0 => no restriction.
        $v = new TextLengthValidator(limitChars: 1500, overheadChars: 5000);
        $long = str_repeat('x', 100_000);
        $this->assertTrue($v->validate($long)->isValid());
    }

    /** @test */
    public function multibyteLengthIsCounted(): void
    {
        $v = new TextLengthValidator(limitChars: 5, overheadChars: 0);
        $this->assertFalse($v->validate('привет')->isValid()); // 6 characters
        $this->assertTrue($v->validate('мир')->isValid());     // 3 characters
    }

    /** @test */
    public function fromModelConfigWithTokensAndOverheadZeroCaps(): void
    {
        $model = ['limits' => ['max_tokens' => 100, 'max_output_tokens' => 20]];
        // inputBudget = 80 tokens ≈ 320 characters
        $v = TextLengthValidator::fromModelConfig($model, fallbackReservePct: 20, overheadChars: 0);

        $this->assertTrue($v->validate(str_repeat('a', 320))->isValid());
        $this->assertFalse($v->validate(str_repeat('a', 321))->isValid());
    }

    /** @test */
    public function fromModelConfigWithDefaultOverheadOftenLeadsToNoCap(): void
    {
        $model = ['limits' => ['max_tokens' => 100, 'max_output_tokens' => 20]];
        // Default overhead (2000) > 320 => internal limit is 0 => no restriction
        $v = TextLengthValidator::fromModelConfig($model);
        $this->assertTrue($v->validate(str_repeat('b', 10_000))->isValid());
    }
}
