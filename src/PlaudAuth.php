<?php

declare(strict_types=1);

namespace Plaud;

use JsonException;
use Plaud\DTO\TokenData;
use Plaud\Exceptions\AuthenticationException;
use Plaud\Http\CurlHttpClient;
use Plaud\Http\HttpClientInterface;
use Plaud\Storage\InMemoryTokenStorage;
use Plaud\Storage\TokenStorageInterface;

class PlaudAuth
{
    private Config $config;
    private TokenStorageInterface $storage;
    private HttpClientInterface $httpClient;

    public function __construct(
        ?Config $config = null,
        ?TokenStorageInterface $storage = null,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->config = $config ?? new Config();
        $this->storage = $storage ?? new InMemoryTokenStorage();
        $this->httpClient = $httpClient ?? new CurlHttpClient(userAgent: $this->config->getUserAgent());

        // Preload direct access token if provided in config
        if ($this->config->getAccessToken() !== null && $this->storage->getToken() === null) {
            try {
                $tokenData = TokenData::fromAccessToken($this->config->getAccessToken());
                $this->storage->saveToken($tokenData);
            } catch (AuthenticationException) {
                // If not a standard JWT, store as simple TokenData
                $tokenData = new TokenData(
                    accessToken: $this->config->getAccessToken(),
                    tokenType: 'Bearer',
                    issuedAt: time(),
                    expiresAt: 0
                );
                $this->storage->saveToken($tokenData);
            }
        }
    }

    /**
     * Get a valid access token. Automatically refreshes/logs in if expired or expiring soon.
     *
     * @throws AuthenticationException
     */
    public function getToken(): string
    {
        $cached = $this->storage->getToken();

        if ($cached !== null && !$cached->isExpiringSoon($this->config->getTokenRefreshBufferSeconds())) {
            return $cached->accessToken;
        }

        // Attempt login if credentials are available
        if ($this->config->getEmail() !== null && $this->config->getPassword() !== null) {
            $tokenData = $this->login();
            return $tokenData->accessToken;
        }

        // If we have a cached token that isn't strictly expired yet, return it as fallback
        if ($cached !== null && !$cached->isExpired()) {
            return $cached->accessToken;
        }

        throw new AuthenticationException('No valid token available and no credentials configured to log in.');
    }

    /**
     * Authenticate with email & password against Plaud API.
     *
     * @throws AuthenticationException
     */
    public function login(?string $email = null, ?string $password = null, int $retryCount = 0): TokenData
    {
        $email = $email ?? $this->config->getEmail();
        $password = $password ?? $this->config->getPassword();

        if (empty($email) || empty($password)) {
            throw new AuthenticationException('Plaud email and password must be provided.');
        }

        $baseUrl = $this->config->getBaseUrl();
        $url = "{$baseUrl}/auth/access-token";

        $body = http_build_query([
            'username' => $email,
            'password' => $password,
        ]);

        try {
            $response = $this->httpClient->request(
                method: 'POST',
                url: $url,
                headers: [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                body: $body
            );
        } catch (\Throwable $e) {
            throw new AuthenticationException("Login request failed: {$e->getMessage()}", (int)$e->getCode(), $e);
        }

        try {
            $data = $response->json();
        } catch (JsonException $e) {
            throw new AuthenticationException("Invalid JSON response received during login: {$response->getBody()}", 0, $e);
        }

        if (!is_array($data)) {
            throw new AuthenticationException("Unexpected login response format: {$response->getBody()}");
        }

        $status = $data['status'] ?? null;
        $accessToken = $data['access_token'] ?? null;
        $tokenType = $data['token_type'] ?? 'Bearer';
        $msg = (string) ($data['msg'] ?? ($data['message'] ?? ''));

        // Handle dynamic region mismatch redirect (-302 or msg containing region mismatch)
        $isRegionMismatch = ($status === -302) || (str_contains(strtolower($msg), 'region mismatch'));
        if ($isRegionMismatch && $retryCount < 2) {
            $domain = (string) ($data['data']['domains']['api'] ?? ($data['data']['domain'] ?? ($data['data']['api'] ?? '')));
            if (!empty($domain)) {
                $newRegion = str_contains($domain, 'euc1') ? Config::REGION_EU : Config::REGION_US;
                $this->config->setBaseUrl(!str_starts_with($domain, 'http') ? "https://{$domain}" : $domain, $newRegion);
            } else {
                $newRegion = ($this->config->getRegion() === Config::REGION_EU) ? Config::REGION_US : Config::REGION_EU;
            }

            $this->config->setRegion($newRegion);
            return $this->login($email, $password, $retryCount + 1);
        }

        if ($status !== 0 || empty($accessToken) || !is_string($accessToken)) {
            $errorMsg = !empty($msg) ? $msg : "Login failed with status {$status}";
            throw new AuthenticationException($errorMsg);
        }

        $tokenData = TokenData::fromAccessToken($accessToken, (string)$tokenType);
        $this->storage->saveToken($tokenData);

        return $tokenData;
    }

    /**
     * Explicitly set an access token into storage.
     */
    public function setToken(string $accessToken, string $tokenType = 'Bearer'): TokenData
    {
        try {
            $tokenData = TokenData::fromAccessToken($accessToken, $tokenType);
        } catch (AuthenticationException) {
            $tokenData = new TokenData(
                accessToken: $accessToken,
                tokenType: $tokenType,
                issuedAt: time(),
                expiresAt: 0
            );
        }

        $this->storage->saveToken($tokenData);
        return $tokenData;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getStorage(): TokenStorageInterface
    {
        return $this->storage;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }
}
