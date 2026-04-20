<?php

declare(strict_types=1);

namespace Sample;

/**
 * In-process ring buffer of recent send results. Powers the dashboard table,
 * the bottom console panel, and the status screen.
 *
 * Single-process semantics: under `php -S` this works as expected. Under
 * PHP-FPM each worker has its own buffer — intentional for a local-play
 * sample.
 */
final class HistoryStore
{
    public const TYPE_LOG = 'log';
    public const TYPE_BEAT = 'beat';
    public const TYPE_CACHE = 'cache';
    public const TYPE_STRESS = 'stress';

    /** @var list<array{ts: int, type: string, status: string, summary: string, error: ?string}> */
    private array $items = [];

    public function __construct(public readonly int $capacity = 50)
    {
    }

    public function record(string $type, string $status, string $summary, ?string $error = null): void
    {
        $this->items[] = [
            'ts' => (int) (microtime(true) * 1000),
            'type' => $type,
            'status' => $status,
            'summary' => $summary,
            'error' => $error,
        ];
        if (count($this->items) > $this->capacity) {
            array_shift($this->items);
        }
    }

    /** @return list<array{ts: int, type: string, status: string, summary: string, error: ?string}> */
    public function recent(int $limit = 20): array
    {
        $out = $this->items;
        usort($out, static fn ($a, $b) => $b['ts'] <=> $a['ts']);
        return array_slice($out, 0, $limit);
    }

    public function totalCount(): int
    {
        return count($this->items);
    }

    public function successCount(): int
    {
        return count(array_filter($this->items, static fn ($i) => $i['status'] === 'Success'));
    }

    public function failureCount(): int
    {
        return count(array_filter($this->items, static fn ($i) => $i['status'] !== 'Success'));
    }

    /** @return list<array{ts: int, type: string, status: string, summary: string, error: ?string}> */
    public function recentErrors(int $limit = 10): array
    {
        $errs = array_filter($this->items, static fn ($i) => $i['error'] !== null);
        usort($errs, static fn ($a, $b) => $b['ts'] <=> $a['ts']);
        return array_slice(array_values($errs), 0, $limit);
    }
}
