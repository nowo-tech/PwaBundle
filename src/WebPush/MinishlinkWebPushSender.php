<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Http\Discovery\Exception as DiscoveryException;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Default {@see WebPushSenderInterface} backed by minishlink/web-push (^11, PSR-18/PSR-17 based).
 *
 * Stateless: a client is built per {@see send()} call (no per-request state survives in FrankenPHP workers).
 * PSR services are injected when the host container provides them and auto-discovered otherwise.
 * Every delivery runs with the bundle connect/total deadlines resolved by {@see WebPushHttpClientFactory}
 * (nowo_pwa.web_push.connect_timeout / timeout, REQ-RUNTIME-001).
 */
final readonly class MinishlinkWebPushSender implements WebPushSenderInterface
{
    public const DEFAULT_SUBJECT = 'mailto:webmaster@localhost';

    public function __construct(
        private string $publicKey,
        #[SensitiveParameter]
        private string $privateKey,
        private string $subject = '',
        private WebPushEndpointGuard $endpointGuard = new WebPushEndpointGuard(),
        private ?ClientInterface $httpClient = null,
        private ?RequestFactoryInterface $requestFactory = null,
        private ?StreamFactoryInterface $streamFactory = null,
        private WebPushRequirements $requirements = new WebPushRequirements(),
        private ?LoggerInterface $logger = null,
        private WebPushHttpClientFactory $httpClientFactory = new WebPushHttpClientFactory(),
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->publicKey !== '' && $this->privateKey !== '';
    }

    public function getPublicKey(): ?string
    {
        return $this->isConfigured() ? $this->publicKey : null;
    }

    public function send(PushSubscriptionData $subscription, string $payload, array $options = []): WebPushDeliveryReport
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Web Push VAPID keys are not configured (nowo_pwa.web_push.vapid.public_key / private_key).');
        }

        $this->endpointGuard->assertSafeEndpoint($subscription->endpoint);

        $client = $this->createClient();

        try {
            $report = $client->sendOneNotification(
                Subscription::create([
                    'endpoint'        => $subscription->endpoint,
                    'keys'            => ['p256dh' => $subscription->publicKey, 'auth' => $subscription->authToken],
                    'contentEncoding' => $subscription->contentEncoding,
                ]),
                $payload,
                $options,
            );

            $success  = $report->isSuccess();
            $delivery = new WebPushDeliveryReport(
                $success,
                $report->getResponse()?->getStatusCode(),
                $success ? null : $report->getReason(),
                $report->isSubscriptionExpired(),
            );
            // Release the response inside the try: a lazily streamed body that hit the deadline throws on destruct.
            unset($report);
        } catch (TransportExceptionInterface $e) {
            return new WebPushDeliveryReport(false, null, $e->getMessage(), false);
        }

        return $delivery;
    }

    /**
     * @throws WebPushDependencyException
     */
    private function createClient(): WebPush
    {
        $this->requirements->assertLibraryInstalled();

        try {
            return new WebPush(
                [
                    'VAPID' => [
                        'subject'    => $this->subject !== '' ? $this->subject : self::DEFAULT_SUBJECT,
                        'publicKey'  => $this->publicKey,
                        'privateKey' => $this->privateKey,
                    ],
                ],
                [],
                $this->httpClientFactory->create($this->httpClient, $this->streamFactory),
                $this->requestFactory,
                $this->streamFactory,
                null,
                $this->logger,
            );
        } catch (DiscoveryException $e) {
            throw new WebPushDependencyException(WebPushRequirements::missingHttpImplementationMessage(), 0, $e);
        }
    }
}
