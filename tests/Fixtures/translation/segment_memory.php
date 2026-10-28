<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\SegmentTranslations;

$values = [];
for ($index = 0; $index < 100000; ++$index) {
    $values['s' . $index] = 'Hello';
}
$payload = json_encode((object) $values, JSON_THROW_ON_ERROR);
unset($values);
if (($argv[1] ?? '') === 'source') {
    $values = SegmentTranslations::decodeSource($payload, true);
} else {
    $expected = [];
    for ($index = 0; $index < 100000; ++$index) {
        $expected[] = 's' . $index;
    }
    $values = SegmentTranslations::decode($payload, $expected);
}
$index = 0;
foreach ($values as $key => $value) {
    if ($key !== 's' . $index || $value !== 'Hello') {
        throw new RuntimeException('Segment memory fixture changed values or order');
    }
    ++$index;
}
echo json_encode([
    'bytes' => strlen($payload),
    'count' => count($values),
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
