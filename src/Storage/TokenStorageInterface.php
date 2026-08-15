<?php

declare(strict_types=1);

namespace Plaud\Storage;

use Plaud\DTO\TokenData;

interface TokenStorageInterface
{
    /**
     * Retrieve the cached token data, or null if none is available.
     */
    public function getToken(): ?TokenData;

    /**
     * Store the token data.
     */
    public function saveToken(TokenData $token): void;

    /**
     * Remove or clear stored token data.
     */
    public function clearToken(): void;
}
