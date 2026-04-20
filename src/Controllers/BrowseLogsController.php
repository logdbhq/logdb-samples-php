<?php

declare(strict_types=1);

namespace Sample\Controllers;

use DateTimeImmutable;
use LogDB\Errors\LogDBError;
use LogDB\Models\LogLevel;
use LogDB\Models\Reader\LogQueryParams;
use Sample\HistoryStore;
use Sample\ReaderFactory;
use Sample\View;

/**
 * Browse logs already stored in LogDB. Mirrors the .NET TUI's "Logs" tab
 * and the com.logdb.web.ui Logs screen — paginated table with filters.
 *
 * Reads from the LogDB REST API via the package's `LogDBReader`. Filters
 * map to `LogQueryParams`; pagination is offset-based via `?page=N`.
 */
final class BrowseLogsController
{
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    /** @param array<string, string|null> $query */
    public function index(array $query, ?array $flash = null): string
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $params = new LogQueryParams(
            application: self::nullIfEmpty($query['application'] ?? null),
            environment: self::nullIfEmpty($query['environment'] ?? null),
            level: self::parseLevel($query['level'] ?? null),
            collection: self::nullIfEmpty($query['collection'] ?? null),
            searchString: self::nullIfEmpty($query['search'] ?? null),
            fromDate: self::parseDate($query['from'] ?? null),
            toDate: self::parseDate($query['to'] ?? null),
            skip: ($page - 1) * self::PAGE_SIZE,
            take: self::PAGE_SIZE,
        );

        try {
            $reader = ReaderFactory::make();
            $result = $reader->getLogs($params);
            $collections = $reader->getCollections();
            $reader->dispose();
            $error = null;
        } catch (LogDBError $e) {
            $result = null;
            $collections = [];
            $error = $e->getMessage();
        }

        return $this->view->render('browse-logs', [
            'title' => 'Browse logs — LogDB PHP Sample',
            'active' => 'browse-logs',
            'flash' => $flash,
            'history' => $this->history,
            'page' => $result,
            'collections' => $collections,
            'currentPage' => $page,
            'pageSize' => self::PAGE_SIZE,
            'totalPages' => $result !== null && $result->pageSize > 0
                ? max(1, (int) ceil($result->totalCount / max(1, $result->pageSize)))
                : 1,
            'filters' => [
                'application' => $query['application'] ?? '',
                'environment' => $query['environment'] ?? '',
                'level' => $query['level'] ?? '',
                'collection' => $query['collection'] ?? '',
                'search' => $query['search'] ?? '',
                'from' => $query['from'] ?? '',
                'to' => $query['to'] ?? '',
            ],
            'readerEndpoint' => ReaderFactory::endpoint(),
            'error' => $error,
        ]);
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        $t = trim((string) $v);
        return $t === '' ? null : $t;
    }

    private static function parseLevel(?string $v): ?LogLevel
    {
        $v = self::nullIfEmpty($v);
        if ($v === null) {
            return null;
        }
        foreach (LogLevel::cases() as $case) {
            if (strcasecmp($case->name, $v) === 0) {
                return $case;
            }
        }
        return null;
    }

    private static function parseDate(?string $v): ?DateTimeImmutable
    {
        $v = self::nullIfEmpty($v);
        if ($v === null) {
            return null;
        }
        try {
            return new DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
