<?php

declare(strict_types=1);

namespace Plaud\Tests\DTO;

use PHPUnit\Framework\TestCase;
use Plaud\DTO\TokenData;
use Plaud\Exceptions\AuthenticationException;

class TokenDataTest extends TestCase
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

    public function testFromAccessTokenDecodesJwtCorrectly(): void
    {
        $now = time();
        $future = $now + 3600;
        $jwt = $this->createMockJwt($now, $future);

        $tokenData = TokenData::fromAccessToken($jwt, 'Bearer');

        $this->assertSame($jwt, $tokenData->accessToken);
        $this->assertSame('Bearer', $tokenData->tokenType);
        $this->assertSame($now, $tokenData->issuedAt);
        $this->assertSame($future, $tokenData->expiresAt);
        $this->assertFalse($tokenData->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $past = time() - 3600;
        $jwt = $this->createMockJwt($past - 7200, $past);

        $tokenData = TokenData::fromAccessToken($jwt);

        $this->assertTrue($tokenData->isExpired());
    }

    public function testIsExpiringSoonWithBuffer(): void
    {
        // Expiring in 10 days
        $expiringIn10Days = time() + (10 * 86400);
        $jwt = $this->createMockJwt(time(), $expiringIn10Days);

        $tokenData = TokenData::fromAccessToken($jwt);

        // Default 30-day buffer should flag it as expiring soon
        $this->assertTrue($tokenData->isExpiringSoon(30 * 86400));
        // 5-day buffer should NOT flag it
        $this->assertFalse($tokenData->isExpiringSoon(5 * 86400));
    }

    public function testInvalidJwtThrowsException(): void
    {
        $this->expectException(AuthenticationException::class);
        TokenData::fromAccessToken('invalid.token');
    }

    public function testArraySerialization(): void
    {
        $token = new TokenData('token123', 'Bearer', 1000, 2000);
        $array = $token->toArray();

        $this->assertSame([
            'accessToken' => 'token123',
            'tokenType' => 'Bearer',
            'issuedAt' => 1000,
            'expiresAt' => 2000,
        ], $array);

        $restored = TokenData::fromArray($array);
        $this->assertSame('token123', $restored->accessToken);
        $this->assertSame(2000, $restored->expiresAt);
    }
}
