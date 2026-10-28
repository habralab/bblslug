<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\TextDocument;
use Bblslug\Translation\UnprotectedText;
use PHPUnit\Framework\TestCase;

final class SegmentExtractionTest extends TestCase
{
    public function testAdjacentNumericMarkersLeaveNonnumericTextTranslatable(): void
    {
        $document = new TextDocument('@@1@@@@02@@a@@x@@b@@3@@');
        self::assertTrue($document->hasTexts());
        self::assertSame('{"s0":"a@@x@@b"}', $document->getJson());
        self::assertSame('@@1@@@@02@@translated@@3@@', $document->restore('{"s0":"translated"}'));
    }

    /** @dataProvider scanKinds */
    public function testFailedScanCannotSilentlySkipText(string $kind): void
    {
        $previousLimit = ini_set('pcre.backtrack_limit', '0');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not extract text segments');
        try {
            if ($kind === 'line') {
                new TextDocument('Visible');
            } else {
                iterator_to_array(UnprotectedText::ranges('Before@@0@@After'));
            }
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previousLimit);
        }
    }

    /** @return list<array{string}> */
    public static function scanKinds(): array
    {
        return [['line'], ['placeholder']];
    }
}
