<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush\Exception;

use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use RuntimeException;
use Throwable;

/**
 * Thrown by a {@see PushSubscriptionProviderInterface} to refuse a test notification.
 *
 * The {@see $errorCode} is returned as {@code error} in the JSON response (e.g. {@code preference_disabled})
 * so the diagnostics panel can show a translated explanation.
 */
final class PushTestDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $statusCode = 400,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }
}
