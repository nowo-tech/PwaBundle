<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Controller;

use Nowo\PwaBundle\PwaBundle;
use Nowo\PwaBundle\WebPush\Exception\InvalidPushEndpointException;
use Nowo\PwaBundle\WebPush\Exception\PushTestDeniedException;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_filter;
use function count;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * POST endpoint behind the push diagnostics "Send test notification" button.
 *
 * Sends one test notification to every stored subscription of the current user and reports the
 * push service answer per device. Registered only when {@code nowo_pwa.web_push.test_notification.enabled}.
 *
 * JSON: {@code {ok: bool, error?: string, reason?: string, results?: list<{host, ok, status, reason, expired}>}}
 */
final readonly class PushTestController
{
    public const CSRF_HEADER = 'X-CSRF-TOKEN';

    /**
     * @param array{
     *     roles: list<string>,
     *     csrf_token_id: string,
     *     max_subscriptions: int,
     *     title: ?string,
     *     body: ?string,
     *     url: ?string,
     *     route: ?string,
     *     tag: string,
     *     icon: ?string,
     *     badge: ?string,
     *     ttl: int,
     *     urgency: string
     * } $config
     */
    public function __construct(
        private WebPushSenderInterface $sender,
        private PushSubscriptionProviderInterface $subscriptionProvider,
        private TokenStorageInterface $tokenStorage,
        private AuthorizationCheckerInterface $authorizationChecker,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private array $config,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return $this->error('unauthenticated', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isGranted()) {
            return $this->error('forbidden', Response::HTTP_FORBIDDEN);
        }

        $token = $request->headers->get(self::CSRF_HEADER) ?? $request->request->get('_token');
        if (!is_string($token) || $token === '' || !$this->csrfTokenManager->isTokenValid(new CsrfToken($this->config['csrf_token_id'], $token))) {
            return $this->error('invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        if (!$this->sender->isConfigured()) {
            return $this->error('push_not_configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $subscriptions = $this->collectSubscriptions($user);
        } catch (PushTestDeniedException $e) {
            return $this->error($e->errorCode, $e->statusCode);
        }

        if ($subscriptions === []) {
            return $this->error('no_subscription', Response::HTTP_NOT_FOUND);
        }

        $payload = $this->buildPayload();
        $options = ['TTL' => $this->config['ttl'], 'urgency' => $this->config['urgency']];
        $results = [];

        foreach ($subscriptions as $subscription) {
            $host = $subscription->endpointHost();
            try {
                $report    = $this->sender->send($subscription, $payload, $options);
                $results[] = ['host' => $host, 'ok' => $report->success, 'status' => $report->statusCode, 'reason' => $report->reason, 'expired' => $report->expired];
                if ($report->expired) {
                    // @igor-ignore - Delegates deletion to the host storage (database), no state kept on the provider.
                    $this->subscriptionProvider->removeSubscription($user, $subscription);
                }
            } catch (InvalidPushEndpointException $e) {
                $results[] = ['host' => $host, 'ok' => false, 'status' => null, 'reason' => $e->getMessage(), 'expired' => true];
                // @igor-ignore - Delegates deletion to the host storage (database), no state kept on the provider.
                $this->subscriptionProvider->removeSubscription($user, $subscription);
            } catch (WebPushDependencyException $e) {
                return new JsonResponse(['ok' => false, 'error' => 'client_error', 'reason' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            } catch (Throwable $e) {
                $results[] = ['host' => $host, 'ok' => false, 'status' => null, 'reason' => $e->getMessage(), 'expired' => false];
            }
        }

        $ok = array_filter($results, static fn (array $row): bool => $row['ok']) !== [];

        return new JsonResponse(['ok' => $ok, 'results' => $results], $ok ? Response::HTTP_OK : Response::HTTP_BAD_GATEWAY);
    }

    private function isGranted(): bool
    {
        if ($this->config['roles'] === []) {
            return true;
        }

        foreach ($this->config['roles'] as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<PushSubscriptionData>
     */
    private function collectSubscriptions(UserInterface $user): array
    {
        $subscriptions = [];
        foreach ($this->subscriptionProvider->findSubscriptions($user) as $subscription) {
            $subscriptions[] = $subscription;
            if (count($subscriptions) >= $this->config['max_subscriptions']) {
                break;
            }
        }

        return $subscriptions;
    }

    private function buildPayload(): string
    {
        $payload = [
            'event' => 'push.test',
            'title' => $this->translate($this->config['title'], 'push_diagnostics.test_title', 'Test notification'),
            'body'  => $this->translate($this->config['body'], 'push_diagnostics.test_body', 'If you can read this, push notifications work on this device.'),
            'tag'   => $this->config['tag'],
        ];

        $url = $this->config['route'] !== null && $this->config['route'] !== ''
            ? $this->urlGenerator->generate($this->config['route'])
            : $this->config['url'];
        if ($url !== null && $url !== '') {
            $payload['url'] = $url;
        }
        foreach (['icon', 'badge'] as $key) {
            if ($this->config[$key] !== null && $this->config[$key] !== '') {
                $payload[$key] = $this->config[$key];
            }
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Host value (translation key or literal, default domain) when configured, bundle catalogue otherwise.
     */
    private function translate(?string $hostValue, string $bundleKey, string $fallback): string
    {
        if ($hostValue !== null && $hostValue !== '') {
            return $this->translator?->trans($hostValue) ?? $hostValue;
        }

        return $this->translator?->trans($bundleKey, [], PwaBundle::TRANSLATION_DOMAIN) ?? $fallback;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'error' => $code], $status);
    }
}
