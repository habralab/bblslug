<?php

declare(strict_types=1);

if (getenv('BBLSLUG_SMOKE_OFFLINE') === '1') {
    require __DIR__ . '/offline-http.php';
}
require dirname(__DIR__, 2) . '/bin/bblslug';
