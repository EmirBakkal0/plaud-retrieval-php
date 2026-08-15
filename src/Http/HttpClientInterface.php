<?php

declare(strict_types=1);

namespace Plaud\Http;

use Plaud\Exceptions\PlaudException;

interface HttpClientInterface
{
    /**
     * Send an HTTP request and return an HttpResponse.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Target full URL
     * @param array<string, string> $headers Headers as key => value
     * @param string|null $body Request payload
     * @return HttpResponse
     * @throws PlaudException On network or connection failure
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null
    ): HttpResponse;
}
