<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\Errors\LogDBError;
use LogDB\Models\Reader\LogBeatQueryParams;
use Sample\HistoryStore;
use Sample\ReaderFactory;
use Sample\View;

final class BrowseBeatsController
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
        $params = new LogBeatQueryParams(
            measurement: self::nullIfEmpty($query['measurement'] ?? null),
            collection: self::nullIfEmpty($query['collection'] ?? null),
            skip: ($page - 1) * self::PAGE_SIZE,
            take: self::PAGE_SIZE,
        );

        try {
            $reader = ReaderFactory::make();
            $result = $reader->getLogBeats($params);
            $reader->dispose();
            $error = null;
        } catch (LogDBError $e) {
            $result = null;
            $error = $e->getMessage();
        }

        return $this->view->render('browse-beats', [
            'title' => 'Browse heartbeats — LogDB PHP Sample',
            'active' => 'browse-beats',
            'flash' => $flash,
            'history' => $this->history,
            'page' => $result,
            'currentPage' => $page,
            'pageSize' => self::PAGE_SIZE,
            'totalPages' => $result !== null && $result->pageSize > 0
                ? max(1, (int) ceil($result->totalCount / max(1, $result->pageSize)))
                : 1,
            'filters' => [
                'measurement' => $query['measurement'] ?? '',
                'collection' => $query['collection'] ?? '',
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
}
