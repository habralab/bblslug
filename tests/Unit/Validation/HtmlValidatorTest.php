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
}
