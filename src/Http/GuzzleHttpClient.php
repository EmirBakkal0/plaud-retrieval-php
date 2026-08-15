<?php

declare(strict_types=1);

namespace Plaud\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Plaud\Config;
use Plaud\Exceptions\PlaudException;

/**
 * Optional Guzzle-based HTTP client adapter.
 */
class GuzzleHttpClient implements HttpClientInterface
{
    private GuzzleClient $client;

    public function __construct(
        ?GuzzleClient $client = null,
        private readonly string $userAgent = Config::DEFAULT_USER_AGENT
    ) {
        $this->client = $client ?? new GuzzleClient([
            'http_errors' => false,
            'timeout' => 60,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null
    ): HttpResponse {
        $hasUserAgent = false;
        foreach (array_keys($headers) as $name) {
            if (strtolower((string)$name) === 'user-agent') {
                $hasUserAgent = true;
                break;
            }
        }

        if (!$hasUserAgent) {
            $headers['User-Agent'] = $this->userAgent;
        }

        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::HTTP_ERRORS => false,
        ];

        if ($body !== null) {
            $options[RequestOptions::BODY] = $body;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            return new HttpResponse(
                $response->getStatusCode(),
                $response->getHeaders(),
                (string) $response->getBody()
            );
        } catch (GuzzleException $e) {
            throw new PlaudException("Guzzle HTTP request error: {$e->getMessage()}", 0, $e);
        }
    }
}
