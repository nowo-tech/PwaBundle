<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;

/**
 * Host port to the application's push subscription storage, keyed by the recipient's user identifier.
 *
 * The identifier is the string the security layer authenticates the user with (Symfony
 * {@code UserInterface::getUserIdentifier()} in a standard host), so a domain repository can look subscriptions up by
 * its own user id without any framework type. Required by the test-notification endpoint
 * ({@code nowo_pwa.web_push.test_notification.enabled}); a single implementation in the host {@code src/} is
 * auto-aliased by Symfony autowiring and is preferred over the deprecated {@see PushSubscriptionProviderInterface}.
 *
 * The method names differ from the deprecated port, so one class can implement both while it migrates.
 *
 * @since 1.16.0
 */
interface PushRecipientSubscriptionProviderInterface
{
    /**
     * Subscriptions stored for the recipient (every device / browser). Return only that recipient's subscriptions.
     *
     * @throws PushTestDeniedException when the host refuses the test (e.g. the user disabled push in their preferences)
     *
     * @return iterable<PushSubscriptionData>
     */
    public function findRecipientSubscriptions(string $userIdentifier): iterable;

    /**
     * Called when the push service reports the subscription as gone (HTTP 404/410) or its endpoint is not an allowed push host.
     */
    public function removeRecipientSubscription(string $userIdentifier, PushSubscriptionData $subscription): void;
}
