<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use Nowo\PwaBundle\WebPush\LegacyPushSubscriptionProviderAdapter;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function is_array;
use function iterator_to_array;

final class LegacyPushSubscriptionProviderAdapterTest extends TestCase
{
    public function testDelegatesWithTheExactTokenUserForTheCurrentRecipient(): void
    {
        $alice   = new InMemoryUser('alice', null, ['ROLE_USER']);
        $legacy  = new RecordingLegacyProvider();
        $adapter = new LegacyPushSubscriptionProviderAdapter($legacy, $this->tokenStorage($alice));

        $found = $adapter->findRecipientSubscriptions('alice');
        $adapter->removeRecipientSubscription('alice', new PushSubscriptionData('https://push.example.org/alice', 'p', 'a'));

        self::assertSame(['https://push.example.org/alice'], $this->endpoints($found));
        self::assertSame([$alice, $alice], $legacy->users, 'the legacy port receives the same object as before 1.16');
        self::assertSame(['https://push.example.org/alice'], $legacy->removed);
    }

    public function testNeverAnswersForAnotherRecipient(): void
    {
        $legacy  = new RecordingLegacyProvider();
        $adapter = new LegacyPushSubscriptionProviderAdapter($legacy, $this->tokenStorage(new InMemoryUser('alice', null)));

        self::assertSame([], $adapter->findRecipientSubscriptions('bob'));
        $adapter->removeRecipientSubscription('bob', new PushSubscriptionData('https://push.example.org/bob', 'p', 'a'));

        self::assertSame([], $legacy->users);
        self::assertSame([], $legacy->removed);
    }

    public function testNeverAnswersWithoutAToken(): void
    {
        $legacy  = new RecordingLegacyProvider();
        $adapter = new LegacyPushSubscriptionProviderAdapter($legacy, $this->tokenStorage(null));

        self::assertSame([], $adapter->findRecipientSubscriptions('alice'));
        $adapter->removeRecipientSubscription('alice', new PushSubscriptionData('https://push.example.org/alice', 'p', 'a'));

        self::assertSame([], $legacy->users);
        self::assertSame([], $legacy->removed);
    }

    public function testReadsTheTokenOnEveryCall(): void
    {
        $legacy  = new RecordingLegacyProvider();
        $storage = $this->tokenStorage(new InMemoryUser('alice', null));
        $adapter = new LegacyPushSubscriptionProviderAdapter($legacy, $storage);

        self::assertSame(['https://push.example.org/alice'], $this->endpoints($adapter->findRecipientSubscriptions('alice')));

        $bob = new InMemoryUser('bob', null);
        $storage->setToken(new UsernamePasswordToken($bob, 'main'));
        self::assertSame([], $adapter->findRecipientSubscriptions('alice'));
        self::assertSame(['https://push.example.org/bob'], $this->endpoints($adapter->findRecipientSubscriptions('bob')));
        self::assertSame($bob, $legacy->users[1]);
    }

    private function tokenStorage(?UserInterface $user): TokenStorage
    {
        $storage = new TokenStorage();
        if ($user instanceof UserInterface) {
            $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        return $storage;
    }

    /**
     * @param iterable<PushSubscriptionData> $subscriptions
     *
     * @return list<string>
     */
    private function endpoints(iterable $subscriptions): array
    {
        $endpoints = [];
        foreach (is_array($subscriptions) ? $subscriptions : iterator_to_array($subscriptions, false) as $subscription) {
            $endpoints[] = $subscription->endpoint;
        }

        return $endpoints;
    }
}

/**
 * @internal
 */
final class RecordingLegacyProvider implements PushSubscriptionProviderInterface
{
    /** @var list<UserInterface> */
    public array $users = [];

    /** @var list<string> */
    public array $removed = [];

    public function findSubscriptions(UserInterface $user): iterable
    {
        $this->users[] = $user;

        return [new PushSubscriptionData('https://push.example.org/' . $user->getUserIdentifier(), 'p', 'a')];
    }

    public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void
    {
        $this->users[]   = $user;
        $this->removed[] = $subscription->endpoint;
    }
}
