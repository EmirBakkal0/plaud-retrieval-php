<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../autoload.php';
}

use Plaud\Config;
use Plaud\Exceptions\ApiException;
use Plaud\Exceptions\AuthenticationException;
use Plaud\PlaudClient;

// 1. Configure credentials and region
$email = getenv('PLAUD_EMAIL') ?: 'user@example.com';
$password = getenv('PLAUD_PASSWORD') ?: 'secret-password';
$region = getenv('PLAUD_REGION') ?: Config::REGION_US; // or Config::REGION_EU
$recordingId = $argv[1] ?? 'example_recording_id_123';

try {
    // 2. Initialize the client (Zero database, in-memory token management)
    $client = PlaudClient::createWithCredentials(
        email: $email,
        password: $password,
        region: $region
    );

    echo "Fetching recording detail for ID: {$recordingId}...\n";

    // Option A: Get full recording details
    $recording = $client->getRecording($recordingId);

    echo "========================================\n";
    echo "Title:     " . $recording->filename . "\n";
    echo "Date:      " . $recording->getFormattedStartDate('Y-m-d H:i:s') . "\n";
    echo "Duration:  " . $recording->getDurationMinutes() . " minutes\n";
    echo "File Size: " . round($recording->filesize / (1024 * 1024), 2) . " MB\n";
    echo "========================================\n";
    echo "TRANSCRIPT:\n";
    echo ($recording->transcript ?: '(No transcript available)') . "\n";

    if ($recording->summary) {
        echo "\nSUMMARY:\n" . $recording->summary . "\n";
    }

    // Option B: Or directly fetch the transcript string
    // $transcriptText = $client->getTranscript($recordingId);

} catch (AuthenticationException $e) {
    echo "Authentication Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (ApiException $e) {
    echo "API Error [{$e->getStatusCode()}]: " . $e->getMessage() . "\n";
    exit(1);
}
