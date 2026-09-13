<?php

declare(strict_types=1);

namespace Plaud\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Plaud\DTO\TokenData;
use Plaud\Storage\InMemoryTokenStorage;

class InMemoryTokenStorageTest extends TestCase
{
    private InMemoryTokenStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryTokenStorage();
    }

    public function testReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->storage->getToken());
    }

    public function testSaveAndRetrieveToken(): void
    {
        $token = new TokenData(
            accessToken: 'sample_token_123',
            tokenType: 'Bearer',
            issuedAt: time(),
            expiresAt: time() + 3600
        );

        $this->storage->saveToken($token);

        $retrieved = $this->storage->getToken();
        $this->assertNotNull($retrieved);
        $this->assertSame('sample_token_123', $retrieved->accessToken);
        $this->assertSame('Bearer', $retrieved->tokenType);
    }

    public function testClearToken(): void
    {
        $token = new TokenData(
            accessToken: 'token_to_clear',
            tokenType: 'Bearer',
            issuedAt: time(),
            expiresAt: time() + 3600
        );

        $this->storage->saveToken($token);
        $this->storage->clearToken();

        $this->assertNull($this->storage->getToken());
    }
}