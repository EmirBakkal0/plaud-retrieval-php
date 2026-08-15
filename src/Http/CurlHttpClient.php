<?php

declare(strict_types=1);

namespace Plaud\Http;

use Plaud\Config;
use Plaud\Exceptions\PlaudException;

class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 60,
        private readonly string $userAgent = Config::DEFAULT_USER_AGENT,
        private readonly bool $verifySsl = true,
        private readonly ?string $caBundlePath = null
    ) {
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
        $ch = curl_init();
        if ($ch === false) {
            throw new PlaudException('Failed to initialize cURL handle');
        }

        $formattedHeaders = [];
        $hasUserAgent = false;

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'user-agent') {
                $hasUserAgent = true;
            }
            $formattedHeaders[] = "{$key}: {$value}";
        }

        if (!$hasUserAgent) {
            $formattedHeaders[] = "User-Agent: {$this->userAgent}";
        }

        $method = strtoupper($method);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ];

        if ($this->caBundlePath !== null && file_exists($this->caBundlePath)) {
            $curlOptions[CURLOPT_CAINFO] = $this->caBundlePath;
        }

        curl_setopt_array($ch, $curlOptions);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            // Auto-fallback for Windows environments without CA bundle configured in php.ini
            if ($errno === 60 && $this->verifySsl && PHP_OS_FAMILY === 'Windows') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $retryResponse = curl_exec($ch);
                if ($retryResponse !== false) {
                    $rawResponse = $retryResponse;
                    $error = '';
                    $errno = 0;
                }
            }

            if ($rawResponse === false) {
                curl_close($ch);
                throw new PlaudException("cURL error ({$errno}): {$error}");
            }
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $rawResponse, 0, $headerSize);
        $responseBody = substr((string) $rawResponse, $headerSize);

        $parsedHeaders = $this->parseHeaders($rawHeaders);

        return new HttpResponse($statusCode, $parsedHeaders, (string) $responseBody);
    }

    /**
     * @return array<string, string|string[]>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($rawHeaders));

        foreach ($lines as $line) {
            if (empty($line) || str_starts_with($line, 'HTTP/')) {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                if (isset($headers[$name])) {
                    if (!is_array($headers[$name])) {
                        $headers[$name] = [$headers[$name]];
                    }
                    $headers[$name][] = $value;
                } else {
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }
}
