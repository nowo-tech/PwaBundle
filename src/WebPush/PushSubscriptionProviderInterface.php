<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Host bridge to the application's push subscription storage.
 *
 * Required by the test-notification endpoint ({@code nowo_pwa.web_push.test_notification.enabled}).
 * A single implementation in the host {@code src/} is auto-aliased by Symfony autowiring.
 */
interface PushSubscriptionProviderInterface
{
    /**
     * Subscriptions stored for the given user (every device / browser).
     *
     * @throws PushTestDeniedException when the host refuses the test (e.g. the user disabled push in their preferences)
     *
     * @return iterable<PushSubscriptionData>
     */
    public function findSubscriptions(UserInterface $user): iterable;

    /**
     * Called when the push service reports the subscription as gone (HTTP 404/410) or its endpoint is not an allowed push host.
     */
    public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void;
}
