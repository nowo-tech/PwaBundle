<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\PwaBundle\Controller\PushTestController;
use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Nowo\PwaBundle\WebPush\PushRecipientSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\WebPushDeliveryReport;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_values;
use function str_contains;

/**
 * Since 1.16 the push test endpoint looks subscriptions up by user identifier. These tests run the same scenarios
 * through the deprecated {@see PushSubscriptionProviderInterface} (bridged by the adapter) and through
 * {@see PushRecipientSubscriptionProviderInterface}, over the same host storage, and require identical responses,
 * identical recipients (sent endpoints) and identical removals; a push never reaches another user's devices.
 */
final class PushRecipientPortEquivalenceTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, list<string>|PushTestDeniedException>, ?string, int}>
     */
    public static function scenarios(): iterable
    {
        $store = [
            'alice' => ['https://fcm.googleapis.com/fcm/send/alice-1', 'https://updates.push.services.mozilla.com/wpush/v2/alice-gone', 'https://evil.example.com/alice'],
            'bob'   => ['https://fcm.googleapis.com/fcm/send/bob-1'],
            'carol' => [],
        ];

        yield 'alice: sent, expired and refused endpoints' => [$store, 'alice', 10];
        yield 'alice: capped' => [$store, 'alice', 1];
        yield 'bob: one device' => [$store, 'bob', 10];
        yield 'carol: no subscription' => [$store, 'carol', 10];
        yield 'guest: unauthenticated' => [$store, null, 10];
        yield 'dave: host refuses' => [$store + ['dave' => new PushTestDeniedException('preference_disabled')], 'dave', 10];
    }

    /**
     * @param array<string, list<string>|PushTestDeniedException> $store
     */
    #[DataProvider('scenarios')]
    public function testBothPortsReachExactlyTheSameRecipients(array $store, ?string $user, int $max): void
    {
        $legacy = $this->exercise(new LegacyStoreProvider($store), [$user], $max);
        $port   = $this->exercise(new RecipientStoreProvider($store), [$user], $max);

        self::assertSame($legacy, $port);
        foreach ($port['sent'][0] as $endpoint) {
            self::assertStringContainsString((string) $user, $endpoint, 'only the current user\'s devices');
        }
    }

    public function testAlternatingUsersOnOneControllerNeverMixRecipients(): void
    {
        $store = [
            'alice' => ['https://fcm.googleapis.com/fcm/send/alice-1', 'https://fcm.googleapis.com/fcm/send/alice-2'],
            'bob'   => ['https://fcm.googleapis.com/fcm/send/bob-1'],
        ];
        $users = [];
        for ($i = 0; $i < 200; ++$i) {
            $users[] = ['alice', 'bob', null][$i % 3];
        }

        $legacy = $this->exercise(new LegacyStoreProvider($store), $users, 10);
        $port   = $this->exercise(new RecipientStoreProvider($store), $users, 10);

        self::assertSame($legacy, $port);
        foreach ($users as $i => $user) {
            self::assertSame($user === null ? [] : $store[$user], $port['sent'][$i], 'request ' . $i);
        }
    }

    /**
     * Runs one controller (one shared instance, as in a kept-alive worker) for each user in turn.
     *
     * @param list<?string> $users
     *
     * @return array{responses: list<array{int, string}>, sent: list<list<string>>, removed: list<array{string, string}>}
     */
    private function exercise(PushSubscriptionProviderInterface|PushRecipientSubscriptionProviderInterface $provider, array $users, int $max): array
    {
        $tokenStorage = new TokenStorage();
        $sender       = new EquivalenceSender();

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => $token->getValue() === 'valid');

        $controller = new PushTestController($sender, $provider, $tokenStorage, $authorizationChecker, $csrf, $this->createStub(UrlGeneratorInterface::class), [
            'roles' => ['ROLE_USER'], 'csrf_token_id' => 'nowo_pwa_push', 'max_subscriptions' => $max, 'title' => 'T', 'body' => 'B',
            'url'   => null, 'route' => null, 'tag' => 'nowo-pwa-push-test', 'icon' => null, 'badge' => null, 'ttl' => 60, 'urgency' => 'high',
        ]);

        $result = ['responses' => [], 'sent' => [], 'removed' => []];
        foreach ($users as $user) {
            $tokenStorage->setToken(null);
            if ($user !== null) {
                $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($user, null, ['ROLE_USER']), 'main', ['ROLE_USER']));
            }

            $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);
            $request->headers->set(PushTestController::CSRF_HEADER, 'valid');
            $response              = $controller($request);
            $result['responses'][] = [$response->getStatusCode(), (string) $response->getContent()];
            $result['sent'][]      = $sender->take();
        }
        $result['removed'] = $provider->removed;

        return $result;
    }
}

/**
 * @internal
 */
final class EquivalenceSender implements WebPushSenderInterface
{
    /** @var list<string> */
    private array $sent = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function getPublicKey(): ?string
    {
        return null;
    }

    public function send(PushSubscriptionData $subscription, string $payload, array $options = []): WebPushDeliveryReport
    {
        $this->sent[] = $subscription->endpoint;
        if (str_contains($subscription->endpoint, 'evil')) {
            throw new InvalidPushEndpointException('not allowed');
        }

        return str_contains($subscription->endpoint, 'gone') ? new WebPushDeliveryReport(false, 410, 'Gone', true) : new WebPushDeliveryReport(true, 201);
    }

    /** @return list<string> */
    public function take(): array
    {
        $sent       = $this->sent;
        $this->sent = [];

        return $sent;
    }
}

/**
 * Host storage keyed by user identifier, the way a standard host implements the deprecated port.
 *
 * @internal
 */
final class LegacyStoreProvider implements PushSubscriptionProviderInterface
{
    /** @var list<array{string, string}> */
    public array $removed = [];

    /**
     * @param array<string, list<string>|PushTestDeniedException> $store
     */
    public function __construct(private readonly array $store)
    {
    }

    public function findSubscriptions(UserInterface $user): iterable
    {
        return StoreLookup::find($this->store, $user->getUserIdentifier());
    }

    public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void
    {
        $this->removed[] = [$user->getUserIdentifier(), $subscription->endpoint];
    }
}

/**
 * The same host storage behind the identifier-keyed port.
 *
 * @internal
 */
final class RecipientStoreProvider implements PushRecipientSubscriptionProviderInterface
{
    /** @var list<array{string, string}> */
    public array $removed = [];

    /**
     * @param array<string, list<string>|PushTestDeniedException> $store
     */
    public function __construct(private readonly array $store)
    {
    }

    public function findRecipientSubscriptions(string $userIdentifier): iterable
    {
        return StoreLookup::find($this->store, $userIdentifier);
    }

    public function removeRecipientSubscription(string $userIdentifier, PushSubscriptionData $subscription): void
    {
        $this->removed[] = [$userIdentifier, $subscription->endpoint];
    }
}

/**
 * @internal
 */
final class StoreLookup
{
    /**
     * @param array<string, list<string>|PushTestDeniedException> $store
     *
     * @return list<PushSubscriptionData>
     */
    public static function find(array $store, string $identifier): array
    {
        $entry = $store[$identifier] ?? [];
        if ($entry instanceof PushTestDeniedException) {
            throw $entry;
        }

        $subscriptions = [];
        foreach (array_values($entry) as $endpoint) {
            $subscriptions[] = new PushSubscriptionData($endpoint, 'p', 'a');
        }

        return $subscriptions;
    }
}
