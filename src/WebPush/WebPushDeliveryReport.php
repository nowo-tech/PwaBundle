<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

/**
 * Push service answer for one delivery attempt.
 */
final readonly class WebPushDeliveryReport
{
    public function __construct(
        public bool $success,
        public ?int $statusCode = null,
        public ?string $reason = null,
        public bool $expired = false,
    ) {
    }
}
