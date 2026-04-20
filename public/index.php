<?php

declare(strict_types=1);

/**
 * Front controller — dispatches every request through this single file.
 *
 * Routing is a plain `match` on (method, path). No router library — the goal
 * of the sample is to show off `logdbhq/logdb-php`, not framework plumbing.
 *
 * Run locally with PHP's built-in dev server:
 *   php -S localhost:8080 -t public
 */

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

use Sample\ClientFactory;
use Sample\Controllers\BeatController;
use Sample\Controllers\CacheController;
use Sample\Controllers\DashboardController;
use Sample\Controllers\LogController;
use Sample\Controllers\StatusController;
use Sample\Controllers\StressController;
use Sample\Env;
use Sample\HistoryStore;
use Sample\View;

Env::load($root . '/.env');

// Static assets: when running via php -S, the built-in router would normally
// serve these directly because the URL maps to a real file. But just in case
// (or under PHP-FPM), return the file with the right mime type.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // let the dev server serve it
}

session_start();

// Per-process singletons — safe under `php -S`. Each PHP-FPM worker would
// rebuild these on first request, which is also what we want.
static $history = null;
static $view = null;

if ($history === null) {
    $history = new HistoryStore();
    $view = new View($root . '/templates');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

try {
    $client = ClientFactory::make($history);
} catch (\Throwable $e) {
    http_response_code(500);
    echo $view->render('error', [
        'title' => 'Configuration error',
        'active' => '',
        'history' => $history,
        'message' => $e->getMessage(),
    ]);
    return;
}

$response = match (true) {
    $path === '/' => (new DashboardController($client, $history, $view))->index($flash),

    $path === '/log' && $method === 'GET' => (new LogController($client, $history, $view))->form($flash),
    $path === '/log' && $method === 'POST' => (new LogController($client, $history, $view))->submit($_POST),

    $path === '/beat' && $method === 'GET' => (new BeatController($client, $history, $view))->form($flash),
    $path === '/beat' && $method === 'POST' => (new BeatController($client, $history, $view))->submit($_POST),

    $path === '/cache' && $method === 'GET' => (new CacheController($client, $history, $view))->form($flash),
    $path === '/cache' && $method === 'POST' => (new CacheController($client, $history, $view))->submit($_POST),

    $path === '/stress' && $method === 'GET' => (new StressController($client, $history, $view))->form($flash),
    $path === '/stress' && $method === 'POST' => (new StressController($client, $history, $view))->submit($_POST),

    $path === '/status' => (new StatusController($client, $history, $view))->index(),

    default => (function () use ($view, $history) {
        http_response_code(404);
        return $view->render('error', [
            'title' => 'Not found',
            'active' => '',
            'history' => $history,
            'message' => 'No route matches this URL.',
        ]);
    })(),
};

echo $response;
