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
use Sample\Controllers\AuthController;
use Sample\Controllers\BeatController;
use Sample\Controllers\CacheController;
use Sample\Controllers\DashboardController;
use Sample\Controllers\LogController;
use Sample\Controllers\StatusController;
use Sample\Controllers\StressController;
use Sample\Env;
use Sample\HistoryStore;
use Sample\MissingApiKey;
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

// HistoryStore is per-session: each browser cookie has its own ring buffer.
// PHP scripts run fresh per request, so we have to (de)serialize through
// $_SESSION — without this, every request would start with an empty store
// and the console panel would never display anything you just sent.
$history = ($_SESSION['history'] ?? null) instanceof HistoryStore
    ? $_SESSION['history']
    : new HistoryStore();

$view = new View($root . '/templates');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Helper to flush mutated state back to the session. Called before every
// echo / header()-only return path so PHP's session save handler (which runs
// at script shutdown) sees the latest history. Must NOT use a shutdown
// callback — those run AFTER the session writer in many PHP configurations.
$saveSession = static function () use (&$history): void {
    $_SESSION['history'] = $history;
};

// /auth and /auth/signout run BEFORE we try to construct the client — they
// don't need one (signing in is what gives us a key in the first place).
if ($path === '/auth' && $method === 'GET') {
    $body = (new AuthController($history, $view))->form($flash);
    $saveSession();
    echo $body;
    return;
}
if ($path === '/auth' && $method === 'POST') {
    $body = (new AuthController($history, $view))->submit($_POST);
    $saveSession();
    echo $body;
    return;
}
if ($path === '/auth/signout' && $method === 'POST') {
    $body = (new AuthController($history, $view))->signOut();
    $saveSession();
    echo $body;
    return;
}

try {
    $client = ClientFactory::make($history);
} catch (MissingApiKey) {
    // No key in session, no key in .env — send the user to the sign-in form
    // instead of erroring with a 500.
    $_SESSION['flash'] = ['type' => 'info', 'message' => 'Sign in with a LogDB API key to start sending.'];
    $saveSession();
    header('Location: /auth');
    return;
} catch (\Throwable $e) {
    http_response_code(500);
    $body = $view->render('error', [
        'title' => 'Configuration error',
        'active' => '',
        'history' => $history,
        'message' => $e->getMessage(),
    ]);
    $saveSession();
    echo $body;
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

$saveSession();
echo $response;
