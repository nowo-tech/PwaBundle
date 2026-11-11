<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function trigger_deprecation;

/**
 * Bridges a host {@see PushSubscriptionProviderInterface} (deprecated, keyed by the security user) to
 * {@see PushRecipientSubscriptionProviderInterface} until 2.0.
 *
 * The legacy port needs the user object, which an identifier alone cannot rebuild safely. The adapter therefore only
 * answers for the user of the current security token, whose {@code getUserIdentifier()} must equal the requested
 * identifier, and passes that exact object (the same one the controller passed before 1.16). For any other
 * identifier it finds nothing and removes nothing, so a push never reaches another user's devices.
 *
 * Stateless: the token is read on every call (FrankenPHP worker mode, kernel reset false).
 *
 * @internal
 *
 * @since 1.16.0
 */
final readonly class LegacyPushSubscriptionProviderAdapter implements PushRecipientSubscriptionProviderInterface
{
    public function __construct(
        private PushSubscriptionProviderInterface $legacy,
        private TokenStorageInterface $tokenStorage,
    ) {
        trigger_deprecation(
            'nowo-tech/pwa-bundle',
            '1.16.0',
            'Implementing "%s" is deprecated, implement "%s" instead (removed in 2.0).',
            PushSubscriptionProviderInterface::class,
            PushRecipientSubscriptionProviderInterface::class,
        );
    }

    public function findRecipientSubscriptions(string $userIdentifier): iterable
    {
        $user = $this->currentUser($userIdentifier);

        return $user instanceof UserInterface ? $this->legacy->findSubscriptions($user) : [];
    }

    public function removeRecipientSubscription(string $userIdentifier, PushSubscriptionData $subscription): void
    {
        $user = $this->currentUser($userIdentifier);
        if ($user instanceof UserInterface) {
            // @igor-ignore - Delegates deletion to the host storage (database), no state kept on the provider.
            $this->legacy->removeSubscription($user, $subscription);
        }
    }

    /** The current token user when it is the requested recipient, null otherwise. */
    private function currentUser(string $userIdentifier): ?UserInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof UserInterface && $user->getUserIdentifier() === $userIdentifier ? $user : null;
    }
}
