<?php

declare(strict_types=1);

use Bblslug\Translation\BatchCheckpoint;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$profile = $argv[1] ?? '';
if (!in_array($profile, ['plain', 'escaped'], true)) {
    throw new InvalidArgumentException('Unknown checkpoint memory profile');
}
$path = $argv[2];
$count = 32;
$fingerprint = 'memory-fixture';
$size = $profile === 'plain' ? 512 * 1024 : 256 * 1024;
$piece = $profile === 'plain' ? 'x' : '\\"';
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
$expected = hash_init('sha256');
hash_update(
    $expected,
    '{"version":1,"fingerprint":"' . $fingerprint . '","packetCount":32,"contiguousPrefixRecords":['
);
$checksums = [];
$checkpoint = new BatchCheckpoint($path, $fingerprint, $count);
for ($index = 0; $index < $count; ++$index) {
    $record = [
        'text' => $index . ':' . str_repeat($piece, intdiv($size, strlen($piece))),
        'httpStatus' => 200,
        'consumed' => ['tokens' => ['total' => 10]],
    ];
    $encoded = json_encode($record, $flags);
    hash_update($expected, ($index === 0 ? '' : ',') . $encoded);
    $checksums[] = hash('sha256', $encoded);
    unset($encoded);
    $checkpoint->append($index, $record);
    if ($checkpoint->get($index) !== $record) {
        throw new RuntimeException('Checkpoint changed a saved record');
    }
}
hash_update($expected, '],"checksums":' . json_encode($checksums, $flags) . "}\n");
$digest = hash_final($expected);
if (hash_file('sha256', $path) !== $digest || $checkpoint->get($count) !== null) {
    throw new RuntimeException('Checkpoint snapshot differs from version-1 bytes');
}
clearstatcache(true, $path);
echo json_encode([
    'records' => $count,
    'fileBytes' => filesize($path),
    'peak' => memory_get_peak_usage(true),
    'digest' => $digest,
], JSON_THROW_ON_ERROR);
