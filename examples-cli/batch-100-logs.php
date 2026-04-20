<?php

declare(strict_types=1);

/**
 * Batched-write demo — sends 100 logs through `LogEventBuilder`, prints the
 * elapsed time and per-status breakdown. Same logic as the web app's
 * /stress screen but without the HTML wrap.
 *
 *   LOGDB_API_KEY=your-key php examples-cli/batch-100-logs.php
 */

require __DIR__ . '/../vendor/autoload.php';

use LogDB\Builders\LogEventBuilder;
use LogDB\LogDBClient;
use LogDB\Models\LogLevel;
use LogDB\Models\LogResponseStatus;
use LogDB\Options\LogDBClientOptions;

$client = new LogDBClient(new LogDBClientOptions(
    endpoint: getenv('LOGDB_ENDPOINT') ?: 'https://otlp.logdb.site',
    apiKey: getenv('LOGDB_API_KEY') ?: '',
    defaultApplication: 'logdb-php-sample-cli',
    defaultEnvironment: 'development',
    batchSize: 50,        // flush twice during this run
    flushInterval: 5_000,
));

$count = 100;
$tag = bin2hex(random_bytes(3));
$statuses = [];

$started = microtime(true);

for ($i = 0; $i < $count; $i++) {
    $level = match (true) {
        $i % 20 === 0 => LogLevel::Error,
        $i % 7  === 0 => LogLevel::Warning,
        default       => LogLevel::Info,
    };

    $status = LogEventBuilder::create($client)
        ->setMessage("batch sample probe={$tag} seq={$i}")
        ->setLogLevel($level)
        ->addLabel('batch-sample')
        ->addAttribute('probe', $tag)
        ->addAttribute('seq', $i)
        ->log();

    $statuses[$status->value] = ($statuses[$status->value] ?? 0) + 1;
}

// Drain the batch buffer so the timing reflects the full round-trip.
$client->flush();

$elapsedMs = (microtime(true) - $started) * 1000;

echo str_pad('count', 12) . $count . "\n";
echo str_pad('elapsed', 12) . number_format($elapsedMs, 0) . " ms\n";
echo str_pad('per sec', 12) . number_format($count / ($elapsedMs / 1000), 0) . "\n";
echo str_pad('probe tag', 12) . $tag . "\n";
echo str_pad('statuses', 12) . json_encode($statuses) . "\n";

$client->dispose();

exit(($statuses[LogResponseStatus::Success->value] ?? 0) === $count ? 0 : 1);
