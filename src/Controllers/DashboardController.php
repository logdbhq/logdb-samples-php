<?php

declare(strict_types=1);

namespace Sample\Controllers;

use LogDB\LogDBClient;
use Sample\ClientFactory;
use Sample\HistoryStore;
use Sample\View;

final class DashboardController
{
    public function __construct(
        private readonly LogDBClient $client,
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function index(?array $flash = null): string
    {
        return $this->view->render('dashboard', [
            'title' => 'Dashboard — LogDB PHP Sample',
            'active' => 'dashboard',
            'flash' => $flash,
            'history' => $this->history,
            'recent' => $this->history->recent(10),
            'totalCount' => $this->history->totalCount(),
            'successCount' => $this->history->successCount(),
            'failureCount' => $this->history->failureCount(),
            'endpoint' => ClientFactory::endpoint(),
            'application' => ClientFactory::application(),
            'environment' => ClientFactory::environment(),
        ]);
    }
}
