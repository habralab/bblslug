<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled response for selected HTML in a large JSON container. */
    class HttpClient
    {
        public static int $calls = 0;

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
            if (json_decode($payload, true, 512, JSON_THROW_ON_ERROR) !== ['s0' => 'Hello']) {
                throw new \RuntimeException('Unselected JSON data reached the provider');
            }
            return [
                'status' => 200,
                'body' => json_encode(['choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => "‹‹TRANSLATION››\n{\"s0\":\"Hallo\"}\n‹‹END››"],
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
    if ($kind === 'long-token') {
        $source = str_repeat('x', 24 * 1024 * 1024);
        $prefix = '{"raw":"';
        $suffix = '","body":"<p>Hello</p>"}';
        foreach (str_split($prefix) as $index => $character) {
            $source[$index] = $character;
        }
        foreach (str_split($suffix) as $index => $character) {
            $source[strlen($source) - strlen($suffix) + $index] = $character;
        }
    } elseif ($kind === 'object') {
        $values = [];
        for ($index = 0; $index < 100000; ++$index) {
            $values['k' . $index] = $index;
        }
        $values['body'] = '<p>Hello</p>';
        $source = json_encode((object) $values, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        unset($values);
    } else {
        $numbers = json_encode(range(0, 249999), JSON_THROW_ON_ERROR);
        $source = '{"body":"<p>Hello</p>", "numbers":' . $numbers
            . ($kind === 'duplicate' ? ', "late":{"same":0,"\u0073ame":1}' : '') . '}';
        unset($numbers);
    }
    $expected = $kind === 'long-token' ? '' : str_replace('<p>Hello</p>', '<p>Hallo</p>', $source);
    if ($kind === 'api') {
        $result = \Bblslug\Bblslug::translate(
            modelKey: 'openai:gpt-5-mini',
            apiKey: 'fixture-key',
            text: $source,
            format: 'json',
            filters: [],
            validate: true,
            htmlTransport: 'segments',
            htmlJsonPointers: ['/body'],
            variables: ['json_transport' => 'markers']
        );
        if ($result['result'] !== $expected || \Bblslug\HttpClient::$calls !== 1) {
            throw new \RuntimeException('Large JSON API fixture changed the result or request count');
        }
        $outcome = 'translated';
    } else {
        $pointers = $kind === 'wrong-type' ? ['/body', '/numbers/249999'] : ['/body'];
        try {
            $document = new \Bblslug\Translation\JsonHtmlDocument($source, $pointers, ['url']);
            if (in_array($kind, ['duplicate', 'wrong-type'], true)) {
                throw new \RuntimeException('A late unselected constraint was not checked');
            }
            if (!$document->hasTexts() || $document->getJson() !== '{"s0":"Hello"}') {
                throw new \RuntimeException('Large JSON fixture changed selected text');
            }
            if ($kind === 'long-token') {
                $outcome = 'scanned';
            } else {
                if ($document->getPrepared() !== $source || $document->restore($document->getJson()) !== $source) {
                    throw new \RuntimeException('Large JSON fixture changed source bytes');
                }
                if ($document->restore('{"s0":"Hallo"}') !== $expected) {
                    throw new \RuntimeException('Large JSON fixture changed unselected bytes or translation');
                }
                if ($document->restore($document->getJson()) !== $source) {
                    throw new \RuntimeException('Large JSON fixture changed repeated restoration');
                }
                $outcome = 'restored';
            }
        } catch (\InvalidArgumentException $exception) {
            $error = match ($kind) {
                'duplicate' => 'Duplicate source JSON keys are ambiguous for HTML selection',
                'wrong-type' => 'HTML JSON Pointer does not resolve to a string: "/numbers/249999"',
                default => null,
            };
            if ($error === null || $exception->getMessage() !== $error) {
                throw $exception;
            }
            $outcome = 'rejected';
        }
    }
    echo json_encode([
        'bytes' => strlen($source),
        'outcome' => $outcome,
        'calls' => \Bblslug\HttpClient::$calls,
        'peak' => memory_get_peak_usage(true),
    ], JSON_THROW_ON_ERROR);
}
