<?php

declare(strict_types=1);

namespace Bblslug {
    /** Echo segment requests without provider credentials or HTTP. */
    class HttpClient
    {
        public static int $calls = 0;

        /** @return array{status:int,body:string,headers:array<mixed>,debugRequest:string,debugResponse:string} */
        public static function request(...$arguments): array
        {
            ++self::$calls;
            $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            $text = trim((string) preg_replace(
                '/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u',
                '',
                $request['messages'][1]['content']
            ));
            return [
                'status' => 200,
                'body' => json_encode(['choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => "‹‹TRANSLATION››\n" . $text . "\n‹‹END››"],
                ]]], JSON_THROW_ON_ERROR),
                'headers' => [],
                'debugRequest' => '',
                'debugResponse' => '',
            ];
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    if (($argv[1] ?? '') === 'api') {
        $source = str_repeat('<p>Hello</p>', intdiv(1024 * 1024, 12));
        $result = \Bblslug\Bblslug::translate(
            modelKey: 'openai:gpt-5-mini',
            apiKey: 'fixture-key',
            text: $source,
            format: 'html',
            filters: [],
            validate: true,
            htmlTransport: 'segments',
            htmlSegmentBatchSize: 64,
            segmentBatchMaxBytes: 4096,
            variables: ['json_transport' => 'markers']
        );
        if ($result['result'] !== $source || \Bblslug\HttpClient::$calls < 1000) {
            throw new \RuntimeException('Segmented HTML memory fixture did not restore all packets');
        }
    } else {
        $unit = '<p title="Hint">Hello</p>';
        $source = str_repeat($unit, 50000);
        $validator = new \Bblslug\Validation\HtmlStructureValidator($source);
        if (!$validator->validate($source)->isValid()) {
            throw new \RuntimeException('Dense HTML identity failed');
        }
        $cleared = substr($source, 0, -strlen($unit)) . '<p title="Hint"></p>';
        $failure = $validator->validate($cleared);
        $expectedErrors = [
            'Nonempty HTML text or translatable attribute became empty at $/div[1]/p[50000]',
        ];
        if ($failure->getErrors() !== $expectedErrors) {
            throw new \RuntimeException('Dense HTML lost its final text-loss check');
        }
        if (!$validator->validate(str_replace('Hello', 'Hallo', $source))->isValid()) {
            throw new \RuntimeException('Dense HTML validator did not recover after failure');
        }
    }
    echo json_encode([
        'bytes' => strlen($source),
        'calls' => \Bblslug\HttpClient::$calls,
        'peak' => memory_get_peak_usage(true),
    ], JSON_THROW_ON_ERROR);
}
