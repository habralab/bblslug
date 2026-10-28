<?php

declare(strict_types=1);

namespace Bblslug\Tests\Translation;

use Bblslug\Translation\SegmentTranslations;
use PHPUnit\Framework\TestCase;

final class SegmentTranslationsTest extends TestCase
{
    /** @dataProvider invalidTranslations */
    public function testDecodePreservesFailurePrecedence(string $payload, array $expected, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        SegmentTranslations::decode($payload, $expected, 'Text');
    }

    /** @return iterable<string, array{string, list<string>, string}> */
    public static function invalidTranslations(): iterable
    {
        yield 'mismatch before duplicate and invalid value' => [
            '{"s0":"ok","\\u00730":null}', ['s0', 's1'],
            'Text segment ids mismatch: missing [s1]; unexpected count 0',
        ];
        yield 'escaped duplicate before invalid value' => [
            '{"s0":"ok","\\u00730":null}', ['s0'], 'Duplicate Text segment ids',
        ];
        yield 'nested duplicate before invalid value' => [
            '{"s0":{"inner":"a","inner":"b"}}', ['s0'], 'Duplicate Text segment ids',
        ];
        yield 'nested key duplicate with outer key before invalid value' => [
            '{"s0":{"s0":"a"}}', ['s0'], 'Duplicate Text segment ids',
        ];
        yield 'nested unique key invalid value' => [
            '{"s0":{"inner":"a"}}', ['s0'], 'Text segments require nonempty translated strings',
        ];
    }

    public function testDecodePreservesOrderAndIgnoresKeyLikeTextInValues(): void
    {
        $values = ['s2' => 's0": value', 's0' => 'literal "s2": and colon: yes', 's01' => 'last'];
        $payload = json_encode($values, JSON_THROW_ON_ERROR);
        self::assertSame($values, SegmentTranslations::decode($payload, ['s01', 's0', 's2']));
    }

    public function testSourcePreservesLeadingZerosAndOrder(): void
    {
        self::assertSame(
            ['s01' => 'first', 's1' => 'second', 's000' => 'third'],
            SegmentTranslations::decodeSource('{"s01":"first","s1":"second","s000":"third"}', true)
        );
    }

    /** @dataProvider invalidSources */
    public function testSourcePreservesFailureAndPreviousException(
        string $payload,
        bool $bounded,
        string $message,
        ?string $previousMessage
    ): void {
        try {
            SegmentTranslations::decodeSource($payload, $bounded);
            self::fail('Expected an invalid source exception');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame($previousMessage, $exception->getPrevious()?->getMessage());
        }
    }

    /** @dataProvider scanFailureOperations */
    public function testFailedTokenScanCannotAcceptDuplicateIds(bool $source): void
    {
        $previousLimit = ini_set('pcre.backtrack_limit', '1');
        $failure = null;
        try {
            $payload = '{"s0":"first","s0":"second"}';
            if ($source) {
                SegmentTranslations::decodeSource($payload);
            } else {
                SegmentTranslations::decode($payload, ['s0']);
            }
        } catch (\Throwable $exception) {
            $failure = $exception;
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previousLimit);
        }
        if ($source) {
            self::assertInstanceOf(\InvalidArgumentException::class, $failure);
            self::assertSame('Invalid segment source payload', $failure->getMessage());
            $failure = $failure->getPrevious();
        }
        self::assertInstanceOf(\RuntimeException::class, $failure);
        self::assertSame('Could not inspect HTML segment ids', $failure->getMessage());
    }

    /** @return list<array{bool}> */
    public static function scanFailureOperations(): array
    {
        return [[false], [true]];
    }

    /** @return iterable<string, array{string, bool, string, ?string}> */
    public static function invalidSources(): iterable
    {
        $overflow = 's' . PHP_INT_MAX . '0';
        yield 'invalid numeric-looking id before duplicate' => [
            '{"01":"ok","s0":"a","s0":"b"}', false,
            'Segment JSON contains an invalid segment id', null,
        ];
        yield 'overflow before invalid id' => [
            '{"' . $overflow . '":"ok","bad":"ok"}', true,
            'Segment JSON contains an out-of-range segment id', null,
        ];
        yield 'invalid id before overflow' => [
            '{"bad":"ok","' . $overflow . '":"ok"}', true,
            'Segment JSON contains an invalid segment id', null,
        ];
        yield 'escaped duplicate wrapped' => [
            '{"s0":"ok","\\u00730":"again"}', false,
            'Invalid segment source payload', 'Duplicate HTML segment ids',
        ];
    }
}
