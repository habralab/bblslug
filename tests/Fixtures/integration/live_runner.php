<?php

declare(strict_types=1);

namespace Bblslug {
    /** Any accidental live request must fail locally, never reach a provider. */
    class HttpClient
    {
        public static function request(...$arguments): array
        {
            if (getenv('BBLSLUG_TEST_LEAK_DIAGNOSTICS') === '1') {
                $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
                $input = $request['messages'][1]['content'];
                preg_match('/‹‹TRANSLATION››(.*?)‹‹END››/s', $input, $matches);
                $translated = str_replace(
                    ['Hello', 'world', 'Hi', 'there'],
                    ['Hallo', 'Welt', 'Hallo', 'dort'],
                    $matches[1]
                );
                return [
                    'status' => 200, 'headers' => [],
                    'body' => json_encode(['choices' => [['finish_reason' => 'stop', 'message' => [
                        'content' => "‹‹TRANSLATION››" . $translated . "‹‹END››",
                    ]]]], JSON_THROW_ON_ERROR),
                    'debugRequest' => (string) getenv('BBLSLUG_OPENAI_API_KEY'), 'debugResponse' => '',
                ];
            }
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
