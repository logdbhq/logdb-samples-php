<?php

declare(strict_types=1);

namespace Sample\Controllers;

use Sample\ClientFactory;
use Sample\HistoryStore;
use Sample\View;

/**
 * Handles per-session authentication. Three actions:
 *   - GET  /auth          → form
 *   - POST /auth          → save key (+ optional endpoint) into $_SESSION
 *   - POST /auth/signout  → clear the session
 *
 * No `LogDBClient` is constructed here — auth runs BEFORE the client is built
 * (the front controller may have skipped client construction with a
 * `MissingApiKey` exception). We only touch the session.
 */
final class AuthController
{
    public function __construct(
        private readonly HistoryStore $history,
        private readonly View $view,
    ) {
    }

    public function form(?array $flash = null): string
    {
        return $this->view->render('auth', [
            'title' => 'Sign in — LogDB PHP Sample',
            'active' => '',
            'flash' => $flash,
            'history' => $this->history,
            'currentSource' => ClientFactory::keySource(),
            'currentMasked' => ClientFactory::maskedActiveKey(),
            'currentEndpoint' => ClientFactory::effectiveEndpoint(),
        ]);
    }

    /** @param array<string, mixed> $post */
    public function submit(array $post): string
    {
        $apiKey = trim((string) ($post['api_key'] ?? ''));
        $endpoint = trim((string) ($post['endpoint'] ?? ''));

        if ($apiKey === '') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'API key is required.'];
            return $this->redirect('/auth');
        }

        if ($endpoint !== '' && !preg_match('#^https?://#i', $endpoint)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Endpoint must be an http(s) URL.'];
            return $this->redirect('/auth');
        }

        ClientFactory::signIn($apiKey, $endpoint === '' ? null : $endpoint);

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Signed in. Sample now uses key ' . ClientFactory::maskKey($apiKey) . '.',
        ];
        return $this->redirect('/');
    }

    public function signOut(): string
    {
        ClientFactory::signOut();
        $_SESSION['flash'] = ['type' => 'info', 'message' => 'Signed out.'];
        return $this->redirect('/auth');
    }

    private function redirect(string $path): string
    {
        header("Location: {$path}");
        return '';
    }
}
