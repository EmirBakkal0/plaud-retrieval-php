<?php

declare(strict_types=1);

namespace Plaud\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Plaud\DTO\TokenData;
use Plaud\Storage\FileTokenStorage;

class FileTokenStorageTest extends TestCase
{
    private string $tempFilePath;
    private FileTokenStorage $storage;

    protected function setUp(): void
    {
        $this->tempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plaud_token_test_' . uniqid() . '.json';
        $this->storage = new FileTokenStorage($this->tempFilePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
    }

    public function testFileStorageLifecycle(): void
    {
        $this->assertNull($this->storage->getToken());

        $token = new TokenData(
            accessToken: 'file_access_token',
            tokenType: 'Bearer',
            issuedAt: time(),
            expiresAt: time() + 3600
        );

        $this->storage->saveToken($token);

        $retrieved = $this->storage->getToken();
        $this->assertNotNull($retrieved);
        $this->assertSame('file_access_token', $retrieved->accessToken);

        $this->storage->clearToken();
        $this->assertNull($this->storage->getToken());
    }
}