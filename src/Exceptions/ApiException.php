<?php

declare(strict_types=1);

namespace Plaud\Exceptions;

/**
 * Thrown when the Plaud API returns an error status code or unexpected payload.
 */
class ApiException extends PlaudException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly mixed $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
