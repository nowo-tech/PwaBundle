<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration\Ddd\Domain;

/**
 * Plain-PHP domain entity of a DDD host (XML-mapped, no bundle base class): a browser subscription that references
 * its owner by an id string only (no user entity, no foreign key).
 */
class StoredPushSubscription
{
    private ?int $id = null;

    public function __construct(
        private readonly string $recipientId,
        private readonly string $endpoint,
        private readonly string $p256dh,
        private readonly string $authToken,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function recipientId(): string
    {
        return $this->recipientId;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function p256dh(): string
    {
        return $this->p256dh;
    }

    public function authToken(): string
    {
        return $this->authToken;
    }
}
