<?php

declare(strict_types=1);

namespace Bblslug {
    /** Any accidental live request must fail locally, never reach a provider. */
    class HttpClient
    {
        public static function request(...$arguments): array
        {
            fwrite(STDERR, "Unexpected live HTTP request\n");
            exit(71);
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    if (in_array('--provider-only', $argv, true)) {
        require dirname(__DIR__, 2) . '/Integration/LiveTranslatePerVendorTest.php';
        $test = new \Bblslug\Tests\Integration\LiveTranslatePerVendorTest('translatesSmallTextSnippet');
        echo json_encode($test->providerLiveVendors(), JSON_THROW_ON_ERROR);
    } else {
        \PHPUnit\TextUI\Command::main();
    }
}
