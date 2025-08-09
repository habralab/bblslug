<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\JsonValidator;

final class JsonValidatorTest extends TestCase
{
    /** @test */
    public function validJsonPasses(): void
    {
        $v = new JsonValidator();
        $this->assertTrue($v->validate('{"a":1,"b":[2,3]}')->isValid());
    }

    /** @test */
    public function invalidJsonFails(): void
    {
        $v = new JsonValidator();
        $res = $v->validate('{"a":}');
        $this->assertFalse($res->isValid());
        $this->assertNotEmpty($res->getErrors());
        $this->assertStringContainsString('JSON syntax error', $res->getErrors()[0]);
    }

    /** @test */
    public function emptyStringIsInvalid(): void
    {
        $v = new JsonValidator();
        $this->assertFalse($v->validate('')->isValid());
    }

    /** @test */
    public function unicodeJsonPasses(): void
    {
        $v = new JsonValidator();
        $this->assertTrue($v->validate('{"text":"привет 🌍"}')->isValid());
    }
}
