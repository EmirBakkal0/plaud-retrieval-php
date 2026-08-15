<?php

declare(strict_types=1);

namespace Plaud\DTO;

class RecordingDetail extends Recording
{
    /**
     * @param string $id
     * @param string $filename
     * @param string $fullname
     * @param int $filesize
     * @param int $duration
     * @param int $startTime
     * @param int $endTime
     * @param bool $isTrash
     * @param bool $isTrans
     * @param bool $isSummary
     * @param string[] $keywords
     * @param string $serialNumber
     * @param string $transcript
     * @param string|null $summary
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $id,
        string $filename,
        string $fullname,
        int $filesize,
        int $duration,
        int $startTime,
        int $endTime,
        bool $isTrash,
        bool $isTrans,
        bool $isSummary,
        array $keywords = [],
        string $serialNumber = '',
        public readonly string $transcript = '',
        public readonly ?string $summary = null,
        array $raw = []
    ) {
        parent::__construct(
            id: $id,
            filename: $filename,
            fullname: $fullname,
            filesize: $filesize,
            duration: $duration,
            startTime: $startTime,
            endTime: $endTime,
            isTrash: $isTrash,
            isTrans: $isTrans,
            isSummary: $isSummary,
            keywords: $keywords,
            serialNumber: $serialNumber,
            raw: $raw
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $raw = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;

        $transcript = (string) ($raw['transcript'] ?? '');
        $preDownload = isset($raw['pre_download_content_list']) && is_array($raw['pre_download_content_list'])
            ? $raw['pre_download_content_list']
            : [];

        // Select the longest content from pre_download_content_list if available
        foreach ($preDownload as $item) {
            if (is_array($item) && isset($item['data_content']) && is_string($item['data_content'])) {
                if (strlen($item['data_content']) > strlen($transcript)) {
                    $transcript = $item['data_content'];
                }
            }
        }

        $summary = isset($raw['summary']) && is_string($raw['summary'])
            ? $raw['summary']
            : (isset($raw['ai_summary']) && is_string($raw['ai_summary']) ? $raw['ai_summary'] : null);

        $id = (string) ($raw['file_id'] ?? $raw['id'] ?? '');
        $filename = (string) ($raw['file_name'] ?? $raw['filename'] ?? $id);
        $fullname = (string) ($raw['file_fullname'] ?? $raw['fullname'] ?? $filename);
        $filesize = (int) ($raw['file_size'] ?? $raw['filesize'] ?? 0);
        $duration = (int) ($raw['duration'] ?? 0);
        $startTime = (int) ($raw['start_time'] ?? 0);
        $endTime = (int) ($raw['end_time'] ?? 0);
        $isTrash = (bool) ($raw['is_trash'] ?? false);
        $isTrans = (bool) ($raw['is_trans'] ?? ($transcript !== ''));
        $isSummary = (bool) ($raw['is_summary'] ?? ($summary !== null));
        $keywords = isset($raw['keywords']) && is_array($raw['keywords']) ? $raw['keywords'] : [];
        $serialNumber = (string) ($raw['serial_number'] ?? $raw['sn'] ?? '');

        return new self(
            id: $id,
            filename: $filename,
            fullname: $fullname,
            filesize: $filesize,
            duration: $duration,
            startTime: $startTime,
            endTime: $endTime,
            isTrash: $isTrash,
            isTrans: $isTrans,
            isSummary: $isSummary,
            keywords: $keywords,
            serialNumber: $serialNumber,
            transcript: $transcript,
            summary: $summary,
            raw: $raw
        );
    }

    public function hasTranscript(): bool
    {
        return !empty(trim($this->transcript));
    }
}
