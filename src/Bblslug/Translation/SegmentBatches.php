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

        try {
            $decoded = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid segment JSON', 0, $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException('Segment JSON must be an object');
        }

        $expected = array_keys(get_object_vars($decoded));
        foreach ($expected as $key) {
            if (preg_match('/\As\d+\z/', $key) !== 1) {
                throw new \InvalidArgumentException('Segment JSON contains an invalid segment id');
            }
        }

        try {
            $values = SegmentTranslations::decode($payload, $expected);
        } catch (\RuntimeException $exception) {
            throw new \InvalidArgumentException('Invalid segment source payload', 0, $exception);
        }
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
