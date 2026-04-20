<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\LogDBClient;
use Sample\ClientFactory;
use Sample\HistoryStore;
use Sample\View;

final class StatusController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function index(): string
    {
        return $this->view->render('status', [
            'title' => 'Status — LogDB PHP Sample',
            'active' => 'status',
            'flash' => null,
            'history' => $this->history,
            'endpoint' => ClientFactory::endpoint(),
            'application' => ClientFactory::application(),
            'environment' => ClientFactory::environment(),
            'totalCount' => $this->history->totalCount(),
            'successCount' => $this->history->successCount(),
            'failureCount' => $this->history->failureCount(),
            'recentErrors' => $this->history->recentErrors(10),
            'phpVersion' => PHP_VERSION,
            'sdkVersion' => self::detectSdkVersion(),
        ]);
    }

    private static function detectSdkVersion(): string
    {
        $installed = dirname(__DIR__, 2) . '/vendor/composer/installed.json';
        if (!is_file($installed)) {
            return 'unknown';
        }
        $json = json_decode((string) file_get_contents($installed), true);
        $packages = $json['packages'] ?? $json ?? [];
        foreach ($packages as $pkg) {
            if (($pkg['name'] ?? '') === 'logdbhq/logdb-php') {
                return (string) ($pkg['version'] ?? 'unknown');
            }
        }
        return 'unknown';
    }
}
