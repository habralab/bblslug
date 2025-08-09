<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\Schema;

final class SchemaTest extends TestCase
{
    /** @test */
    public function captureBuildsTypesAndShapes(): void
    {
        $in = [
            'id' => 123,
            'name' => 'alice',
            'tags' => ['x', 'y'],
            'meta' => ['ok' => true, 'score' => 1.5],
        ];

        $schema = Schema::capture($in);

        $this->assertSame('integer', $schema['id']);
        $this->assertSame('string', $schema['name']);
        $this->assertIsArray($schema['tags']);      // list of element types
        $this->assertSame('string', $schema['tags'][0]);
        $this->assertSame('boolean', $schema['meta']['ok']);
        $this->assertSame('double', $schema['meta']['score']);
    }

    /** @test */
    public function validateReportsMismatch(): void
    {
        $before = Schema::capture(['a' => 1, 'b' => [true, false]]);
        $after  = Schema::capture(['a' => '1', 'b' => [true, 0]]);
        $res = Schema::validate($before, $after);

        $this->assertFalse($res->isValid());
        $this->assertNotEmpty($res->getErrors());
    }

    /** @test */
    public function validateSuccessOnSameShapeWithDifferentValues(): void
    {
        // Same keys and node structure; values differ (e.g., translated strings).
        $original = [
            'article' => [
                'name' => 'Some Name',
                'description' => 'Some Description',
                'tags' => ['one', 'two'],      // list length matches
                'meta' => ['lang' => 'en'],
            ],
        ];

        $translated = [
            'article' => [
                'name' => 'Какое-то имя',
                'description' => 'Какое-то описание',
                'tags' => ['один', 'два'],     // same length, different values
                'meta' => ['lang' => 'ru'],
            ],
        ];

        $a = \Bblslug\Validation\Schema::capture($original);
        $b = \Bblslug\Validation\Schema::capture($translated);

        $this->assertTrue(\Bblslug\Validation\Schema::validate($a, $b)->isValid());
    }
}
