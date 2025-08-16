<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use PHPUnit\Framework\TestCase;
use Bblslug\Validation\Schema;

use function array_is_list;

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

    /** @test */
    public function applyRepairsIsNoopWithoutFlags(): void
    {
        $before = ['a' => null, 'b' => 1];
        $after  = ['b' => 1];
        $fixed  = Schema::applyRepairs($before, $after, []);

        // No flags -> nothing should change
        $this->assertSame($after, $fixed);
    }

    /** @test */
    public function repairMissingNullsRestoresObjectKeysWithNull(): void
    {
        $before = [
            'a' => null,
            'b' => 1,
            'c' => [
                'x' => null,
                'y' => 2,
            ],
        ];
        // 'a' and 'c.x' missing in $after; both were null in $before
        // Non-null 'b' is intentionally dropped to ensure we don't invent values
        $after = [
            'c' => ['y' => 2],
        ];

        $fixed = Schema::applyRepairs($before, $after, [Schema::REPAIR_MISSING_NULLS]);

        $this->assertArrayHasKey('a', $fixed);
        $this->assertNull($fixed['a']);
        $this->assertArrayHasKey('c', $fixed);
        $this->assertArrayHasKey('x', $fixed['c']);
        $this->assertNull($fixed['c']['x']);
        // Non-null 'b' must NOT be recreated
        $this->assertArrayNotHasKey('b', $fixed);
    }

    /** @test */
    public function repairMissingNullsRestoresListSlotsThatWereNull(): void
    {
        $before = [null, null];
        $after  = []; // model dropped both nulls

        $fixed = Schema::applyRepairs($before, $after, [Schema::REPAIR_MISSING_NULLS]);

        // Should restore both nulls and keep list semantics (0..n-1)
        $this->assertTrue(array_is_list($fixed));
        $this->assertSame([null, null], $fixed);
    }

    /** @test */
    public function repairedOutputProducesSameCapturedSchema(): void
    {
        $original = [
            'src' => [
                'body'    => 'text',
                'preview' => null,
            ],
        ];
        // LLM removed the null node
        $translated = [
            'src' => [
                'body' => 'перевод',
            ],
        ];

        $fixed = Schema::applyRepairs($original, $translated, [Schema::REPAIR_MISSING_NULLS]);

        $a = Schema::capture($original);
        $b = Schema::capture($fixed);
        $this->assertTrue(Schema::validate($a, $b)->isValid());
    }
}
