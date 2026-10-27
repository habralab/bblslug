<?php

declare(strict_types=1);

namespace Bblslug\Tests;

use Bblslug\Bblslug;
use PHPUnit\Framework\TestCase;

final class BblslugTransportValidationTest extends TestCase
{
    /**
     * @dataProvider invalidOptions
     * @param array<string,mixed> $options
     */
    public function testValidationPrecedence(array $options, string $message): void
    {
        $feedback = [];
        $defaults = [
            'apiKey' => '',
            'format' => 'text',
            'modelKey' => 'UNKNOWN',
            'text' => 'Hello',
            'onFeedback' => static function (string $message, string $level) use (&$feedback): void {
                $feedback[] = [$message, $level];
            },
        ];

        try {
            Bblslug::translate(...array_replace($defaults, $options));
            self::fail('Expected invalid arguments');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame([], $feedback);
        }
    }

    /** @return array<string,array{array<string,mixed>,string}> */
    public static function invalidOptions(): array
    {
        return [
            'checkpoint before bytes' => [
                ['checkpointPath' => 'https://example.test', 'segmentBatchMaxBytes' => 0],
                'checkpointPath requires a local path and configured segment batching',
            ],
            'checkpoint requires batching' => [
                ['checkpointPath' => 'checkpoint.json', 'textTransport' => 'invalid'],
                'checkpointPath requires a local path and configured segment batching',
            ],
            'bytes before abbreviations and text mode' => [
                ['segmentBatchMaxBytes' => 0, 'segmentAbbreviations' => ['Dr.'], 'textTransport' => 'invalid'],
                'segmentBatchMaxBytes requires segments transport and a positive integer',
            ],
            'abbreviations before text batch' => [
                ['segmentAbbreviations' => ['Dr.'], 'textSegmentBatchSize' => 0, 'textTransport' => 'invalid'],
                'segmentAbbreviations requires segmentBatchMaxBytes',
            ],
            'text batch before text transport' => [
                ['textSegmentBatchSize' => 1, 'textTransport' => 'invalid'],
                'textSegmentBatchSize requires text segments transport and a positive integer',
            ],
            'text transport before html batch and mode' => [
                ['textTransport' => 'invalid', 'htmlSegmentBatchSize' => 0, 'htmlTransport' => 'invalid'],
                'textTransport must be document or segments',
            ],
            'text format before schema presence' => [
                ['textTransport' => 'segments', 'format' => 'html', 'variables' => ['response_schema' => null]],
                'textTransport segments requires format text',
            ],
            'text schema presence before html batch' => [
                [
                    'textTransport' => 'segments', 'variables' => ['response_schema' => null],
                    'htmlSegmentBatchSize' => 0,
                ],
                'response_schema cannot describe a text segment transport payload',
            ],
            'html batch before transport' => [
                ['htmlSegmentBatchSize' => 1, 'htmlTransport' => 'invalid'],
                'htmlSegmentBatchSize requires segments transport and a positive integer',
            ],
            'html batch before format' => [
                ['htmlSegmentBatchSize' => 0, 'htmlTransport' => 'segments'],
                'htmlSegmentBatchSize requires segments transport and a positive integer',
            ],
            'html transport before pointers' => [
                ['htmlTransport' => 'invalid', 'htmlJsonPointers' => ['/body']],
                'htmlTransport must be document or segments',
            ],
            'html format before pointers' => [
                ['htmlTransport' => 'segments', 'htmlJsonPointers' => ['/body']],
                'htmlTransport segments requires format html, json, or linedJson',
            ],
            'pointers outside json html segments' => [
                ['format' => 'json', 'htmlJsonPointers' => ['/body']],
                'htmlJsonPointers requires JSON format and htmlTransport segments',
            ],
            'json html segments require selection before schema' => [
                ['format' => 'json', 'htmlTransport' => 'segments', 'variables' => ['response_schema' => null]],
                'JSON HTML segments require explicit htmlJsonPointers',
            ],
            'html schema presence before reserved variables' => [
                ['format' => 'html', 'htmlTransport' => 'segments', 'variables' => [
                    'response_schema' => null, 'format' => 'reserved',
                ]],
                'response_schema cannot describe an HTML segment transport payload',
            ],
            'reserved variables after transport' => [
                ['variables' => ['format' => 'reserved']],
                'Reserved option in variables: format',
            ],
        ];
    }

    public function testValidOptionsReachModelLookupAfterStartFeedback(): void
    {
        $feedback = [];
        $defaults = [
            'apiKey' => '',
            'format' => 'text',
            'modelKey' => 'UNKNOWN',
            'text' => 'Hello',
            'onFeedback' => static function (string $message, string $level) use (&$feedback): void {
                $feedback[] = [$message, $level];
            },
        ];
        $options = ['textTransport' => 'segments', 'segmentBatchMaxBytes' => 100];

        try {
            Bblslug::translate(...array_replace($defaults, $options));
            self::fail('Expected unknown model');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Unknown model key: UNKNOWN', $exception->getMessage());
            self::assertSame(
                [["Starting translation (model='UNKNOWN', format='text', dryRun=false)", 'info']],
                $feedback
            );
        }
    }
}
