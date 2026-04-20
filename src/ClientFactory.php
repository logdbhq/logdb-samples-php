<?php

declare(strict_types=1);

namespace Sample;

use LogDB\LogDBClient;
use LogDB\Options\LogDBClientOptions;

/**
 * Builds a single `LogDBClient` for the request.
 *
 * Wires `onError` into the shared `HistoryStore` so the Status screen can
 * surface SDK failures. Reads everything from `.env` via the `Env` helper.
 */
final class ClientFactory
{
    private static ?LogDBClient $client = null;

    public static function make(HistoryStore $history): LogDBClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $endpoint = Env::get('LOGDB_ENDPOINT', 'https://otlp.logdb.site') ?? 'https://otlp.logdb.site';
        $apiKey = Env::get('LOGDB_API_KEY');
        $application = Env::get('LOGDB_APPLICATION', 'logdb-php-sample');
        $environment = Env::get('LOGDB_ENVIRONMENT', 'development');

        if ($apiKey === null || $apiKey === '' || $apiKey === 'your-api-key-here') {
            throw new \RuntimeException(
                'LOGDB_API_KEY is not set. Copy .env.example to .env and fill in your LogDB API key.',
            );
        }

        self::$client = new LogDBClient(new LogDBClientOptions(
            endpoint: $endpoint,
            apiKey: $apiKey,
            defaultApplication: $application,
            defaultEnvironment: $environment,
            // Default-ish: batching on, shutdown handler drains at PHP-FPM request end.
            // The sample's web requests are short-lived enqueue → return → flush at shutdown.
            enableBatching: true,
            batchSize: 50,
            flushInterval: 2_000,
            onError: static function (\Throwable $err) use ($history): void {
                $history->record(
                    type: 'sdk-error',
                    status: 'Failed',
                    summary: $err::class,
                    error: $err->getMessage(),
                );
            },
        ));

        return self::$client;
    }

    /** Sample doesn't authenticate — expose the configured endpoint for the Status screen. */
    public static function endpoint(): string
    {
        return Env::get('LOGDB_ENDPOINT', 'https://otlp.logdb.site') ?? 'https://otlp.logdb.site';
    }

    public static function application(): string
    {
        return Env::get('LOGDB_APPLICATION', 'logdb-php-sample') ?? 'logdb-php-sample';
    }

    public static function environment(): string
    {
        return Env::get('LOGDB_ENVIRONMENT', 'development') ?? 'development';
    }
}
