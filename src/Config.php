<?php

declare(strict_types=1);

namespace Plaud;

class Config
{
    public const REGION_US = 'us';
    public const REGION_EU = 'eu';

    public const DEFAULT_BASE_URLS = [
        self::REGION_US => 'https://api.plaud.ai',
        self::REGION_EU => 'https://api-euc1.plaud.ai',
    ];

    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public const DEFAULT_TOKEN_REFRESH_BUFFER_SECONDS = 30 * 24 * 60 * 60; // 30 days

    /**
     * @param string $region 'us' or 'eu'
     * @param string|null $email
     * @param string|null $password
     * @param string|null $accessToken Direct bearer token (if not using email/password login)
     * @param array<string, string> $baseUrls
     * @param string $userAgent
     * @param int $tokenRefreshBufferSeconds
     */
    public function __construct(
        private string $region = self::REGION_US,
        private ?string $email = null,
        private ?string $password = null,
        private ?string $accessToken = null,
        private array $baseUrls = self::DEFAULT_BASE_URLS,
        private string $userAgent = self::DEFAULT_USER_AGENT,
        private int $tokenRefreshBufferSeconds = self::DEFAULT_TOKEN_REFRESH_BUFFER_SECONDS
    ) {
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = strtolower($region) === self::REGION_EU ? self::REGION_EU : self::REGION_US;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getBaseUrl(?string $region = null): string
    {
        $reg = $region ?? $this->region;
        return $this->baseUrls[$reg] ?? $this->baseUrls[self::REGION_US];
    }

    public function setBaseUrl(string $url, ?string $region = null): self
    {
        $reg = $region ?? $this->region;
        $this->baseUrls[$reg] = rtrim($url, '/');
        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getTokenRefreshBufferSeconds(): int
    {
        return $this->tokenRefreshBufferSeconds;
    }
}
