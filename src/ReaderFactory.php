<?php

declare(strict_types=1);

namespace Sample;

use LogDB\Reader\LogDBReader;
use LogDB\Reader\LogDBReaderOptions;

/**
 * Builds a `LogDBReader` for the current request.
 *
 * Auth resolution mirrors `ClientFactory` exactly — session key wins, .env
 * is the fallback, throws `MissingApiKey` if neither is set. The reader
 * uses a SEPARATE endpoint variable (`LOGDB_READER_ENDPOINT`) because the
 * REST API and the OTLP collector live on different paths
 * (`/rest-api/*` vs `/otlp/*`).
 *
 * Rebuilt per request like ClientFactory — constructor is cheap, no I/O.
 */
final class ReaderFactory
{
    public static function make(): LogDBReader
    {
        $apiKey = ClientFactory::effectiveApiKey();
        if ($apiKey === null) {
            throw new MissingApiKey('No LogDB API key found in session or .env. Sign in at /auth.');
        }

        return new LogDBReader(new LogDBReaderOptions(
            endpoint: self::endpoint(),
            apiKey: $apiKey,
            requestTimeout: 15_000,
            maxRetries: 2,
        ));
    }

    public static function endpoint(): string
    {
        return Env::get('LOGDB_READER_ENDPOINT', 'https://test-01.logdb.site/rest-api')
            ?? 'https://test-01.logdb.site/rest-api';
    }
}
