<?php

declare(strict_types=1);

namespace Plaud\Tests\DTO;

use PHPUnit\Framework\TestCase;
use Plaud\DTO\RecordingDetail;

class RecordingDetailTest extends TestCase
{
    public function testSelectsLongestTranscriptFromPreDownloadList(): void
    {
        $payload = [
            'file_id' => 'rec_abc123',
            'file_name' => 'Meeting with Team.mp3',
            'file_size' => 1048576,
            'duration' => 120000, // 2 minutes (120,000 ms)
            'start_time' => 1700000000000,
            'end_time' => 1700000120000,
            'is_trash' => false,
            'is_trans' => true,
            'pre_download_content_list' => [
                [
                    'data_content' => 'Short summary draft.',
                ],
                [
                    'data_content' => 'Full comprehensive transcript with all speaker statements and detailed discussion points.',
                ],
                [
                    'data_content' => 'Brief notes.',
                ],
            ],
            'summary' => 'Discussion regarding project launch.',
        ];

        $detail = RecordingDetail::fromArray($payload);

        $this->assertSame('rec_abc123', $detail->id);
        $this->assertSame('Meeting with Team.mp3', $detail->filename);
        $this->assertSame(
            'Full comprehensive transcript with all speaker statements and detailed discussion points.',
            $detail->transcript
        );
        $this->assertSame('Discussion regarding project launch.', $detail->summary);
        $this->assertSame(2, $detail->getDurationMinutes());
        $this->assertTrue($detail->hasTranscript());
    }

    public function testFallbackWhenNoPreDownloadList(): void
    {
        $payload = [
            'id' => 'rec_xyz',
            'filename' => 'Voice Note.mp3',
            'transcript' => 'Direct transcript content.',
        ];

        $detail = RecordingDetail::fromArray($payload);

        $this->assertSame('rec_xyz', $detail->id);
        $this->assertSame('Direct transcript content.', $detail->transcript);
        $this->assertNull($detail->summary);
    }
}
