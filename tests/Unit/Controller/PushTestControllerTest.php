<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Controller;

use Nowo\PwaBundle\Controller\PushTestController;
use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\WebPushDeliveryReport;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class PushTestControllerTest extends TestCase
{
    private const FCM  = 'https://fcm.googleapis.com/fcm/send/1';
    private const MOZ  = 'https://updates.push.services.mozilla.com/wpush/v2/2';
    private const EVIL = 'https://evil.example.com/3';

    /** @var list<array{PushSubscriptionData, string, array<string, mixed>}> */
    private array $sent = [];

    /** @var list<string> */
    private array $removed = [];

    public function testRequiresAuthenticatedUser(): void
    {
        $response = $this->controller(user: null)($this->request());

        $this->assertJsonError($response, 401, 'unauthenticated');
    }

    public function testRequiresOneOfTheConfiguredRoles(): void
    {
        $response = $this->controller(granted: [])($this->request());
        $this->assertJsonError($response, 403, 'forbidden');

        $response = $this->controller(granted: ['ROLE_PUSH'], config: ['roles' => ['ROLE_ADMIN', 'ROLE_PUSH']])($this->request());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEmptyRoleListOnlyNeedsAuthentication(): void
    {
        $response = $this->controller(granted: [], config: ['roles' => []])($this->request());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRejectsInvalidOrMissingCsrfToken(): void
    {
        $this->assertJsonError($this->controller()($this->request('wrong')), 403, 'invalid_csrf');
        $this->assertJsonError($this->controller()($this->request(null)), 403, 'invalid_csrf');
    }

    public function testAcceptsCsrfTokenFromBody(): void
    {
        $request = new Request([], ['_token' => 'valid'], [], [], [], ['REQUEST_METHOD' => 'POST']);

        self::assertSame(200, $this->controller()($request)->getStatusCode());
    }

    public function testRequiresConfiguredSender(): void
    {
        $this->assertJsonError($this->controller(configured: false)($this->request()), 503, 'push_not_configured');
    }

    public function testProviderCanDenyTheTest(): void
    {
        $response = $this->controller(subscriptions: new PushTestDeniedException('preference_disabled'))($this->request());

        $this->assertJsonError($response, 400, 'preference_disabled');
    }

    public function testNoSubscription(): void
    {
        $this->assertJsonError($this->controller(subscriptions: [])($this->request()), 404, 'no_subscription');
    }

    public function testSendsToEverySubscriptionWithBundleDefaults(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = [], ?string $domain = null): string => $domain . ':' . $id);

        $response = $this->controller(translator: $translator)($this->request());

        self::assertSame(200, $response->getStatusCode());
        $data = $this->decode($response);
        self::assertTrue($data['ok']);
        self::assertSame([
            ['host' => 'fcm.googleapis.com', 'ok' => true, 'status' => 201, 'reason' => null, 'expired' => false],
        ], $data['results']);

        self::assertCount(1, $this->sent);
        [, $payload, $options] = $this->sent[0];
        self::assertSame(['TTL' => 300, 'urgency' => 'high'], $options);
        self::assertSame([
            'event' => 'push.test',
            'title' => 'NowoPwaBundle:push_diagnostics.test_title',
            'body'  => 'NowoPwaBundle:push_diagnostics.test_body',
            'tag'   => 'nowo-pwa-push-test',
        ], json_decode($payload, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testEnglishFallbackWithoutTranslatorAndHostOverrides(): void
    {
        $this->controller()($this->request());
        $payload = json_decode($this->sent[0][1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Test notification', $payload['title']);
        self::assertSame('If you can read this, push notifications work on this device.', $payload['body']);

        $this->sent = [];
        $this->controller(config: ['title' => 'Host title', 'body' => 'Host body', 'url' => '/inbox', 'icon' => '/i.png', 'badge' => '/b.png'])($this->request());
        $payload = json_decode($this->sent[0][1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Host title', $payload['title']);
        self::assertSame('Host body', $payload['body']);
        self::assertSame('/inbox', $payload['url']);
        self::assertSame('/i.png', $payload['icon']);
        self::assertSame('/b.png', $payload['badge']);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = [], ?string $domain = null): string => ($domain ?? 'messages') . ':' . $id);
        $this->sent = [];
        $this->controller(config: ['title' => 'app.push.title', 'route' => 'app_inbox', 'url' => '/ignored'], translator: $translator)($this->request());
        $payload = json_decode($this->sent[0][1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('messages:app.push.title', $payload['title']);
        self::assertSame('/generated/app_inbox', $payload['url']);
    }

    public function testReportsPerDeviceFailuresAndRemovesExpiredSubscriptions(): void
    {
        $subscriptions = [
            new PushSubscriptionData(self::FCM, 'p', 'a'),
            new PushSubscriptionData(self::MOZ, 'p', 'a'),
            new PushSubscriptionData(self::EVIL, 'p', 'a'),
            new PushSubscriptionData('https://fcm.googleapis.com/fcm/send/boom', 'p', 'a'),
        ];
        $reports = [
            self::FCM => new WebPushDeliveryReport(false, 403, 'Forbidden'),
            self::MOZ => new WebPushDeliveryReport(false, 410, 'Gone', true),
        ];

        $response = $this->controller(subscriptions: $subscriptions, reports: $reports)($this->request());

        self::assertSame(502, $response->getStatusCode());
        $data = $this->decode($response);
        self::assertFalse($data['ok']);
        self::assertSame([
            ['host' => 'fcm.googleapis.com', 'ok' => false, 'status' => 403, 'reason' => 'Forbidden', 'expired' => false],
            ['host' => 'updates.push.services.mozilla.com', 'ok' => false, 'status' => 410, 'reason' => 'Gone', 'expired' => true],
            ['host' => 'evil.example.com', 'ok' => false, 'status' => null, 'reason' => 'not allowed', 'expired' => true],
            ['host' => 'fcm.googleapis.com', 'ok' => false, 'status' => null, 'reason' => 'transport down', 'expired' => false],
        ], $data['results']);
        self::assertSame([self::MOZ, self::EVIL], $this->removed);
    }

    public function testCapsTheNumberOfDevices(): void
    {
        $subscriptions = (static function (): iterable {
            yield new PushSubscriptionData(self::FCM, 'p', 'a');
            yield new PushSubscriptionData(self::FCM . 'b', 'p', 'a');
            yield new PushSubscriptionData(self::FCM . 'c', 'p', 'a');
        })();

        $this->controller(subscriptions: $subscriptions, config: ['max_subscriptions' => 2])($this->request());

        self::assertCount(2, $this->sent);
    }

    public function testMissingDependencyAbortsWithClientError(): void
    {
        $response = $this->controller(reports: [self::FCM => new WebPushDependencyException('composer require nyholm/psr7')])($this->request());

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'client_error', 'reason' => 'composer require nyholm/psr7'], $this->decode($response));
    }

    /**
     * @param list<string> $granted
     * @param iterable<PushSubscriptionData>|PushTestDeniedException|null $subscriptions
     * @param array<string, WebPushDeliveryReport|WebPushDependencyException> $reports
     * @param array<string, mixed> $config
     */
    private function controller(
        ?UserInterface $user = new InMemoryUser('ada', null, ['ROLE_USER']),
        array $granted = ['ROLE_USER'],
        bool $configured = true,
        iterable|PushTestDeniedException|null $subscriptions = null,
        array $reports = [],
        array $config = [],
        ?TranslatorInterface $translator = null,
    ): PushTestController {
        $tokenStorage = new TokenStorage();
        if ($user instanceof UserInterface) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => in_array($attribute, $granted, true));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => $token->getId() === 'nowo_pwa_push' && $token->getValue() === 'valid');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $name): string => '/generated/' . $name);

        $sender = new class($configured, $reports, $this->sent) implements WebPushSenderInterface {
            /**
             * @param array<string, WebPushDeliveryReport|WebPushDependencyException> $reports
             * @param list<array{PushSubscriptionData, string, array<string, mixed>}> $sent
             */
            public function __construct(private readonly bool $configured, private readonly array $reports, private array &$sent)
            {
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function getPublicKey(): ?string
            {
                return null;
            }

            public function send(PushSubscriptionData $subscription, string $payload, array $options = []): WebPushDeliveryReport
            {
                $this->sent[] = [$subscription, $payload, $options];
                if (str_contains($subscription->endpoint, 'evil')) {
                    throw new InvalidPushEndpointException('not allowed');
                }
                if (str_contains($subscription->endpoint, 'boom')) {
                    throw new RuntimeException('transport down');
                }
                $report = $this->reports[$subscription->endpoint] ?? new WebPushDeliveryReport(true, 201);
                if ($report instanceof WebPushDependencyException) {
                    throw $report;
                }

                return $report;
            }
        };

        $provider = new class($subscriptions ?? [new PushSubscriptionData(self::FCM, 'p', 'a')], $this->removed) implements PushSubscriptionProviderInterface {
            /**
             * @param iterable<PushSubscriptionData>|PushTestDeniedException $subscriptions
             * @param list<string> $removed
             */
            public function __construct(private readonly iterable|PushTestDeniedException $subscriptions, private array &$removed)
            {
            }

            public function findSubscriptions(UserInterface $user): iterable
            {
                if ($this->subscriptions instanceof PushTestDeniedException) {
                    throw $this->subscriptions;
                }

                return $this->subscriptions;
            }

            public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void
            {
                $this->removed[] = $subscription->endpoint;
            }
        };

        return new PushTestController(
            $sender,
            $provider,
            $tokenStorage,
            $authorizationChecker,
            $csrf,
            $urlGenerator,
            $config + [
                'roles'             => ['ROLE_USER'],
                'csrf_token_id'     => 'nowo_pwa_push',
                'max_subscriptions' => 10,
                'title'             => null,
                'body'              => null,
                'url'               => null,
                'route'             => null,
                'tag'               => 'nowo-pwa-push-test',
                'icon'              => null,
                'badge'             => null,
                'ttl'               => 300,
                'urgency'           => 'high',
            ],
            $translator,
        );
    }

    private function request(?string $csrf = 'valid'): Request
    {
        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);
        if ($csrf !== null) {
            $request->headers->set(PushTestController::CSRF_HEADER, $csrf);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    private function assertJsonError(JsonResponse $response, int $status, string $code): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => $code], $this->decode($response));
        self::assertSame([], $this->sent);
    }
}
