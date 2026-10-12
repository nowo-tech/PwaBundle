<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Twig;

use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes {@code nowo_pwa_web_push()} for the push diagnostics partial (VAPID public key, test URL, CSRF token).
 */
final class PwaWebPushTwigExtension extends AbstractExtension
{
    /**
     * @param array{enabled: bool, test_enabled: bool, test_route: string, csrf_token_id: string} $config
     */
    public function __construct(
        private readonly array $config,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?WebPushSenderInterface $sender = null,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pwa_web_push', $this->webPush(...)),
        ];
    }

    /**
     * @return array{enabled: bool, configured: bool, vapid_public_key: ?string, test_url: ?string, csrf_token_id: string, csrf_token: ?string}
     */
    public function webPush(): array
    {
        $sender      = $this->config['enabled'] ? $this->sender : null;
        $testEnabled = $sender instanceof WebPushSenderInterface && $this->config['test_enabled'];

        return [
            'enabled'          => $sender instanceof WebPushSenderInterface,
            'configured'       => $sender?->isConfigured() ?? false,
            'vapid_public_key' => $sender?->getPublicKey(),
            'test_url'         => $testEnabled ? $this->urlGenerator->generate($this->config['test_route']) : null,
            'csrf_token_id'    => $this->config['csrf_token_id'],
            'csrf_token'       => $testEnabled && $this->csrfTokenManager instanceof CsrfTokenManagerInterface
                ? $this->csrfTokenManager->getToken($this->config['csrf_token_id'])->getValue()
                : null,
        ];
    }
}
