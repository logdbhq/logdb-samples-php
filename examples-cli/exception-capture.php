<?php

declare(strict_types=1);

/**
 * Exception capture — show how a Throwable in $context['exception'] becomes
 * a fully-stamped Log entry (Log->exception + Log->stackTrace + level=Error).
 *
 *   LOGDB_API_KEY=your-key php examples-cli/exception-capture.php
 */

require __DIR__ . '/../vendor/autoload.php';

use LogDB\LogDBClient;
use LogDB\Options\LogDBClientOptions;

$client = new LogDBClient(new LogDBClientOptions(
    endpoint: getenv('LOGDB_ENDPOINT') ?: 'https://otlp.logdb.site',
    apiKey: getenv('LOGDB_API_KEY') ?: '',
    defaultApplication: 'logdb-php-sample-cli',
    defaultEnvironment: 'development',
));

function chargeCard(string $orderId): void
{
    throw new \RuntimeException("payment gateway returned 502 for order {$orderId}");
}

$traceId = bin2hex(random_bytes(8));

try {
    chargeCard('order-' . $traceId);
} catch (\Throwable $e) {
    // PSR-3 standard: 'exception' context key holds a Throwable.
    // The SDK populates Log->exception (class + message), Log->stackTrace
    // (full PHP backtrace), and bumps level to Error if not set.
    $client->error('payment failed', [
        'exception' => $e,
        'user_email' => 'alice@example.com',
        'correlation_id' => $traceId,
        'order_id' => 'order-' . $traceId,
    ]);
}

$client->dispose();
echo "OK — search LogDB for trace={$traceId}\n";
