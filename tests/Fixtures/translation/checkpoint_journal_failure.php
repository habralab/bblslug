<?php

declare(strict_types=1);

namespace Bblslug\Translation {
    function fwrite($handle, string $data): int|false
    {
        if ($GLOBALS['fault'] === 'fwrite') {
            return false;
        }
        if ($GLOBALS['fault'] === 'partial') {
            if ($GLOBALS['writes']++ === 0) {
                return \fwrite($handle, substr($data, 0, max(1, intdiv(strlen($data), 2))));
            }
            return false;
        }
        return \fwrite($handle, $data);
    }

    function fflush($handle): bool
    {
        return $GLOBALS['fault'] === 'fflush' ? false : \fflush($handle);
    }

    function fsync($handle): bool
    {
        return $GLOBALS['fault'] === 'fsync' ? false : \fsync($handle);
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $GLOBALS['fault'] = '';
    $GLOBALS['writes'] = 0;
    $path = $argv[1];
    $record = ['text' => 'saved', 'httpStatus' => 200, 'consumed' => []];
    $checkpoint = new \Bblslug\Translation\BatchCheckpoint($path, 'fingerprint', 2);
    $checkpoint->append(0, $record);
    $GLOBALS['fault'] = $argv[2];
    $failed = false;
    try {
        $checkpoint->append(1, $record);
    } catch (\RuntimeException) {
        $failed = true;
    }
    $GLOBALS['fault'] = '';
    $prefixIntact = $checkpoint->get(0) === $record && $checkpoint->get(1) === null;
    $retryRejected = false;
    try {
        $checkpoint->append(1, $record);
    } catch (\RuntimeException) {
        $retryRejected = true;
    }
    unset($checkpoint);
    $resumed = new \Bblslug\Translation\BatchCheckpoint($path, 'fingerprint', 2);
    $recoveredSecond = $resumed->get(1) === $record;
    if (!$recoveredSecond) {
        $resumed->append(1, $record);
    }
    $resumeComplete = $resumed->get(0) === $record && $resumed->get(1) === $record;
    echo json_encode(
        compact('failed', 'retryRejected', 'prefixIntact', 'recoveredSecond', 'resumeComplete'),
        JSON_THROW_ON_ERROR
    );
}
