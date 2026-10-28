<?php

declare(strict_types=1);

namespace Bblslug\Translation {
    function ftruncate($handle, int $size): bool
    {
        return $GLOBALS['recoveryFault'] === 'ftruncate' ? false : \ftruncate($handle, $size);
    }

    function fflush($handle): bool
    {
        return $GLOBALS['recoveryFault'] === 'fflush' ? false : \fflush($handle);
    }

    function fsync($handle): bool
    {
        return $GLOBALS['recoveryFault'] === 'fsync' ? false : \fsync($handle);
    }
}

namespace {
    $GLOBALS['recoveryFault'] = $argv[1];
    require __DIR__ . '/checkpoint.php';
}
