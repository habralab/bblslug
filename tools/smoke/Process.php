<?php

declare(strict_types=1);

namespace Bblslug\Tools\Smoke;

final class Process
{
    /** @param list<string> $command @param array<string,string> $environment
     *  @return array{exit:int,stdout:string,stderr:string,interrupted:bool,timedOut:bool}
     */
    public static function run(array $command, array $environment, int $seconds, ?string $stopFile = null): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            $environment
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start smoke subprocess');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $interrupted = false;
        $timedOut = false;
        $deadline = microtime(true) + $seconds;
        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $state = proc_get_status($process);
            if (!$state['running']) {
                break;
            }
            if (!$interrupted && $stopFile !== null && is_file($stopFile)) {
                $interrupted = true;
                proc_terminate($process, 9);
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(20000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closed = proc_close($process);
        return ['exit' => $state['exitcode'] >= 0 ? $state['exitcode'] : $closed,
            'stdout' => $stdout, 'stderr' => $stderr, 'interrupted' => $interrupted, 'timedOut' => $timedOut];
    }
}
