<?php

declare(strict_types=1);

/**
 * PSR-3 quickstart — the smallest interesting `logdbhq/logdb-php` program.
 *
 *   LOGDB_API_KEY=your-key php examples-cli/psr3-quickstart.php
 */

require __DIR__ . '/../vendor/autoload.php';

use LogDB\LogDBClient;
use LogDB\Options\LogDBClientOptions;

$apiKey = getenv('LOGDB_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set LOGDB_API_KEY in env.\n");
    exit(1);
}

$client = new LogDBClient(new LogDBClientOptions(
    endpoint: getenv('LOGDB_ENDPOINT') ?: 'https://otlp.logdb.site',
    apiKey: $apiKey,
    defaultApplication: 'logdb-php-sample-cli',
    defaultEnvironment: 'development',
));

// PSR-3 ergonomics. Well-known context keys (user_email, correlation_id,
// http_method, request_path, status_code, ip_address) lift into top-level
// Log columns. Other keys route to typed attribute maps by PHP value type.
$client->info('user logged in', [
    'user_email' => 'alice@example.com',
    'correlation_id' => 'trace-abc-123',
    'tenant' => 'acme',         // string → attributesS
    'amount_eur' => 199.99,     // float  → attributesN
    'admin' => false,           // bool   → attributesB
]);

$client->warning('cache miss', ['key' => 'user:42:profile']);

// Drain the buffer + close the curl handle. The destructor + the default
// shutdown handler also call dispose(); explicit is just cleaner.
$client->dispose();

echo "OK\n";
