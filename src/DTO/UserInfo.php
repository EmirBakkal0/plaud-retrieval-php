<?php

declare(strict_types=1);

namespace Plaud\DTO;

class UserInfo
{
    /**
     * @param string $id
     * @param string $nickname
     * @param string $email
     * @param string $country
     * @param string $membershipType
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nickname,
        public readonly string $email,
        public readonly string $country,
        public readonly string $membershipType,
        public readonly array $raw = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $user = isset($data['data_user']) && is_array($data['data_user'])
            ? $data['data_user']
            : (isset($data['data']) && is_array($data['data']) ? $data['data'] : $data);

        $state = isset($data['data_state']) && is_array($data['data_state']) ? $data['data_state'] : [];
        $membershipType = (string) ($state['membership_type'] ?? $user['membership_type'] ?? 'unknown');

        return new self(
            id: (string) ($user['id'] ?? $user['user_id'] ?? ''),
            nickname: (string) ($user['nickname'] ?? $user['name'] ?? ''),
            email: (string) ($user['email'] ?? ''),
            country: (string) ($user['country'] ?? ''),
            membershipType: $membershipType,
            raw: $data
        );
    }
}
