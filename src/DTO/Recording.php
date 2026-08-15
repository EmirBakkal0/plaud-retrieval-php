<?php

declare(strict_types=1);

namespace Plaud\DTO;

use DateTimeImmutable;
use DateTimeInterface;

class Recording
{
    /**
     * @param string $id
     * @param string $filename
     * @param string $fullname
     * @param int $filesize In bytes
     * @param int $duration In milliseconds
     * @param int $startTime Epoch in milliseconds
     * @param int $endTime Epoch in milliseconds
     * @param bool $isTrash
     * @param bool $isTrans
     * @param bool $isSummary
     * @param string[] $keywords
     * @param string $serialNumber
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $filename,
        public readonly string $fullname,
        public readonly int $filesize,
        public readonly int $duration,
        public readonly int $startTime,
        public readonly int $endTime,
        public readonly bool $isTrash,
        public readonly bool $isTrans,
        public readonly bool $isSummary,
        public readonly array $keywords = [],
        public readonly string $serialNumber = '',
        public readonly array $raw = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['file_id'] ?? $data['id'] ?? '');
        $filename = (string) ($data['file_name'] ?? $data['filename'] ?? $id);
        $fullname = (string) ($data['file_fullname'] ?? $data['fullname'] ?? $filename);
        $filesize = (int) ($data['file_size'] ?? $data['filesize'] ?? 0);
        $duration = (int) ($data['duration'] ?? 0);
        $startTime = (int) ($data['start_time'] ?? 0);
        $endTime = (int) ($data['end_time'] ?? 0);
        $isTrash = (bool) ($data['is_trash'] ?? false);
        $isTrans = (bool) ($data['is_trans'] ?? false);
        $isSummary = (bool) ($data['is_summary'] ?? false);
        $keywords = isset($data['keywords']) && is_array($data['keywords']) ? $data['keywords'] : [];
        $serialNumber = (string) ($data['serial_number'] ?? $data['sn'] ?? '');

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
            raw: $data
        );
    }

    public function getDurationMinutes(): int
    {
        return (int) round($this->duration / 60000);
    }

    public function getDurationSeconds(): float
    {
        return $this->duration / 1000;
    }

    public function getStartDateTime(): ?DateTimeImmutable
    {
        if ($this->startTime <= 0) {
            return null;
        }
        $seconds = (int) ($this->startTime / 1000);
        return (new DateTimeImmutable())->setTimestamp($seconds);
    }

    public function getFormattedStartDate(string $format = 'Y-m-d H:i'): string
    {
        $dt = $this->getStartDateTime();
        return $dt ? $dt->format($format) : '';
    }
}
