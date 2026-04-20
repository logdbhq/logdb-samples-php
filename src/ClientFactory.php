<?php

declare(strict_types=1);

namespace Sample;

use LogDB\LogDBClient;
use LogDB\Options\LogDBClientOptions;

/**
 * Builds a `LogDBClient` for the current request, with **per-session**
 * authentication.
 *
 * Resolution order for both `apiKey` and `endpoint`:
 *   1. `$_SESSION['logdb_api_key']` / `$_SESSION['logdb_endpoint']` — set by the /auth screen
 *   2. `.env` (`LOGDB_API_KEY` / `LOGDB_ENDPOINT`)
 *   3. (endpoint only) the hard-coded default `https://otlp.logdb.site`
 *
 * Sessions are per-browser-cookie. Two browsers hitting the same dev server
 * can use two different LogDB API keys side-by-side, and signing out of one
 * doesn't affect the other.
 *
 * The client is rebuilt per request — the constructor is cheap (no I/O), and
 * we want a different client whenever the session key changes.
 */
final class ClientFactory
{
    public const SOURCE_SESSION = 'session';
    public const SOURCE_ENV = '.env';
    public const SOURCE_NONE = 'none';

    public static function make(HistoryStore $history): LogDBClient
    {
        $apiKey = self::effectiveApiKey();
        if ($apiKey === null) {
            throw new MissingApiKey('No LogDB API key found in session or .env. Sign in at /auth.');
        }

        return new LogDBClient(new LogDBClientOptions(
            endpoint: self::effectiveEndpoint(),
            apiKey: $apiKey,
            defaultApplication: self::application(),
            defaultEnvironment: self::environment(),
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
    }

    public static function effectiveApiKey(): ?string
    {
        $s = $_SESSION['logdb_api_key'] ?? null;
        if (is_string($s) && $s !== '') {
            return $s;
        }
        $e = Env::get('LOGDB_API_KEY');
        if ($e !== null && $e !== '' && $e !== 'your-api-key-here') {
            return $e;
        }
        return null;
    }

    public static function effectiveEndpoint(): string
    {
        $s = $_SESSION['logdb_endpoint'] ?? null;
        if (is_string($s) && $s !== '') {
            return $s;
        }
        return Env::get('LOGDB_ENDPOINT', 'https://otlp.logdb.site') ?? 'https://otlp.logdb.site';
    }

    public static function keySource(): string
    {
        if (isset($_SESSION['logdb_api_key']) && is_string($_SESSION['logdb_api_key']) && $_SESSION['logdb_api_key'] !== '') {
            return self::SOURCE_SESSION;
        }
        $e = Env::get('LOGDB_API_KEY');
        if ($e !== null && $e !== '' && $e !== 'your-api-key-here') {
            return self::SOURCE_ENV;
        }
        return self::SOURCE_NONE;
    }

    /** Mask all but the last 4 chars: `pk_…3c4b502` style. */
    public static function maskKey(string $key): string
    {
        $tail = substr($key, -4);
        $prefix = strtok($key, '_');
        return ($prefix !== false && $prefix !== $key ? "{$prefix}_…" : '…') . $tail;
    }

    /** Convenience wrapper used by the Status screen and sidebar header. */
    public static function maskedActiveKey(): ?string
    {
        $k = self::effectiveApiKey();
        return $k === null ? null : self::maskKey($k);
    }

    public static function application(): string
    {
        return Env::get('LOGDB_APPLICATION', 'logdb-php-sample') ?? 'logdb-php-sample';
    }

    public static function environment(): string
    {
        return Env::get('LOGDB_ENVIRONMENT', 'development') ?? 'development';
    }

    /** Replace the session API key + (optional) endpoint. */
    public static function signIn(string $apiKey, ?string $endpoint = null): void
    {
        $_SESSION['logdb_api_key'] = $apiKey;
        if ($endpoint !== null && $endpoint !== '') {
            $_SESSION['logdb_endpoint'] = $endpoint;
        } else {
            unset($_SESSION['logdb_endpoint']);
        }
    }

    public static function signOut(): void
    {
        unset($_SESSION['logdb_api_key'], $_SESSION['logdb_endpoint']);
    }
}
