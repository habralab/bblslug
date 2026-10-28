<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\SegmentPlan;

$values = [];
for ($index = 0; $index < 100000; ++$index) {
    $values['s' . $index] = 'Hello';
}
$payload = json_encode((object) $values, JSON_THROW_ON_ERROR);
unset($values);
$plan = new SegmentPlan($payload, 64, 4096);
$index = 0;
foreach ($plan->getPayloads() as $packet) {
    $values = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);
    if (strlen($packet) > 4096 || count($values) > 64) {
        throw new RuntimeException('Segment plan memory fixture exceeded packet limits');
    }
    foreach ($values as $key => $value) {
        if ($key !== 's' . $index || $value !== 'Hello') {
            throw new RuntimeException('Segment plan memory fixture changed packet values or order');
        }
        ++$index;
    }
}
unset($values);
if ($plan->restore($payload) !== $payload) {
    throw new RuntimeException('Segment plan memory fixture changed identity restoration');
}
$translated = str_replace('"Hello"', '"Hallo"', $payload);
if ($plan->restore($translated) !== $translated) {
    throw new RuntimeException('Segment plan memory fixture changed translated restoration');
}
echo json_encode([
    'bytes' => strlen($payload),
    'count' => $index,
    'packets' => count($plan->getPayloads()),
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
