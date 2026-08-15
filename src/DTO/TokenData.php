<?php

declare(strict_types=1);

namespace Plaud\DTO;

use Plaud\Exceptions\AuthenticationException;

class TokenData
{
    /**
     * @param string $accessToken The raw JWT access token
     * @param string $tokenType Usually 'Bearer'
     * @param int $issuedAt Unix timestamp in seconds
     * @param int $expiresAt Unix timestamp in seconds
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType = 'Bearer',
        public readonly int $issuedAt = 0,
        public readonly int $expiresAt = 0
    ) {
    }

    /**
     * Parse JWT payload to extract iat and exp.
     *
     * @throws AuthenticationException
     */
    public static function fromAccessToken(string $accessToken, string $tokenType = 'Bearer'): self
    {
        $parts = explode('.', $accessToken);
        if (count($parts) !== 3) {
            throw new AuthenticationException('Invalid JWT token format');
        }

        // Base64Url decode payload (part 1)
        $payloadB64 = strtr($parts[1], '-_', '+/');
        $padding = strlen($payloadB64) % 4;
        if ($padding > 0) {
            $payloadB64 .= str_repeat('=', 4 - $padding);
        }

        $jsonPayload = base64_decode($payloadB64, true);
        if ($jsonPayload === false) {
            throw new AuthenticationException('Failed to base64url-decode JWT payload');
        }

        $data = json_decode($jsonPayload, true);
        if (!is_array($data)) {
            throw new AuthenticationException('Invalid JSON inside JWT payload');
        }

        $iat = isset($data['iat']) ? (int) $data['iat'] : time();
        $exp = isset($data['exp']) ? (int) $data['exp'] : 0;

        return new self(
            accessToken: $accessToken,
            tokenType: $tokenType ?: 'Bearer',
            issuedAt: $iat,
            expiresAt: $exp
        );
    }

    /**
     * Check if the token is already expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === 0) {
            return false;
        }
        return time() >= $this->expiresAt;
    }

    /**
     * Check if the token will expire within the given buffer (default 30 days = 2,592,000s).
     */
    public function isExpiringSoon(int $bufferSeconds = 2592000): bool
    {
        if ($this->expiresAt === 0) {
            return false;
        }
        return (time() + $bufferSeconds) >= $this->expiresAt;
    }

    /**
     * @return array{accessToken: string, tokenType: string, issuedAt: int, expiresAt: int}
     */
    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'tokenType' => $this->tokenType,
            'issuedAt' => $this->issuedAt,
            'expiresAt' => $this->expiresAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) ($data['accessToken'] ?? $data['access_token'] ?? ''),
            tokenType: (string) ($data['tokenType'] ?? $data['token_type'] ?? 'Bearer'),
            issuedAt: (int) ($data['issuedAt'] ?? $data['issued_at'] ?? 0),
            expiresAt: (int) ($data['expiresAt'] ?? $data['expires_at'] ?? 0)
        );
    }
}
