<?php

declare(strict_types=1);

namespace Plaud\Tests;

use PHPUnit\Framework\TestCase;
use Plaud\Config;
use Plaud\Exceptions\AuthenticationException;
use Plaud\Http\HttpClientInterface;
use Plaud\Http\HttpResponse;
use Plaud\PlaudAuth;
use Plaud\Storage\InMemoryTokenStorage;

class PlaudAuthTest extends TestCase
{
    private function createMockJwt(int $iat, int $exp): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode(json_encode(['sub' => 'user_123', 'iat' => $iat, 'exp' => $exp]))
        );
        $signature = 'mockSignature';
        return "{$header}.{$payload}.{$signature}";
    }

    public function testLoginSuccessAndSavesToStorage(): void
    {
        $now = time();
        $future = $now + (300 * 86400); // 300 days
        $jwt = $this->createMockJwt($now, $future);

        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('POST'),
                $this->equalTo('https://api.plaud.ai/auth/access-token'),
                $this->callback(fn($headers) => isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/x-www-form-urlencoded'),
                $this->stringContains('username=test%40example.com')
            )
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'status' => 0,
                    'access_token' => $jwt,
                    'token_type' => 'Bearer',
                ])
            ));

        $config = new Config(
            region: Config::REGION_US,
            email: 'test@example.com',
            password: 'secretpassword'
        );
        $storage = new InMemoryTokenStorage();

        $auth = new PlaudAuth($config, $storage, $mockHttp);
        $token = $auth->getToken();

        $this->assertSame($jwt, $token);
        $this->assertNotNull($storage->getToken());
        $this->assertSame($jwt, $storage->getToken()->accessToken);
    }

    public function testLoginFailureThrowsAuthenticationException(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->method('request')
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'status' => -1,
                    'msg' => 'Invalid email or password',
                ])
            ));

        $config = new Config(email: 'wrong@example.com', password: 'wrong');
        $auth = new PlaudAuth($config, new InMemoryTokenStorage(), $mockHttp);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid email or password');
        $auth->getToken();
    }

    public function testGetTokenReturnsCachedTokenWithoutHttpCallWhenValid(): void
    {
        $now = time();
        $future = $now + (100 * 86400); // 100 days from now (well beyond 30-day buffer)
        $jwt = $this->createMockJwt($now, $future);

        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->never())->method('request');

        $config = new Config(email: 'user@example.com', password: 'pw');
        $storage = new InMemoryTokenStorage();
        $storage->saveToken(\Plaud\DTO\TokenData::fromAccessToken($jwt));

        $auth = new PlaudAuth($config, $storage, $mockHttp);
        $token = $auth->getToken();

        $this->assertSame($jwt, $token);
    }
}
