<?php

declare(strict_types=1);

namespace Plaud\Storage;

use Plaud\DTO\TokenData;

/**
 * Default in-memory token storage.
 * Does not write to disk or database. Token is stored only during request / process execution.
 */
class InMemoryTokenStorage implements TokenStorageInterface
{
    public function __construct(
        private ?TokenData $token = null
    ) {
    }

    public function getToken(): ?TokenData
    {
        return $this->token;
    }

    public function saveToken(TokenData $token): void
    {
        $this->token = $token;
    }

    public function clearToken(): void
    {
        $this->token = null;
    }
}
