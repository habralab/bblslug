<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** Durable, resumable storage for a sequence of translated packets. */
final class BatchCheckpoint
{
    private const VERSION = 2;
    private const LEGACY_VERSION = 1;
    private const WRITE_BUFFER_BYTES = 64 * 1024;

    /** @var resource */
    private $lock;
    /** @var resource|null */
    private $journal = null;
    private bool $legacy = false;
    private string $path;
    private string $fingerprint;
    private int $packetCount;
    /** @var list<array{text:string,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>}> */
    private array $records = [];
    /** @var list<string> */
    private array $checksums = [];

    public function __construct(string $path, string $fingerprint, int $packetCount)
    {
        self::validatePath($path);
        if ($fingerprint === '' || str_contains($fingerprint, "\0") || $packetCount < 0) {
            throw new \InvalidArgumentException('Invalid checkpoint identity');
        }
        $parent = dirname($path);
        if (!is_dir($parent)) {
            throw new \RuntimeException('Checkpoint parent directory does not exist');
        }
        $this->path = $path;
        $this->fingerprint = $fingerprint;
        $this->packetCount = $packetCount;
        $this->lock = self::openLock($path . '.lock');

        if (file_exists($path) || is_link($path)) {
            self::assertRegular($path, 'checkpoint');
            $this->load();
        } else {
            $header = json_encode([
                'version' => self::VERSION, 'fingerprint' => $fingerprint, 'packetCount' => $packetCount,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $this->replaceFile([$header]);
            $this->load();
        }
    }

    public function __destruct()
    {
        if (is_resource($this->journal)) {
            fclose($this->journal);
        }
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
    }

    /** @return array{text:string,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>}|null */
    public function get(int $index): ?array
    {
        if ($index < 0 || $index >= count($this->records)) {
            return null;
        }
        return $this->records[$index];
    }

    /** @param array<string,mixed> $record */
    public function append(int $index, array $record): void
    {
        if ($index < 0 || $index !== count($this->records) || $index >= $this->packetCount) {
            throw new \InvalidArgumentException('Checkpoint append index is not the current prefix');
        }
        $record = self::normalizeRecord($record);
        $checksum = self::checksum($record);
        if (!$this->legacy) {
            $this->appendJournal($index, $record, $checksum);
            $this->records[] = $record;
            return;
        }
        $this->records[] = $record;
        $this->checksums[] = $checksum;
        try {
            $this->persistLegacy();
        } catch (\Throwable $exception) {
            array_pop($this->records);
            array_pop($this->checksums);
            throw new \RuntimeException('Could not persist checkpoint', 0, $exception);
        }
    }

    private function load(): void
    {
        $handle = @fopen($this->path, 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Could not open checkpoint for writing');
        }
        try {
            $first = fgets($handle);
            if ($first === false) {
                throw new \RuntimeException('Malformed checkpoint');
            }
            $header = json_decode($first, true);
            if (is_array($header) && ($header['version'] ?? null) === self::VERSION) {
                $this->loadJournal($handle, $first);
                $this->journal = $handle;
                $handle = null;
            } else {
                unset($header);
                $rest = stream_get_contents($handle);
                if ($rest === false) {
                    throw new \RuntimeException('Could not read checkpoint');
                }
                [$fingerprint, $count, $records, $checksums] = self::decode($first . $rest);
                $this->assertIdentity($fingerprint, $count);
                if (!is_writable(dirname($this->path))) {
                    throw new \RuntimeException('Checkpoint parent directory is not writable');
                }
                $this->legacy = true;
                $this->records = $records;
                $this->checksums = $checksums;
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /** @param resource $handle */
    private function loadJournal($handle, string $first): void
    {
        if (!function_exists('fsync')) {
            throw new \RuntimeException('Checkpoint journals require fsync');
        }
        $header = self::decodeLine($first, ['version', 'fingerprint', 'packetCount']);
        if (!is_string($header['fingerprint']) || !is_int($header['packetCount']) || $header['packetCount'] < 0) {
            throw new \RuntimeException('Malformed checkpoint');
        }
        $this->assertIdentity($header['fingerprint'], $header['packetCount']);
        $end = ftell($handle);
        if ($end === false || $end < 0) {
            throw new \RuntimeException('Could not inspect checkpoint position');
        }
        $partial = false;
        while (($line = fgets($handle)) !== false) {
            if (!str_ends_with($line, "\n")) {
                $partial = true;
                break;
            }
            $entry = self::decodeLine($line, ['index', 'record', 'checksum']);
            if (
                $entry['index'] !== count($this->records) || $entry['index'] >= $this->packetCount
                || !is_array($entry['record']) || !is_string($entry['checksum'])
            ) {
                throw new \RuntimeException('Malformed checkpoint');
            }
            /** @var array<string,mixed> $raw */
            $raw = $entry['record'];
            $record = self::normalizeRecord($raw);
            if (!hash_equals(self::checksum($record), $entry['checksum'])) {
                throw new \RuntimeException('Checkpoint checksum mismatch');
            }
            $this->records[] = $record;
            $end = ftell($handle);
            if ($end === false || $end < 0) {
                throw new \RuntimeException('Could not inspect checkpoint position');
            }
        }
        if (!feof($handle)) {
            throw new \RuntimeException('Could not read checkpoint');
        }
        if ($partial && (!ftruncate($handle, $end) || !fflush($handle) || !fsync($handle))) {
            throw new \RuntimeException('Could not recover checkpoint tail');
        }
        if (fseek($handle, $end) !== 0) {
            throw new \RuntimeException('Could not seek checkpoint');
        }
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    private static function decodeLine(string $line, array $keys): array
    {
        try {
            $value = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Malformed checkpoint', 0, $exception);
        }
        if (!str_ends_with($line, "\n") || !is_array($value)) {
            throw new \RuntimeException('Malformed checkpoint');
        }
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new \RuntimeException('Malformed checkpoint');
        }
        /** @var array<string,mixed> $value */
        return $value;
    }

    private function assertIdentity(string $fingerprint, int $count): void
    {
        if ($fingerprint !== $this->fingerprint || $count !== $this->packetCount) {
            throw new \RuntimeException('Checkpoint identity mismatch');
        }
    }

    /** @param array<string,mixed> $record */
    private function appendJournal(int $index, array $record, string $checksum): void
    {
        if (!is_resource($this->journal)) {
            throw new \RuntimeException('Checkpoint journal is closed; reopen it before appending');
        }
        $line = json_encode([
            'index' => $index, 'record' => $record, 'checksum' => $checksum,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        try {
            if (fwrite($this->journal, $line) !== strlen($line) || !fflush($this->journal) || !fsync($this->journal)) {
                throw new \RuntimeException('Could not write checkpoint journal');
            }
        } catch (\Throwable $exception) {
            fclose($this->journal);
            $this->journal = null;
            throw new \RuntimeException('Could not persist checkpoint', 0, $exception);
        }
    }

    /** @return array{string,int,list<array{text:string,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>}>,list<string>} */
    private static function decode(string $data): array
    {
        try {
            $value = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Malformed checkpoint', 0, $exception);
        }
        if (!is_array($value)) {
            throw new \RuntimeException('Malformed checkpoint');
        }
        $keys = array_keys($value);
        sort($keys);
        $expected = ['checksums', 'contiguousPrefixRecords', 'fingerprint', 'packetCount', 'version'];
        sort($expected);
        if (
            $keys !== $expected || $value['version'] !== self::LEGACY_VERSION || !is_string($value['fingerprint'])
            || !is_int($value['packetCount']) || $value['packetCount'] < 0
            || !is_array($value['contiguousPrefixRecords']) || !array_is_list($value['contiguousPrefixRecords'])
            || !is_array($value['checksums']) || !array_is_list($value['checksums'])
            || count($value['contiguousPrefixRecords']) !== count($value['checksums'])
            || count($value['contiguousPrefixRecords']) > $value['packetCount']
        ) {
            throw new \RuntimeException('Malformed checkpoint');
        }
        /** @var list<array{text:string,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>}> $records */
        $records = [];
        /** @var list<string> $checksums */
        $checksums = [];
        foreach ($value['contiguousPrefixRecords'] as $i => $raw) {
            if (!is_array($raw)) {
                throw new \RuntimeException('Malformed checkpoint');
            }
            /** @var array<string,mixed> $raw */
            $record = self::normalizeRecord($raw);
            $checksum = $value['checksums'][$i];
            if (
                !is_string($checksum) || !preg_match('/\A[0-9a-f]{64}\z/', $checksum)
                || !hash_equals($checksum, self::checksum($record))
            ) {
                throw new \RuntimeException('Checkpoint checksum mismatch');
            }
            $records[] = $record;
            $checksums[] = $checksum;
        }
        return [$value['fingerprint'], $value['packetCount'], $records, $checksums];
    }

    /**
     * @param array<string,mixed> $record
     * @return array{text:string,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>}
     */
    private static function normalizeRecord(array $record): array
    {
        $keys = array_keys($record);
        sort($keys);
        $expected = ['consumed', 'httpStatus', 'text'];
        sort($expected);
        if (
            $keys !== $expected || !is_string($record['text']) || !is_int($record['httpStatus'])
            || $record['httpStatus'] < 200 || $record['httpStatus'] > 299 || !is_array($record['consumed'])
        ) {
            throw new \InvalidArgumentException('Invalid checkpoint record');
        }
        $consumed = [];
        foreach ($record['consumed'] as $name => $usage) {
            if (!is_string($name) || $name === '' || !is_array($usage)) {
                throw new \InvalidArgumentException('Invalid checkpoint usage');
            }
            $usageKeys = array_keys($usage);
            sort($usageKeys);
            $allowed = ['breakdown', 'total'];
            sort($allowed);
            if (array_diff($usageKeys, $allowed) !== []) {
                throw new \InvalidArgumentException('Invalid checkpoint usage');
            }
            $normalized = [];
            if (array_key_exists('total', $usage)) {
                if (!is_int($usage['total']) || $usage['total'] < 0) {
                    throw new \InvalidArgumentException('Invalid checkpoint usage');
                }
                $normalized['total'] = $usage['total'];
            }
            if (array_key_exists('breakdown', $usage)) {
                if (!is_array($usage['breakdown'])) {
                    throw new \InvalidArgumentException('Invalid checkpoint usage');
                }
                $breakdown = [];
                foreach ($usage['breakdown'] as $key => $amount) {
                    if (!is_string($key) || !is_int($amount) || $amount < 0) {
                        throw new \InvalidArgumentException('Invalid checkpoint usage');
                    }
                    $breakdown[$key] = $amount;
                }
                ksort($breakdown, SORT_STRING);
                $normalized['breakdown'] = $breakdown;
            }
            $consumed[$name] = $normalized;
        }
        ksort($consumed, SORT_STRING);
        return [
            'text' => $record['text'],
            'httpStatus' => $record['httpStatus'],
            'consumed' => $consumed,
        ];
    }

    /** @param array<string,mixed> $record */
    private static function checksum(array $record): string
    {
        $encoded = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        return hash('sha256', $encoded);
    }

    private function persistLegacy(): void
    {
        $header = substr(json_encode([
            'version' => self::LEGACY_VERSION,
            'fingerprint' => $this->fingerprint,
            'packetCount' => $this->packetCount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 0, -1);
        $this->replaceFile($this->snapshotChunks($header));
    }

    /** @param iterable<string> $chunks */
    private function replaceFile(iterable $chunks): void
    {
        $temp = @tempnam(dirname($this->path), '.batch-checkpoint-');
        if ($temp === false) {
            throw new \RuntimeException('Could not create checkpoint temporary file');
        }
        $handle = null;
        try {
            if (realpath(dirname($temp)) !== realpath(dirname($this->path))) {
                throw new \RuntimeException('Checkpoint temporary file must share its parent directory');
            }
            if (!@chmod($temp, 0600)) {
                throw new \RuntimeException('Could not restrict checkpoint file permissions');
            }
            $handle = @fopen($temp, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Could not write checkpoint temporary file');
            }
            foreach ($chunks as $chunk) {
                if (fwrite($handle, $chunk) !== strlen($chunk)) {
                    throw new \RuntimeException('Could not write checkpoint temporary file');
                }
            }
            if (!fflush($handle)) {
                throw new \RuntimeException('Could not write checkpoint temporary file');
            }
            if (function_exists('fsync') && !@fsync($handle)) {
                throw new \RuntimeException('Could not sync checkpoint temporary file');
            }
            fclose($handle);
            $handle = null;
            if (!@rename($temp, $this->path)) {
                throw new \RuntimeException('Could not install checkpoint');
            }
            $temp = '';
            @chmod($this->path, 0600);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($temp !== '') {
                @unlink($temp);
            }
        }
    }

    /**
     * Encode bounded batches; one record can exceed the target buffer size.
     *
     * @return \Generator<int,string>
     */
    private function snapshotChunks(string $header): \Generator
    {
        $buffer = $header;
        foreach (['contiguousPrefixRecords' => $this->records, 'checksums' => $this->checksums] as $name => $values) {
            $buffer .= ',"' . $name . '":[';
            foreach ($values as $index => $value) {
                $buffer .= ($index === 0 ? '' : ',') . json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
                if (strlen($buffer) >= self::WRITE_BUFFER_BYTES) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            $buffer .= ']';
        }
        yield $buffer . "}\n";
    }

    private static function validatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $path)) {
            throw new \InvalidArgumentException('Checkpoint path must be a local filesystem path');
        }
    }

    /** @return resource */
    private static function openLock(string $path)
    {
        if (is_link($path)) {
            throw new \RuntimeException('Checkpoint lock must not be a symlink');
        }
        if (file_exists($path) && !is_file($path)) {
            throw new \RuntimeException('Checkpoint lock is not regular');
        }
        $handle = @fopen($path, file_exists($path) ? 'r+b' : 'x+b');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException('Checkpoint is locked');
        }
        @chmod($path, 0600);
        return $handle;
    }

    private static function assertRegular(string $path, string $label): void
    {
        if (is_link($path) || !is_file($path)) {
            throw new \RuntimeException('Checkpoint ' . $label . ' is not regular');
        }
    }
}
