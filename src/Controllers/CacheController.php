<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\Builders\LogCacheBuilder;
use LogDB\LogDBClient;
use LogDB\Models\LogResponseStatus;
use Sample\HistoryStore;
use Sample\View;

final class CacheController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function form(?array $flash = null): string
    {
        return $this->view->render('cache', [
            'title' => 'Write to cache — LogDB PHP Sample',
            'active' => 'cache',
            'flash' => $flash,
            'history' => $this->history,
        ]);
    }

    /** @param array<string, mixed> $post */
    public function submit(array $post): string
    {
        $key = trim((string) ($post['key'] ?? ''));
        $valueRaw = (string) ($post['value'] ?? '');
        $valueIsJson = isset($post['value_is_json']);
        $ttl = (string) ($post['ttl_seconds'] ?? '');

        if ($key === '') {
            return $this->redirect('/cache', ['type' => 'error', 'message' => 'Cache key is required.']);
        }
        if ($valueRaw === '') {
            return $this->redirect('/cache', ['type' => 'error', 'message' => 'Cache value is required.']);
        }

        $builder = LogCacheBuilder::create($this->client)->setKey($key);

        if ($valueIsJson) {
            $decoded = json_decode($valueRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->redirect('/cache', [
                    'type' => 'error',
                    'message' => 'Invalid JSON: ' . json_last_error_msg(),
                ]);
            }
            $builder = $builder->setValue($decoded);
        } else {
            $builder = $builder->setValue($valueRaw);
        }

        if ($ttl !== '' && ctype_digit($ttl)) {
            $builder = $builder->setTtlSeconds((int) $ttl);
        }

        $status = $builder->log();
        $this->client->flush();

        error_log("[sample] /cache POST -> status={$status->value} key=\"{$key}\" json=" . ($valueIsJson ? 'yes' : 'no'));

        $this->history->record(
            type: HistoryStore::TYPE_CACHE,
            status: $status->value,
            summary: "{$key} = " . self::truncate($valueRaw, 40),
            error: $status === LogResponseStatus::Success ? null : "status={$status->value}",
        );

        $flash = $status === LogResponseStatus::Success
            ? ['type' => 'success', 'message' => "Cached \"{$key}\""]
            : ['type' => 'error', 'message' => "Send failed: {$status->value}"];

        return $this->redirect('/cache', $flash);
    }

    private function redirect(string $path, array $flash): string
    {
        $_SESSION['flash'] = $flash;
        header("Location: {$path}");
        return '';
    }

    private static function truncate(string $s, int $max): string
    {
        return strlen($s) <= $max ? $s : substr($s, 0, $max - 1) . '…';
    }
}
