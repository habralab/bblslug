<?php

declare(strict_types=1);

namespace Bblslug\Tests\Models\Drivers;

use PHPUnit\Framework\TestCase;
use Bblslug\Models\Drivers\GoogleDriver;
use RuntimeException;

/**
 * Google-specific finishReason handling:
 * - unexpected reasons (e.g., SAFETY) must throw with helpful message
 */
final class GoogleDriverFinishReasonTest extends TestCase
{
    /** @test */
    public function unexpectedFinishReasonThrows(): void
    {
        $driver = new GoogleDriver();
        $json = json_encode([
            'candidates' => [[
                'finishReason' => 'SAFETY',
                'content' => ['parts' => [['text' => '‹‹TRANSLATION››X‹‹END››']]],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unexpected finishReason .*SAFETY/i');
        $driver->parseResponse([], $json);
    }
}
