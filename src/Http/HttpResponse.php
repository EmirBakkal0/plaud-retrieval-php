<?php

declare(strict_types=1);

namespace Plaud\Http;

use JsonException;

class HttpResponse
{
    /**
     * @param int $statusCode
     * @param array<string, string|string[]> $headers
     * @param string $body
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isOk(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalizedTarget = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string)$key) === $normalizedTarget) {
                return is_array($value) ? implode(', ', $value) : (string)$value;
            }
        }
        return null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return mixed
     * @throws JsonException
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
