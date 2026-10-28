<?php

declare(strict_types=1);

namespace Bblslug\Tests\Unit\Translation;

use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\JsonHtmlDocument;
use Bblslug\Translation\SegmentBatchState;
use Bblslug\Translation\SegmentPlan;
use Bblslug\Translation\TextDocument;
use PHPUnit\Framework\TestCase;

final class RestoredSegmentValuesTest extends TestCase
{
    public function testDocumentsRestoreArraysAndStringsEquivalently(): void
    {
        $documents = [
            new TextDocument("  Alpha  \r\nBeta @@1@@ Gamma\n"),
            new HtmlTextDocument('<p title="A &amp; B"> Hello &amp; world </p><img alt=Hello>'),
            new JsonHtmlDocument('{"keep":1.00,"a":"<p>A &amp; B</p>","b":"<i>C</i>"}', ['/a', '/b'], ['url']),
        ];
        foreach ($documents as $document) {
            $source = json_decode($document->getJson(), true, 512, JSON_THROW_ON_ERROR);
            foreach (
                [$source, array_reverse($source, true),
                array_map(static fn(string $value): string => 'T & "' . $value, $source)] as $values
            ) {
                $before = $values;
                $json = json_encode((object) $values, JSON_THROW_ON_ERROR);
                self::assertSame($document->restore($json), $document->restoreValues($values));
                self::assertSame($before, $values);
                self::assertSame($document->restoreValues($values), $document->restoreValues($values));
            }
        }
    }

    public function testPlanRestoresSplitAndGappedIdsEquivalently(): void
    {
        foreach (
            ['{}', '{"s0":"one  two three four five six","s9":"tail"}',
            '{"s20":"first  ","s0":"second  ","s9":"third  "}'] as $source
        ) {
            $plan = new SegmentPlan($source, 2, 24);
            $values = [];
            foreach ($plan->getPayloads() as $payload) {
                $values += json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            }
            foreach (
                [$values, array_reverse($values, true),
                array_map(static fn(string $value): string => 'T:' . $value, $values)] as $translated
            ) {
                $before = $translated;
                $json = json_encode((object) $translated, JSON_THROW_ON_ERROR);
                self::assertSame(
                    json_decode($plan->restore($json), true, 512, JSON_THROW_ON_ERROR),
                    $plan->restoreValues($translated)
                );
                self::assertSame($before, $translated);
            }
        }
    }

    public function testInvalidRepresentableMapsMatchStringErrorsAndPriority(): void
    {
        $documents = [new TextDocument('one'), new HtmlTextDocument('<p>one</p>'),
            new JsonHtmlDocument('{"x":"<p>one</p>"}', ['/x']), new SegmentPlan('{"s0":"one"}', null, 50)];
        $maps = [
            ['s1' => null], [0 => 'one'], ['s0' => null], ['s0' => 42], ['s0' => '  '],
            ['s0' => "line\n@@1@@"], ['s0' => '@@1@@'], ['s0' => '', 'extra' => null],
        ];
        foreach ($documents as $document) {
            foreach ($maps as $values) {
                $json = json_encode((object) $values, JSON_THROW_ON_ERROR);
                $errors = [];
                $calls = [static fn() => $document->restore($json), static fn() => $document->restoreValues($values)];
                foreach ($calls as $call) {
                    try {
                        $call();
                        $errors[] = null;
                    } catch (\RuntimeException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                self::assertSame($errors[0], $errors[1]);
                self::assertNotNull($errors[0]);
            }
        }
    }

    public function testMissingIdsUseLexicalFirstTenAndExactUnexpectedCount(): void
    {
        $document = new TextDocument(implode("\n", array_fill(0, 15, 'word')));
        $values = ['s14' => 'word', 'unexpected' => 'value'];
        $expected = 'Text segment ids mismatch: missing [s0, s1, s10, s11, s12, s13, s2, s3, s4, s5]; '
            . 'unexpected count 1';
        foreach (
            [static fn() => $document->restoreValues($values),
            static fn() => $document->restore(json_encode((object) $values, JSON_THROW_ON_ERROR))] as $call
        ) {
            try {
                $call();
                self::fail('Expected missing segment IDs to be rejected');
            } catch (\RuntimeException $e) {
                self::assertSame($expected, $e->getMessage());
            }
        }
        self::assertSame(['s14' => 'word', 'unexpected' => 'value'], $values);
    }

    public function testInvalidUtf8ArrayValuesAreRejected(): void
    {
        $documents = [new TextDocument('one'), new HtmlTextDocument('<p>one</p>'),
            new JsonHtmlDocument('{"x":"<p>one</p>"}', ['/x']), new SegmentPlan('{"s0":"one"}', null, 50)];
        foreach ($documents as $document) {
            $values = ['s0' => "bad\xFF"];
            try {
                $document->restoreValues($values);
                self::fail('Expected invalid UTF-8 to be rejected');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('segments require nonempty translated strings', $e->getMessage());
            }
            self::assertSame(['s0' => "bad\xFF"], $values);
        }
    }

    public function testBatchSummaryAndTranslationsAreNonConsuming(): void
    {
        $state = new SegmentBatchState([], 'Text', false, null, null);
        $this->assertStateViews($state);
        $state->acceptRetained(0, '{"s0":"source"}', [
            'text' => '{"s0":"ancien"}', 'httpStatus' => 201, 'consumed' => ['tokens' => ['total' => 7]],
        ]);
        $state->acceptFresh(1, '{"s2":"source"}', [
            'text' => '{"s2":"nouveau"}', 'httpStatus' => 202, 'usage' => null,
        ]);
        $this->assertStateViews($state);
    }

    private function assertStateViews(SegmentBatchState $state): void
    {
        foreach ([true, false] as $reuse) {
            $result = $state->result($reuse);
            $expected = json_decode($result['text'], true, 512, JSON_THROW_ON_ERROR);
            unset($result['text']);
            self::assertSame($result, $state->summary($reuse));
            self::assertSame($expected, $state->translations());
            self::assertSame($result, $state->summary($reuse));
            self::assertSame($expected, $state->translations());
        }
    }
}
