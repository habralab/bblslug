<?php

declare(strict_types=1);

namespace Bblslug {
    /** Echo bounded segment packets without provider credentials or HTTP. */
    class HttpClient
    {
        public static int $calls = 0;
        public static ?\HashContext $packets = null;

        /** @return array{status:int,body:string,headers:array<mixed>,debugRequest:string,debugResponse:string} */
        public static function request(...$arguments): array
        {
            ++self::$calls;
            $request = json_decode($arguments['body'], true, 512, JSON_THROW_ON_ERROR);
            $payload = trim((string) preg_replace(
                '/^‹‹TRANSLATION››\s*|\s*‹‹END››$/u',
                '',
                $request['messages'][1]['content']
            ));
            $values = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (strlen($payload) > 4096 || count($values) > 64) {
                throw new \RuntimeException('Word planning API fixture exceeded packet limits');
            }
            self::$packets ??= hash_init('sha256');
            hash_update(self::$packets, $payload . "\0");
            return [
                'status' => 200,
                'body' => json_encode(['choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => "‹‹TRANSLATION››\n" . $payload . "\n‹‹END››"],
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

    $kind = $argv[1] ?? '';
    $abbreviations = [];
    if (in_array($kind, ['long-word', 'rejected-word', 'api-rejected'], true)) {
        $value = str_repeat('a', 4 * 1024 * 1024);
    } elseif (in_array($kind, ['initial-chain', 'middle-chain'], true)) {
        $value = ($kind === 'initial-chain' ? " \t" : "prefix words \t")
            . str_repeat('Dr. ', 300000) . "Name \r\n";
        $abbreviations = ['Dr.'];
    } else {
        $bytes = $kind === 'api' ? 1024 * 1024 : 2 * 1024 * 1024;
        $value = str_repeat('word ', intdiv($bytes, 5));
    }
    $api = in_array($kind, ['api', 'api-rejected'], true);
    $reject = in_array($kind, ['rejected-word', 'api-rejected'], true);
    $cap = $kind === 'long-word' ? 8 * 1024 * 1024 : 4096;
    $source = $api ? '<p>' . $value . '</p>'
        : json_encode(['s0' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $digest = '';
    try {
        if ($api) {
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
            if ($result['result'] !== $source || \Bblslug\HttpClient::$calls < 100) {
                throw new \RuntimeException('Word planning API fixture changed source or request count');
            }
            $digest = hash_final(\Bblslug\HttpClient::$packets);
        } else {
            $plan = new \Bblslug\Translation\SegmentPlan($source, 64, $cap, $abbreviations);
            $values = [];
            $packets = hash_init('sha256');
            foreach ($plan->getPayloads() as $payload) {
                $packet = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                if (strlen($payload) > $cap || count($packet) > 64) {
                    throw new \RuntimeException('Word planning fixture exceeded packet limits');
                }
                hash_update($packets, $payload . "\0");
                $values += $packet;
            }
            $digest = hash_final($packets);
            if ($plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)) !== $source) {
                throw new \RuntimeException('Word planning fixture changed identity restoration');
            }
            foreach ($values as &$translation) {
                $translation = strtoupper($translation);
            }
            unset($translation);
            $expected = json_encode(['s0' => strtoupper($value)], JSON_THROW_ON_ERROR);
            if ($plan->restore(json_encode((object) $values, JSON_THROW_ON_ERROR)) !== $expected) {
                throw new \RuntimeException('Word planning fixture changed translated restoration');
            }
        }
        if ($reject) {
            throw new \RuntimeException('An oversized word was accepted');
        }
        $outcome = 'restored';
    } catch (\InvalidArgumentException $exception) {
        if (!$reject || $exception->getMessage() !== 'Segment value cannot fit within the byte cap') {
            throw $exception;
        }
        if (\Bblslug\HttpClient::$calls !== 0) {
            throw new \RuntimeException('Oversized word reached the provider before preflight finished');
        }
        $outcome = 'rejected';
    }
    echo json_encode([
        'bytes' => strlen($source),
        'outcome' => $outcome,
        'calls' => \Bblslug\HttpClient::$calls,
        'packetHash' => $digest,
        'peak' => memory_get_peak_usage(true),
    ], JSON_THROW_ON_ERROR);
}
