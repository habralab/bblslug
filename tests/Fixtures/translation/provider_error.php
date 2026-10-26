<?php

declare(strict_types=1);

namespace Bblslug {
    /** Controlled HTTP response for facade error redaction tests. */
    class HttpClient
    {
        public static int $requestCount = 0;

        /** @var array{status:int,body:string,verbose:bool} */
        private static array $response = [
            'status' => 500,
            'body' => '',
            'verbose' => false,
        ];

        public static function configure(int $status, string $body, bool $verbose): void
        {
            self::$response = [
                'status' => $status,
                'body' => $body,
                'verbose' => $verbose,
            ];
        }

        /** @return array{body:string,debugRequest:string,debugResponse:string,headers:array<string,array<int,string>>,status:int} */
        public static function request(...$arguments): array
        {
            self::$requestCount++;

            return [
                'body' => self::$response['body'],
                'debugRequest' => self::$response['verbose'] ? '[request debug]' : '',
                'debugResponse' => self::$response['verbose'] ? '[response debug]' . self::$response['body'] : '',
                'headers' => [],
                'status' => self::$response['status'],
            ];
        }
    }
}

namespace {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--')) {
            [$name, $value] = array_pad(explode('=', $argument, 2), 2, '');
            $options[substr($name, 2)] = $value;
        }
    }

    $body = base64_decode((string)($options['body'] ?? ''), true);
    if ($body === false) {
        fwrite(STDERR, "Invalid body encoding\n");
        exit(2);
    }

    \Bblslug\HttpClient::configure(
        (int)($options['status'] ?? 500),
        $body,
        ($options['verbose'] ?? '0') === '1'
    );

    require dirname(__DIR__, 3) . '/vendor/autoload.php';

    $variables = [];
    if (($options['folder-id'] ?? '') !== '') {
        $variables['folder_id'] = $options['folder-id'];
    }

    try {
        $translation = \Bblslug\Bblslug::translate(
            apiKey: (string)($options['api-key'] ?? ''),
            format: 'text',
            modelKey: (string)($options['model'] ?? ''),
            text: isset($options['input']) ? (string) base64_decode($options['input'], true) : 'source text',
            variables: $variables,
            validate: false,
            verbose: ($options['verbose'] ?? '0') === '1'
        );
        $result = [
            'type' => 'success',
            'result' => $translation['result'],
            'message' => '',
            'requestCount' => \Bblslug\HttpClient::$requestCount,
        ];
    } catch (\Throwable $exception) {
        $result = [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'requestCount' => \Bblslug\HttpClient::$requestCount,
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}
