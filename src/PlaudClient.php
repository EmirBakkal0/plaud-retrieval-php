<?php

declare(strict_types=1);

namespace Plaud;

use JsonException;
use Plaud\DTO\Recording;
use Plaud\DTO\RecordingDetail;
use Plaud\DTO\UserInfo;
use Plaud\Exceptions\ApiException;
use Plaud\Exceptions\AuthenticationException;
use Plaud\Exceptions\NotFoundException;
use Plaud\Exceptions\PlaudException;
use Plaud\Http\CurlHttpClient;
use Plaud\Http\HttpClientInterface;
use Plaud\Storage\InMemoryTokenStorage;
use Plaud\Storage\TokenStorageInterface;

class PlaudClient
{
    private PlaudAuth $auth;
    private HttpClientInterface $httpClient;

    public function __construct(
        PlaudAuth $auth,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->auth = $auth;
        $this->httpClient = $httpClient ?? $auth->getHttpClient();
    }

    /**
     * Factory: Create client using email & password.
     */
    public static function createWithCredentials(
        string $email,
        string $password,
        string $region = Config::REGION_US,
        ?TokenStorageInterface $storage = null,
        ?HttpClientInterface $httpClient = null
    ): self {
        $config = new Config(
            region: $region,
            email: $email,
            password: $password
        );
        $storage = $storage ?? new InMemoryTokenStorage();
        $auth = new PlaudAuth($config, $storage, $httpClient);
        return new self($auth, $httpClient);
    }

    /**
     * Factory: Create client with an existing access token.
     */
    public static function createWithToken(
        string $accessToken,
        string $region = Config::REGION_US,
        ?HttpClientInterface $httpClient = null
    ): self {
        $config = new Config(
            region: $region,
            accessToken: $accessToken
        );
        $storage = new InMemoryTokenStorage();
        $auth = new PlaudAuth($config, $storage, $httpClient);
        return new self($auth, $httpClient);
    }

    /**
     * Factory: Create client from a Config object.
     */
    public static function create(
        Config $config,
        ?TokenStorageInterface $storage = null,
        ?HttpClientInterface $httpClient = null
    ): self {
        $storage = $storage ?? new InMemoryTokenStorage();
        $auth = new PlaudAuth($config, $storage, $httpClient);
        return new self($auth, $httpClient);
    }

    /**
     * Send an authenticated JSON request to the Plaud API.
     * Handles automatic -302 region redirection if needed.
     *
     * @param string $path
     * @param string $method
     * @param array<string, string> $headers
     * @param string|null $body
     * @return array<string, mixed>
     *
     * @throws AuthenticationException
     * @throws ApiException
     * @throws PlaudException
     */
    public function request(
        string $path,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null,
        int $retryCount = 0
    ): array {
        $token = $this->auth->getToken();
        $baseUrl = $this->auth->getConfig()->getBaseUrl();
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $defaultHeaders = [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        $response = $this->httpClient->request(
            method: $method,
            url: $url,
            headers: $mergedHeaders,
            body: $body
        );

        if (!$response->isOk()) {
            if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
                throw new AuthenticationException(
                    "Plaud API authentication failed with HTTP {$response->getStatusCode()}: {$response->getBody()}",
                    $response->getStatusCode()
                );
            }
            if ($response->getStatusCode() === 404) {
                throw new NotFoundException(
                    "Resource not found at {$path}",
                    $response->getStatusCode()
                );
            }
            throw new ApiException(
                "Plaud API error (HTTP {$response->getStatusCode()}): {$response->getBody()}",
                $response->getStatusCode(),
                $response->getBody()
            );
        }

        try {
            $data = $response->json();
        } catch (JsonException $e) {
            throw new ApiException(
                "Failed to parse JSON response from Plaud API: {$response->getBody()}",
                $response->getStatusCode(),
                $response->getBody(),
                $e
            );
        }

        if (!is_array($data)) {
            throw new ApiException(
                "Unexpected API response type: expected array, got " . gettype($data),
                $response->getStatusCode(),
                $data
            );
        }

        // Handle dynamic region mismatch redirect (-302)
        if (isset($data['status']) && $data['status'] === -302 && isset($data['data']['domains']['api']) && $retryCount < 2) {
            $domain = (string)$data['data']['domains']['api'];
            $newRegion = str_contains($domain, 'euc1') ? Config::REGION_EU : Config::REGION_US;
            if ($newRegion !== $this->auth->getConfig()->getRegion()) {
                $this->auth->getConfig()->setRegion($newRegion);
                return $this->request($path, $method, $headers, $body, $retryCount + 1);
            }
        }

        return $data;
    }

