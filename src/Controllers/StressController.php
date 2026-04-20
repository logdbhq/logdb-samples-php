<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\Builders\LogEventBuilder;
use LogDB\LogDBClient;
use LogDB\Models\LogLevel;
use LogDB\Models\LogResponseStatus;
use Sample\HistoryStore;
use Sample\View;

final class StressController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function form(?array $flash = null, ?array $result = null): string
    {
        return $this->view->render('stress', [
            'title' => 'Stress test — LogDB PHP Sample',
            'active' => 'stress',
            'flash' => $flash,
            'history' => $this->history,
            'result' => $result,
        ]);
    }

    /** @param array<string, mixed> $post */
    public function submit(array $post): string
    {
        $count = (int) ($post['count'] ?? 100);
        $count = max(1, min(5_000, $count)); // hard upper bound to keep the sample sane

        $batchSize = (int) ($post['batch_size'] ?? 50);
        $batchSize = max(1, min($count, $batchSize));

        $levelMix = (string) ($post['level_mix'] ?? 'mostly-info');
        $tag = bin2hex(random_bytes(3));

        $startedAt = microtime(true);
        $allOk = true;
        $statuses = ['Success' => 0, 'Failed' => 0, 'NotAuthorized' => 0, 'CircuitOpen' => 0, 'Timeout' => 0];

        $sent = 0;
        while ($sent < $count) {
            $thisBatch = min($batchSize, $count - $sent);
            for ($i = 0; $i < $thisBatch; $i++) {
                $level = self::pickLevel($levelMix);
                $status = LogEventBuilder::create($this->client)
                    ->setMessage("stress probe={$tag} seq=" . ($sent + $i))
                    ->setLogLevel($level)
                    ->addLabel('stress')
                    ->addAttribute('probe', $tag)
                    ->addAttribute('seq', $sent + $i)
                    ->log();
                $statuses[$status->value]++;
                if ($status !== LogResponseStatus::Success) {
                    $allOk = false;
                }
            }
            $sent += $thisBatch;
            $this->client->flush(); // force a flush per batch so timings are meaningful
        }

        $elapsed = microtime(true) - $startedAt;
        $perSec = $elapsed > 0 ? $count / $elapsed : 0;

        $this->history->record(
            type: HistoryStore::TYPE_STRESS,
            status: $allOk ? 'Success' : 'Failed',
            summary: "{$count} entries in " . number_format($elapsed * 1000, 0) . ' ms (' . number_format($perSec, 0) . ' /sec)',
            error: $allOk ? null : ('non-success: ' . implode(',', array_keys(array_filter($statuses, fn ($v, $k) => $v > 0 && $k !== 'Success', ARRAY_FILTER_USE_BOTH)))),
        );

        $result = [
            'count' => $count,
            'batchSize' => $batchSize,
            'elapsedMs' => $elapsed * 1000,
            'perSec' => $perSec,
            'statuses' => $statuses,
            'tag' => $tag,
        ];

        return $this->form(
            flash: $allOk
                ? ['type' => 'success', 'message' => "Sent {$count} entries cleanly. Probe tag: {$tag}"]
                : ['type' => 'error', 'message' => "Sent {$count} entries — at least one non-success."],
            result: $result,
        );
    }

    private static function pickLevel(string $mix): LogLevel
    {
        $r = mt_rand(0, 99);
        return match ($mix) {
            'all-info' => LogLevel::Info,
            'all-error' => LogLevel::Error,
            'mostly-error' => $r < 70 ? LogLevel::Error : ($r < 90 ? LogLevel::Warning : LogLevel::Info),
            default => $r < 70 ? LogLevel::Info : ($r < 90 ? LogLevel::Warning : LogLevel::Error), // mostly-info
        };
    }
}
