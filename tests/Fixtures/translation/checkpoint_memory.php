<?php

declare(strict_types=1);

namespace Bblslug\Translation {
    function fwrite($handle, string $data): int|false
    {
        $written = \fwrite($handle, $data);
        if ($written !== false) {
            $GLOBALS['checkpointMemoryWrittenBytes'] += $written;
        }
        ++$GLOBALS['checkpointMemoryWrites'];
        return $written;
    }
}

namespace {
    use Bblslug\Translation\BatchCheckpoint;

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $profile = $argv[1] ?? '';
    if (!in_array($profile, ['plain', 'escaped'], true)) {
        throw new InvalidArgumentException('Unknown checkpoint memory profile');
    }
    $path = $argv[2];
    $count = 32;
    $journal = ($argv[3] ?? 'legacy') === 'journal';
    $legacyResume = ($argv[3] ?? '') === 'legacy-resume';
    $fingerprint = 'memory-fixture';
    $size = $profile === 'plain' ? 512 * 1024 : 256 * 1024;
    $piece = $profile === 'plain' ? 'x' : '\\"';
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    $expected = hash_init('sha256');
    if ($journal) {
        hash_update($expected, json_encode([
            'version' => 2, 'fingerprint' => $fingerprint, 'packetCount' => $count,
        ], $flags) . "\n");
    } else {
        file_put_contents($path, json_encode([
            'version' => 1, 'fingerprint' => $fingerprint, 'packetCount' => $count,
            'contiguousPrefixRecords' => [], 'checksums' => [],
        ], $flags) . "\n");
        hash_update(
            $expected,
            '{"version":1,"fingerprint":"' . $fingerprint . '","packetCount":32,"contiguousPrefixRecords":['
        );
    }
    $checksums = [];
    $GLOBALS['checkpointMemoryWrittenBytes'] = 0;
    $GLOBALS['checkpointMemoryWrites'] = 0;
    $checkpoint = new BatchCheckpoint($path, $fingerprint, $count);
    for ($index = 0; $index < $count; ++$index) {
        $record = [
            'text' => $index . ':' . str_repeat($piece, intdiv($size, strlen($piece))),
            'httpStatus' => 200,
            'consumed' => ['tokens' => ['total' => 10]],
        ];
        $encoded = json_encode($record, $flags);
        $checksum = hash('sha256', $encoded);
        if ($journal) {
            hash_update($expected, json_encode([
                'index' => $index, 'record' => $record, 'checksum' => $checksum,
            ], $flags) . "\n");
        } else {
            hash_update($expected, ($index === 0 ? '' : ',') . $encoded);
            $checksums[] = $checksum;
        }
        unset($encoded);
        $checkpoint->append($index, $record);
        if ($checkpoint->get($index) !== $record) {
            throw new RuntimeException('Checkpoint changed a saved record');
        }
    }
    if (!$journal) {
        hash_update($expected, '],"checksums":' . json_encode($checksums, $flags) . "}\n");
    }
    $digest = hash_final($expected);
    if (hash_file('sha256', $path) !== $digest || $checkpoint->get($count) !== null) {
        throw new RuntimeException('Checkpoint differs from expected serialized bytes');
    }
    if ($journal || $legacyResume) {
        unset($checkpoint, $record);
        $checkpoint = new BatchCheckpoint($path, $fingerprint, $count);
        for ($index = 0; $index < $count; ++$index) {
            $saved = $checkpoint->get($index);
            $text = $index . ':' . str_repeat($piece, intdiv($size, strlen($piece)));
            if ($saved !== ['text' => $text, 'httpStatus' => 200, 'consumed' => ['tokens' => ['total' => 10]]]) {
                throw new RuntimeException('Checkpoint resume changed a saved record');
            }
        }
    }
    clearstatcache(true, $path);
    if ($journal && $GLOBALS['checkpointMemoryWrittenBytes'] !== filesize($path)) {
        throw new RuntimeException('Journal rewrote previously saved bytes');
    }
    echo json_encode([
        'records' => $count,
        'fileBytes' => filesize($path),
        'peak' => memory_get_peak_usage(true),
        'digest' => $digest,
        'writtenBytes' => $GLOBALS['checkpointMemoryWrittenBytes'],
        'writes' => $GLOBALS['checkpointMemoryWrites'],
    ], JSON_THROW_ON_ERROR);
}
