<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\Builders\LogBeatBuilder;
use LogDB\LogDBClient;
use LogDB\Models\LogResponseStatus;
use Sample\HistoryStore;
use Sample\View;

final class BeatController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function form(?array $flash = null): string
    {
        return $this->view->render('beat', [
            'title' => 'Send a heartbeat — LogDB PHP Sample',
            'active' => 'beat',
            'flash' => $flash,
            'history' => $this->history,
        ]);
    }

    /** @param array<string, mixed> $post */
    public function submit(array $post): string
    {
        $measurement = trim((string) ($post['measurement'] ?? ''));
        if ($measurement === '') {
            return $this->redirect('/beat', ['type' => 'error', 'message' => 'Measurement name is required.']);
        }

        $builder = LogBeatBuilder::create($this->client)->setMeasurement($measurement);

        $tagKeys = (array) ($post['tag_keys'] ?? []);
        $tagValues = (array) ($post['tag_values'] ?? []);
        for ($i = 0; $i < max(count($tagKeys), count($tagValues)); $i++) {
            $k = trim((string) ($tagKeys[$i] ?? ''));
            $v = trim((string) ($tagValues[$i] ?? ''));
            if ($k !== '' && $v !== '') {
                $builder = $builder->addTag($k, $v);
            }
        }

        $fieldKeys = (array) ($post['field_keys'] ?? []);
        $fieldValues = (array) ($post['field_values'] ?? []);
        $count = 0;
        for ($i = 0; $i < max(count($fieldKeys), count($fieldValues)); $i++) {
            $k = trim((string) ($fieldKeys[$i] ?? ''));
            $v = trim((string) ($fieldValues[$i] ?? ''));
            if ($k === '') {
                continue;
            }
            $builder = is_numeric($v)
                ? $builder->addField($k, str_contains($v, '.') ? (float) $v : (int) $v)
                : $builder->addField($k, $v);
            $count++;
        }

        $status = $builder->log();
        $this->client->flush();

        error_log("[sample] /beat POST -> status={$status->value} measurement=\"{$measurement}\" fields={$count}");

        $this->history->record(
            type: HistoryStore::TYPE_BEAT,
            status: $status->value,
            summary: "{$measurement} ({$count} field" . ($count === 1 ? '' : 's') . ')',
            error: $status === LogResponseStatus::Success ? null : "status={$status->value}",
        );

        $flash = $status === LogResponseStatus::Success
            ? ['type' => 'success', 'message' => "Sent heartbeat \"{$measurement}\""]
            : ['type' => 'error', 'message' => "Send failed: {$status->value}"];

        return $this->redirect('/beat', $flash);
    }

    private function redirect(string $path, array $flash): string
    {
        $_SESSION['flash'] = $flash;
        header("Location: {$path}");
        return '';
    }
}
