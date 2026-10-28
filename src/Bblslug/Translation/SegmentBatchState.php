<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Models\UsageExtractor;
use Bblslug\OperationDeadline;

/** @internal Validated segment results and checkpoint progress for one translation. */
final class SegmentBatchState
{
    /** @var array<string,string> */
    private array $translations = [];
    /** @var list<array{index:int,segments:int,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>,reused:bool}> */
    private array $summaries = [];
    /** @var list<array<string,array{total?:int,breakdown?:array<string,int>}>> */
    private array $usages = [];
    /** @var list<array<string,array{total?:int,breakdown?:array<string,int>}>> */
    private array $freshUsages = [];
    private int $reusedBatches = 0;

    /** @param array<string,mixed> $model */
    public function __construct(
        private readonly array $model,
        private readonly string $label,
        private readonly bool $allowLineBreaks,
        private readonly ?BatchCheckpoint $checkpoint,
        private readonly ?OperationDeadline $deadline
    ) {
    }

    /**
     * Validate the entire saved prefix before any new provider request.
     *
     * @param list<string> $payloads
     */
    public function validateRetained(array $payloads): void
    {
        if ($this->checkpoint === null) {
            return;
        }
        foreach ($payloads as $index => $payload) {
            $this->deadline?->check();
            $saved = $this->checkpoint->get($index);
            if ($saved === null) {
                break;
            }
            $packet = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
            if (!$packet instanceof \stdClass) {
                throw new \LogicException('Checkpoint packet must be an object');
            }
            $ids = array_keys(get_object_vars($packet));
            SegmentTranslations::decode($saved['text'], $ids, $this->label, $this->allowLineBreaks);
        }
    }

    /**
     * Persist a fresh protocol-valid packet before reporting completion.
     *
     * @param array{text:string,usage:array<string,mixed>|null,httpStatus:int} $provider
     */
    public function acceptFresh(int $index, string $payload, array $provider): void
    {
        $segments = $this->addTranslations($payload, $provider['text']);
        $usage = UsageExtractor::extract($this->model, $provider['usage']);
        $this->freshUsages[] = $usage;
        $this->checkpoint?->append($index, [
            'text' => $provider['text'], 'httpStatus' => $provider['httpStatus'], 'consumed' => $usage,
        ]);
        $this->recordSummary($index, $segments, $provider['httpStatus'], $usage, false);
    }

    /**
     * Reuse the text, status and accounted usage from the same saved packet.
     *
     * @param array{text:string,httpStatus:int,
     *     consumed:array<string,array{total?:int,breakdown?:array<string,int>}>} $saved
     */
    public function acceptRetained(int $index, string $payload, array $saved): void
    {
        $segments = $this->addTranslations($payload, $saved['text']);
        ++$this->reusedBatches;
        $this->recordSummary($index, $segments, $saved['httpStatus'], $saved['consumed'], true);
    }

    private function addTranslations(string $payload, string $text): int
    {
        $packet = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        if (!$packet instanceof \stdClass) {
            throw new \LogicException($this->label . ' segment packet must be an object');
        }
        $expected = array_keys(get_object_vars($packet));
        $this->translations += SegmentTranslations::decode($text, $expected, $this->label, $this->allowLineBreaks);
        return count($expected);
    }

    /** @param array<string,array{total?:int,breakdown?:array<string,int>}> $usage */
    private function recordSummary(int $index, int $segments, int $httpStatus, array $usage, bool $reused): void
    {
        $this->usages[] = $usage;
        $this->summaries[] = [
            'index' => $index + 1, 'segments' => $segments,
            'httpStatus' => $httpStatus, 'consumed' => $usage, 'reused' => $reused,
        ];
    }

    /**
     * @return array{text:string,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>,
     *     batches:list<array{index:int,segments:int,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>,reused?:bool}>,
     *     reusedBatches:int,consumedThisRun:array<string,array{total?:int,breakdown?:array<string,int>}>}
     */
    public function result(bool $reportReuse): array
    {
        return [
            'text' => json_encode((object) $this->translations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ] + $this->summary($reportReuse);
    }

    /** @return array<string,string> */
    public function translations(): array
    {
        return $this->translations;
    }

    /**
     * @return array{consumed:array<string,array{total?:int,breakdown?:array<string,int>}>,
     *     batches:list<array{index:int,segments:int,httpStatus:int,consumed:array<string,array{total?:int,breakdown?:array<string,int>}>,reused?:bool}>,
     *     reusedBatches:int,consumedThisRun:array<string,array{total?:int,breakdown?:array<string,int>}>}
     */
    public function summary(bool $reportReuse): array
    {
        $summaries = $this->summaries;
        if (!$reportReuse) {
            foreach ($summaries as &$summary) {
                unset($summary['reused']);
            }
            unset($summary);
        }
        return [
            'consumed' => UsageExtractor::sum($this->usages),
            'batches' => $summaries,
            'reusedBatches' => $this->reusedBatches,
            'consumedThisRun' => UsageExtractor::sum($this->freshUsages),
        ];
    }
}
