<?php

declare(strict_types=1);

namespace Bblslug\Tests\Filters;

use PHPUnit\Framework\TestCase;
use Bblslug\Filters\PlaceholderCounter;

/**
 * PlaceholderCounter monotonic numbering and format.
 */
final class PlaceholderCounterTest extends TestCase
{
    /** @test */
    public function incrementsAndFormatsPlaceholders(): void
    {
        $c = new PlaceholderCounter();

        $p0 = $c->next();
        $p1 = $c->next();
        $p2 = $c->next();

        $this->assertSame('@@0@@', $p0);
        $this->assertSame('@@1@@', $p1);
        $this->assertSame('@@2@@', $p2);
        $this->assertSame(3, $c->current());
    }
}
