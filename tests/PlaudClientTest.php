<?php

declare(strict_types=1);

namespace Plaud\Tests;

use PHPUnit\Framework\TestCase;
use Plaud\Config;
use Plaud\Exceptions\ApiException;
use Plaud\Exceptions\AuthenticationException;
use Plaud\Exceptions\NotFoundException;
use Plaud\Http\HttpClientInterface;
use Plaud\Http\HttpResponse;
use Plaud\PlaudAuth;
use Plaud\PlaudClient;
use Plaud\Storage\InMemoryTokenStorage;

class PlaudClientTest extends TestCase
{
    private function createClientWithMockHttp(HttpClientInterface $mockHttp, string $region = Config::REGION_US): PlaudClient
    {
        $config = new Config(
            region: $region,
            accessToken: 'valid_mock_token'
        );
        $auth = new PlaudAuth($config, new InMemoryTokenStorage(), $mockHttp);
        return new PlaudClient($auth, $mockHttp);
    }

    public function testListRecordingsFiltersTrash(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo('https://api.plaud.ai/file/simple/web'),
                $this->callback(fn($headers) => isset($headers['Authorization']) && $headers['Authorization'] === 'Bearer valid_mock_token')
            )
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'data_file_list' => [
                        [
                            'file_id' => 'rec_1',
                            'file_name' => 'Active Recording 1',
                            'is_trash' => false,
                            'duration' => 60000,
                        ],
                        [
                            'file_id' => 'rec_2',
                            'file_name' => 'Trashed Recording',
                            'is_trash' => true,
                            'duration' => 30000,
                        ],
                        [
                            'file_id' => 'rec_3',
                            'file_name' => 'Active Recording 2',
                            'is_trash' => false,
                            'duration' => 120000,
                        ],
                    ]
                ])
            ));

        $client = $this->createClientWithMockHttp($mockHttp);
        $recordings = $client->listRecordings();

        $this->assertCount(2, $recordings);
        $this->assertSame('rec_1', $recordings[0]->id);
        $this->assertSame('rec_3', $recordings[1]->id);
    }

    public function testSummaryMethodsAndLegacyTranscriptAlias(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->exactly(4))
            ->method('request')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo('https://api.plaud.ai/file/detail/test_rec_id')
            )
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'data' => [
                        'file_id' => 'test_rec_id',
                        'file_name' => 'Strategy Call.mp3',
                        'pre_download_content_list' => [
                            ['data_content' => 'Short'],
                            ['data_content' => 'This is the standard summary from Plaud.'],
                        ],
                        'summary' => 'Custom-template output',
                    ]
                ])
            ));

        $client = $this->createClientWithMockHttp($mockHttp);
        $this->assertSame('This is the standard summary from Plaud.', $client->getSummary('test_rec_id'));
        $this->assertSame('Custom-template output', $client->getCustomSummary('test_rec_id'));
        $this->assertSame('This is the standard summary from Plaud.', $client->getTranscript('test_rec_id'));
        $detail = $client->getRecording('test_rec_id');
        $this->assertSame('This is the standard summary from Plaud.', $detail->summary);
        $this->assertSame('Custom-template output', $detail->customSummary);
    }

    public function testMissingSummaries(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->exactly(2))->method('request')
            ->willReturn(new HttpResponse(200, [], '{"data":{"file_id":"empty"}}'));
        $client = $this->createClientWithMockHttp($mockHttp);
        $this->assertSame('', $client->getSummary('empty'));
        $this->assertNull($client->getCustomSummary('empty'));
    }

    public function testGetUserInfo(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo('https://api.plaud.ai/user/me')
            )
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'data_user' => [
                        'id' => 'user_123',
                        'nickname' => 'John Doe',
                        'email' => 'john@example.com',
                        'country' => 'US',
                    ],
                    'data_state' => [
                        'membership_type' => 'pro',
                    ]
                ])
            ));

        $client = $this->createClientWithMockHttp($mockHttp);
        $user = $client->getUserInfo();

        $this->assertSame('user_123', $user->id);
        $this->assertSame('John Doe', $user->nickname);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('pro', $user->membershipType);
    }

    public function testDownloadAudio(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo('https://api.plaud.ai/file/download/rec_audio_1')
            )
            ->willReturn(new HttpResponse(
                200,
                ['Content-Type' => 'audio/mpeg'],
                'BINARY_AUDIO_STREAM_DATA'
            ));

        $client = $this->createClientWithMockHttp($mockHttp);
        $audio = $client->downloadAudio('rec_audio_1');

        $this->assertSame('BINARY_AUDIO_STREAM_DATA', $audio);
    }

    public function testRegionMismatch302Redirect(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) {
                if (str_contains($url, 'api.plaud.ai')) {
                    // Return -302 redirect response pointing to euc1
                    return new HttpResponse(
                        200,
                        ['Content-Type' => 'application/json'],
                        json_encode([
                            'status' => -302,
                            'data' => [
                                'domains' => [
                                    'api' => 'api-euc1.plaud.ai'
                                ]
                            ]
                        ])
                    );
                }

                if (str_contains($url, 'api-euc1.plaud.ai')) {
                    return new HttpResponse(
                        200,
                        ['Content-Type' => 'application/json'],
                        json_encode([
                            'status' => 0,
                            'data_file_list' => []
                        ])
                    );
                }

                return new HttpResponse(500, [], '');
            });

        $client = $this->createClientWithMockHttp($mockHttp, Config::REGION_US);
        $result = $client->listRecordings();

        $this->assertIsArray($result);
        $this->assertSame(Config::REGION_EU, $client->getAuth()->getConfig()->getRegion());
    }

    public function testNotFoundThrowsNotFoundException(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->method('request')
            ->willReturn(new HttpResponse(404, [], '{"msg":"Not Found"}'));

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->expectException(NotFoundException::class);
        $client->getRecording('non_existing_id');
    }

    public function testUnauthorizedThrowsAuthenticationException(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->method('request')
            ->willReturn(new HttpResponse(401, [], '{"msg":"Unauthorized"}'));

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->expectException(AuthenticationException::class);
        $client->getRecording('some_id');
    }
}
