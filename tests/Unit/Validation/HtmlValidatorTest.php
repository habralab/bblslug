<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\HtmlValidator;

final class HtmlValidatorTest extends TestCase
{
    /** @test */
    public function validFragmentPasses(): void
    {
        $v = new HtmlValidator();
        $this->assertTrue($v->validate('<p>Hello <strong>world</strong></p>')->isValid());
    }

    /** @test */
    public function fullDocumentWithHtml5TagsPasses(): void
    {
        $html = '<!DOCTYPE html><html><body><section><article>ok</article></section></body></html>';
        $v = new HtmlValidator();
        $this->assertTrue($v->validate($html)->isValid());
    }

    /** @test */
    public function unclosedTagFails(): void
    {
        $v = new HtmlValidator();
        $res = $v->validate('<p><strong>oops</p>');
        $this->assertFalse($res->isValid());
        $this->assertNotEmpty($res->getErrors());
    }

    /** @test */
    public function obviouslyBrokenMarkupFails(): void
    {
        $v = new HtmlValidator();
        $res = $v->validate('<p> <- broken >> </span>');
        $this->assertFalse($res->isValid());
    }
    /** @dataProvider callerErrorModes */
    public function testCallerErrorModeIsRestored(bool $mode): void
    {
        $previous = libxml_use_internal_errors($mode);
        try {
            self::assertTrue((new HtmlValidator())->validate('<p>Valid</p>')->isValid());
            self::assertSame($mode, libxml_use_internal_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<string,array{bool}> */
    public static function callerErrorModes(): array
    {
        return ['internal' => [true], 'external' => [false]];
    }

    public function testPreviousXmlErrorsDoNotInvalidateValidHtml(): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $dom = new \DOMDocument();
            self::assertFalse($dom->loadXML('<root>'));
            self::assertNotEmpty(libxml_get_errors());
            self::assertTrue((new HtmlValidator())->validate('<p>Valid</p>')->isValid());
            self::assertTrue(libxml_use_internal_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
