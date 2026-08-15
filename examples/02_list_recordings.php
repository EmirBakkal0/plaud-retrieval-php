<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../autoload.php';
}

use Plaud\Config;
use Plaud\PlaudClient;

$email = getenv('PLAUD_EMAIL') ?: 'user@example.com';
$password = getenv('PLAUD_PASSWORD') ?: 'secret-password';
$region = getenv('PLAUD_REGION') ?: Config::REGION_US;

try {
    $client = PlaudClient::createWithCredentials($email, $password, $region);

    echo "Fetching recordings list...\n";
    $recordings = $client->listRecordings();

    if (empty($recordings)) {
        echo "No recordings found.\n";
        exit(0);
    }

    echo sprintf("%-34s | %-16s | %-8s | %-5s | %s\n", "ID", "Start Date", "Duration", "Trans", "Filename");
    echo str_repeat('-', 95) . "\n";

    foreach ($recordings as $rec) {
        echo sprintf(
            "%-34s | %-16s | %-8s | %-5s | %s\n",
            $rec->id,
            $rec->getFormattedStartDate('Y-m-d H:i'),
            $rec->getDurationMinutes() . 'm',
            $rec->isTrans ? 'YES' : 'NO',
            $rec->filename
        );
    }

    echo "\nTotal recordings: " . count($recordings) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
