<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../autoload.php';
}

use Plaud\PlaudClient;

$email = getenv('PLAUD_EMAIL') ?: 'user@example.com';
$password = getenv('PLAUD_PASSWORD') ?: 'secret-password';
$recordingId = $argv[1] ?? 'example_recording_id_123';
$destinationPath = $argv[2] ?? __DIR__ . '/output.mp3';

try {
    $client = PlaudClient::createWithCredentials($email, $password);

    // Option A: Get temporary MP3 signed URL
    $tempMp3Url = $client->getMp3Url($recordingId);
    if ($tempMp3Url) {
        echo "Temporary MP3 URL (expires shortly):\n{$tempMp3Url}\n\n";
    }

    // Option B: Download binary audio stream and save to file
    echo "Downloading audio stream for recording {$recordingId}...\n";
    $bytes = $client->saveAudioToFile($recordingId, $destinationPath);

    echo "Successfully saved audio to {$destinationPath} ({$bytes} bytes).\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
