<?php

declare(strict_types=1);

namespace Plaud\Storage;

use Plaud\DTO\TokenData;

/**
 * Optional file-based token storage.
 */
class FileTokenStorage implements TokenStorageInterface
{
    public function __construct(
        private readonly string $filePath
    ) {
    }

    public function getToken(): ?TokenData
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $raw = @file_get_contents($this->filePath);
        if ($raw === false || empty($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        // Support both direct token data or wrapped under "token"
        $tokenData = isset($data['token']) && is_array($data['token']) ? $data['token'] : $data;
        if (empty($tokenData['accessToken']) && empty($tokenData['access_token'])) {
            return null;
        }

        return TokenData::fromArray($tokenData);
    }

    public function saveToken(TokenData $token): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $existing = [];
        if (file_exists($this->filePath)) {
            $raw = @file_get_contents($this->filePath);
            if ($raw !== false && !empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }
        }

        $existing['token'] = $token->toArray();
        @file_put_contents($this->filePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($this->filePath, 0600);
    }

    public function clearToken(): void
    {
        if (file_exists($this->filePath)) {
            $raw = @file_get_contents($this->filePath);
            if ($raw !== false && !empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['token'])) {
                    unset($decoded['token']);
                    @file_put_contents($this->filePath, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    return;
                }
            }
            @unlink($this->filePath);
        }
    }
}
