<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\Builders\LogEventBuilder;
use LogDB\LogDBClient;
use LogDB\Models\LogLevel;
use LogDB\Models\LogResponseStatus;
use Sample\HistoryStore;
use Sample\View;

final class LogController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function form(?array $flash = null): string
    {
        return $this->view->render('log', [
            'title' => 'Send a log — LogDB PHP Sample',
            'active' => 'log',
            'flash' => $flash,
            'history' => $this->history,
            'levels' => self::levelOptions(),
        ]);
    }

    /** @param array<string, mixed> $post */
    public function submit(array $post): string
    {
        $message = trim((string) ($post['message'] ?? ''));
        if ($message === '') {
            return $this->redirect('/log', ['type' => 'error', 'message' => 'Message is required.']);
        }

        $levelName = (string) ($post['level'] ?? 'Info');
        $level = self::parseLevel($levelName);

        $builder = LogEventBuilder::create($this->client)
            ->setMessage($message)
            ->setLogLevel($level);

        if (!empty($post['user_email'])) {
            $builder = $builder->setUserEmail((string) $post['user_email']);
        }
        if (!empty($post['correlation_id'])) {
            $builder = $builder->setCorrelationId((string) $post['correlation_id']);
        }
        if (!empty($post['request_path'])) {
            $builder = $builder->setRequestPath((string) $post['request_path']);
        }
        if (!empty($post['http_method'])) {
            $builder = $builder->setHttpMethod((string) $post['http_method']);
        }
        if (!empty($post['status_code']) && ctype_digit((string) $post['status_code'])) {
            $builder = $builder->setStatusCode((int) $post['status_code']);
        }
        if (!empty($post['ip_address'])) {
            $builder = $builder->setIpAddress((string) $post['ip_address']);
        }
        foreach ((array) ($post['labels'] ?? []) as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $builder = $builder->addLabel($label);
            }
        }
        foreach (self::pairs($post['attributes_keys'] ?? [], $post['attributes_values'] ?? []) as [$k, $v]) {
            $builder = $builder->addAttribute($k, self::coerceAttributeValue($v));
        }

        $status = $builder->log();

        // Force a flush so the round-trip completes within this request and we
        // can record a definitive Success/Failed status in the history.
        $this->client->flush();

        error_log("[sample] /log POST -> status={$status->value} level={$level->toString()} msg=\"{$message}\"");

        $this->history->record(
            type: HistoryStore::TYPE_LOG,
            status: $status->value,
            summary: self::truncate("{$level->toString()}: {$message}", 80),
            error: $status === LogResponseStatus::Success ? null : "status={$status->value}",
        );

        $flash = $status === LogResponseStatus::Success
            ? ['type' => 'success', 'message' => "Sent: \"{$message}\" (level {$level->toString()})"]
            : ['type' => 'error', 'message' => "Send failed: {$status->value}"];

        return $this->redirect('/log', $flash);
    }

    private function redirect(string $path, array $flash): string
    {
        $_SESSION['flash'] = $flash;
        header("Location: {$path}");
        return '';
    }

    /** @return list<array{value: string, label: string}> */
    private static function levelOptions(): array
    {
        $out = [];
        foreach (LogLevel::cases() as $case) {
            $out[] = ['value' => $case->name, 'label' => $case->toString()];
        }
        return $out;
    }

    private static function parseLevel(string $name): LogLevel
    {
        foreach (LogLevel::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }
        return LogLevel::Info;
    }

    /**
     * @param string|int|float|bool $value
     * @return string|int|float|bool
     */
    private static function coerceAttributeValue(mixed $value): string|int|float|bool
    {
        $s = (string) $value;
        if ($s === 'true' || $s === 'false') {
            return $s === 'true';
        }
        if (is_numeric($s)) {
            return str_contains($s, '.') ? (float) $s : (int) $s;
        }
        return $s;
    }

    /**
     * @param array<int, string> $keys
     * @param array<int, string> $values
     * @return list<array{0: string, 1: string}>
     */
    private static function pairs(array $keys, array $values): array
    {
        $out = [];
        $count = max(count($keys), count($values));
        for ($i = 0; $i < $count; $i++) {
            $k = trim((string) ($keys[$i] ?? ''));
            $v = (string) ($values[$i] ?? '');
            if ($k === '') {
                continue;
            }
            $out[] = [$k, $v];
        }
        return $out;
    }

    private static function truncate(string $s, int $max): string
    {
        return strlen($s) <= $max ? $s : substr($s, 0, $max - 1) . '…';
    }
}
