<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../autoload.php';
}

use Plaud\DTO\TokenData;
use Plaud\PlaudClient;
use Plaud\Storage\TokenStorageInterface;

/**
 * Example custom token storage implementation.
 * You can implement this to cache tokens in PSR-6/PSR-16 Cache, Redis, Session, or anywhere else.
 */
class CustomArrayTokenStorage implements TokenStorageInterface
{
    private static array $globalStore = [];

    public function getToken(): ?TokenData
    {
        if (isset(self::$globalStore['token'])) {
            return TokenData::fromArray(self::$globalStore['token']);
        }
        return null;
    }

    public function saveToken(TokenData $token): void
    {
        self::$globalStore['token'] = $token->toArray();
    }

    public function clearToken(): void
    {
        unset(self::$globalStore['token']);
    }
}

// Usage:
$storage = new CustomArrayTokenStorage();
$client = PlaudClient::createWithCredentials(
    email: 'user@example.com',
    password: 'password',
    storage: $storage
);

echo "Client configured with custom token storage.\n";
