<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal Format and argument compatibility for locally reconstructed segment transports. */
final class TransportValidation
{
    public static function validateText(
        string $format,
        string $textTransport,
        ?int $textSegmentBatchSize,
        bool $hasResponseSchema
    ): void {
        if ($textSegmentBatchSize !== null && ($textSegmentBatchSize < 1 || $textTransport !== 'segments')) {
            throw new \InvalidArgumentException(
                'textSegmentBatchSize requires text segments transport and a positive integer'
            );
        }
        if (!\in_array($textTransport, ['document', 'segments'], true)) {
            throw new \InvalidArgumentException('textTransport must be document or segments');
        }
        if ($textTransport === 'segments' && $format !== 'text') {
            throw new \InvalidArgumentException('textTransport segments requires format text');
        }
        if ($textTransport === 'segments' && $hasResponseSchema) {
            throw new \InvalidArgumentException('response_schema cannot describe a text segment transport payload');
        }
    }

    /**
     * Validate HTML transport and report whether it selects HTML strings inside JSON.
     *
     * @param array<int,string> $htmlJsonPointers
     */
    public static function validateHtml(
        string $format,
        string $htmlTransport,
        ?int $htmlSegmentBatchSize,
        array $htmlJsonPointers,
        bool $hasResponseSchema
    ): bool {
        if ($htmlSegmentBatchSize !== null && ($htmlSegmentBatchSize < 1 || $htmlTransport !== 'segments')) {
            throw new \InvalidArgumentException(
                'htmlSegmentBatchSize requires segments transport and a positive integer'
            );
        }
        if (!\in_array($htmlTransport, ['document', 'segments'], true)) {
            throw new \InvalidArgumentException('htmlTransport must be document or segments');
        }
        $jsonHtmlSegments = $htmlTransport === 'segments' && \in_array($format, ['json', 'linedJson'], true);
        if ($htmlTransport === 'segments' && !\in_array($format, ['html', 'json', 'linedJson'], true)) {
            throw new \InvalidArgumentException('htmlTransport segments requires format html, json, or linedJson');
        }
        if ($htmlJsonPointers !== [] && !$jsonHtmlSegments) {
            throw new \InvalidArgumentException('htmlJsonPointers requires JSON format and htmlTransport segments');
        }
        if ($jsonHtmlSegments && $htmlJsonPointers === []) {
            throw new \InvalidArgumentException('JSON HTML segments require explicit htmlJsonPointers');
        }
        if ($htmlTransport === 'segments' && $hasResponseSchema) {
            throw new \InvalidArgumentException('response_schema cannot describe an HTML segment transport payload');
        }
        return $jsonHtmlSegments;
    }
}