    /**
     * List all recordings (excluding trashed recordings).
     *
     * @return Recording[]
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function listRecordings(): array
    {
        $data = $this->request('/file/simple/web');
        $rawList = $data['data_file_list'] ?? ($data['data'] ?? []);

        if (!is_array($rawList)) {
            return [];
        }

        $recordings = [];
        foreach ($rawList as $item) {
            if (is_array($item)) {
                $rec = Recording::fromArray($item);
                if (!$rec->isTrash) {
                    $recordings[] = $rec;
                }
            }
        }

        return $recordings;
    }

    /**
     * Get full details of a recording including standard and custom-template summaries.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NotFoundException
     */
    public function getRecording(string $id): RecordingDetail
    {
        if (empty(trim($id))) {
            throw new NotFoundException('Recording ID cannot be empty');
        }

        $data = $this->request("/file/detail/{$id}");
        return RecordingDetail::fromArray($data);
    }

    /**
     * Directly retrieve the standard summary for a recording.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NotFoundException
     */
    public function getSummary(string $id): string
    {
        $detail = $this->getRecording($id);
        return $detail->summary;
    }

    /**
     * Retrieve the custom-template summary, or null when unavailable.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NotFoundException
     */
    public function getCustomSummary(string $id): ?string
    {
        return $this->getRecording($id)->customSummary;
    }

    /** @deprecated Use getSummary(). This method does not retrieve a verbatim transcript. */
    public function getTranscript(string $id): string
    {
        return $this->getSummary($id);
    }

    /**
     * Get current user profile and account details.
     *
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function getUserInfo(): UserInfo
    {
        $data = $this->request('/user/me');
        return UserInfo::fromArray($data);
    }

    /**
     * Download binary audio data for a recording.
     *
     * @return string Binary audio content
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function downloadAudio(string $id): string
    {
        if (empty(trim($id))) {
            throw new NotFoundException('Recording ID cannot be empty');
        }

        $token = $this->auth->getToken();
        $baseUrl = $this->auth->getConfig()->getBaseUrl();
        $url = rtrim($baseUrl, '/') . "/file/download/{$id}";

        $response = $this->httpClient->request(
            method: 'GET',
            url: $url,
            headers: [
                'Authorization' => "Bearer {$token}",
            ]
        );

        if (!$response->isOk()) {
            throw new ApiException(
                "Audio download failed (HTTP {$response->getStatusCode()})",
                $response->getStatusCode()
            );
        }

        return $response->getBody();
    }

    /**
     * Download audio and save directly to a file on disk.
     *
     * @param string $id Recording ID
     * @param string $destinationPath Path to save the audio file
     * @return int Bytes written
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws PlaudException
     */
    public function saveAudioToFile(string $id, string $destinationPath): int
    {
        $audioData = $this->downloadAudio($id);

        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $written = @file_put_contents($destinationPath, $audioData);
        if ($written === false) {
            throw new PlaudException("Failed to write audio file to {$destinationPath}");
        }

        return $written;
    }

    /**
     * Get a temporary download URL for the MP3 version of a recording.
     */
    public function getMp3Url(string $id): ?string
    {
        try {
            $data = $this->request("/file/temp-url/{$id}?is_opus=false");
            return $data['url'] ?? ($data['data']['url'] ?? ($data['data'] ?? ($data['temp_url'] ?? null)));
        } catch (\Throwable) {
            return null;
        }
    }

    public function getAuth(): PlaudAuth
    {
        return $this->auth;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }
}
