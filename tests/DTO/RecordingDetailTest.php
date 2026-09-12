<?php

declare(strict_types=1);

namespace Plaud\Tests\DTO;

use PHPUnit\Framework\TestCase;
use Plaud\DTO\RecordingDetail;

class RecordingDetailTest extends TestCase
{
    public function testSummaryMappingAndLegacyAliases(): void
    {
        $payload = ['data' => [
            'transcript' => 'Standard summary',
            'summary' => 'Custom-template summary',
            'ai_summary' => 'Fallback custom summary',
            'pre_download_content_list' => [null, [], ['data_content' => []]],
        ]];
        $detail = RecordingDetail::fromArray($payload);
        $this->assertSame('Standard summary', $detail->summary);
        $this->assertSame('Custom-template summary', $detail->customSummary);
        $this->assertSame($detail->summary, $detail->transcript);
        $this->assertTrue($detail->hasTranscript());
        $this->assertTrue($detail->hasCustomSummary());
        $this->assertSame($payload['data'], $detail->raw);
    }

    public function testCustomSummaryFallbackDoesNotBecomeStandardSummary(): void
    {
        $detail = RecordingDetail::fromArray(['ai_summary' => 'Template output']);
        $this->assertSame('', $detail->summary);
        $this->assertSame('Template output', $detail->customSummary);
        $this->assertFalse($detail->hasSummary());
        $this->assertTrue($detail->hasCustomSummary());
    }

    public function testMissingAndBlankContent(): void
    {
        foreach ([[], ['transcript' => '  ', 'summary' => "\n"],
            ['transcript' => [], 'summary' => [], 'pre_download_content_list' => false]] as $payload) {
            $detail = RecordingDetail::fromArray($payload);
            $this->assertFalse($detail->hasSummary());
            $this->assertFalse($detail->hasCustomSummary());
            $this->assertFalse($detail->hasTranscript());
        }
        $this->assertNull(RecordingDetail::fromArray([])->customSummary);
    }

    public function testSelectsLongestSummaryFromPreDownloadList(): void
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
                    'data_content' => 'Full standard summary with detailed discussion points.',
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
            'Full standard summary with detailed discussion points.',
            $detail->summary
        );
        $this->assertSame('Discussion regarding project launch.', $detail->customSummary);
        $this->assertSame(2, $detail->getDurationMinutes());
        $this->assertTrue($detail->hasSummary());
    }

    public function testFallbackWhenNoPreDownloadList(): void
    {
        $payload = [
            'id' => 'rec_xyz',
            'filename' => 'Voice Note.mp3',
            'transcript' => 'Standard summary in legacy upstream field.',
        ];

        $detail = RecordingDetail::fromArray($payload);

        $this->assertSame('rec_xyz', $detail->id);
        $this->assertSame('Standard summary in legacy upstream field.', $detail->summary);
        $this->assertNull($detail->customSummary);
    }
}
