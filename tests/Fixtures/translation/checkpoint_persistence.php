<?php

declare(strict_types=1);

namespace Bblslug\Translation {
    function fwrite($handle, string $data): int|false
    {
        ++$GLOBALS['checkpointWrites'];
        if ($GLOBALS['checkpointFault'] === 'fwrite') {
            return false;
        }
        if ($GLOBALS['checkpointFault'] === 'after-write' && $GLOBALS['checkpointWrites'] === 2) {
            return false;
        }
        if ($GLOBALS['checkpointFault'] === 'short-write') {
            return \fwrite($handle, substr($data, 0, max(1, intdiv(strlen($data), 2))));
        }
        return \fwrite($handle, $data);
    }

    function fflush($handle): bool
    {
        return $GLOBALS['checkpointFault'] === 'fflush' ? false : \fflush($handle);
    }

    function fsync($handle): bool
    {
        return $GLOBALS['checkpointFault'] === 'fsync' ? false : \fsync($handle);
    }

    function rename(string $from, string $to): bool
    {
        return $GLOBALS['checkpointFault'] === 'rename' ? false : \rename($from, $to);
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $GLOBALS['checkpointFault'] = '';
    $GLOBALS['checkpointWrites'] = 0;
    $path = $argv[1];
    $fault = $argv[2];
    $first = ['text' => 'saved', 'httpStatus' => 200, 'consumed' => []];
    $second = ['text' => str_repeat('large value ', 8000), 'httpStatus' => 201, 'consumed' => []];
    $checkpoint = new \Bblslug\Translation\BatchCheckpoint($path, 'fingerprint', 2);
    $checkpoint->append(0, $first);
    $before = file_get_contents($path);
    $GLOBALS['checkpointWrites'] = 0;
    $GLOBALS['checkpointFault'] = $fault;
    try {
        $checkpoint->append(1, $second);
        $error = null;
    } catch (\RuntimeException $exception) {
        $error = $exception->getMessage();
    }
    $writes = $GLOBALS['checkpointWrites'];
    $GLOBALS['checkpointFault'] = '';
    $lock = fopen($path . '.lock', 'r+b');
    $lockHeld = !flock($lock, LOCK_EX | LOCK_NB);
    if (!$lockHeld) {
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    $result = [
        'error' => $error,
        'unchanged' => file_get_contents($path) === $before,
        'first' => $checkpoint->get(0),
        'second' => $checkpoint->get(1),
        'temps' => glob(dirname($path) . '/.batch-checkpoint-*'),
        'lockHeld' => $lockHeld,
        'writes' => $writes,
    ];
    $checkpoint->append(1, $second);
    $result['retry'] = $checkpoint->get(1) === $second;
    unset($checkpoint);
    $resumed = new \Bblslug\Translation\BatchCheckpoint($path, 'fingerprint', 2);
    $result['resume'] = $resumed->get(0) === $first && $resumed->get(1) === $second;
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
