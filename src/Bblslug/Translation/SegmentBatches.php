<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** Split a keyed segment document into bounded, independently translatable packets. */
final class SegmentBatches
{
    /**
     * @return list<string>
     */
    public static function split(string $payload, int $size): array
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Segment batch size must be positive');
        }

        $values = SegmentTranslations::decodeSource($payload);
        if ($values === []) {
            return [];
        }

        $batches = [];
        foreach (array_chunk($values, $size, true) as $batch) {
            /** @var array<string, string> $batch */
            $batches[] = json_encode(
                (object) $batch,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        }

        return $batches;
    }
}
