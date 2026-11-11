<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Compiler;

use Nowo\PwaBundle\Controller\PushTestController;
use Nowo\PwaBundle\WebPush\LegacyPushSubscriptionProviderAdapter;
use Nowo\PwaBundle\WebPush\PushRecipientSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Picks the subscription port of the push test endpoint (since 1.16).
 *
 * 1. The host's {@see PushRecipientSubscriptionProviderInterface} (identifier-keyed), preferred even when the same
 *    class also implements the deprecated port.
 * 2. Otherwise the host's deprecated {@see PushSubscriptionProviderInterface}, bridged by
 *    {@see LegacyPushSubscriptionProviderAdapter}.
 * 3. Otherwise the new port is referenced anyway, so the container build fails naming it (as before 1.16 for the old one).
 */
final class PushSubscriptionProviderPass implements CompilerPassInterface
{
    public const ADAPTER_ID = 'nowo_pwa.web_push.legacy_subscription_provider_adapter';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PushTestController::class)) {
            return;
        }

        $provider = new Reference(PushRecipientSubscriptionProviderInterface::class);
        if (!$container->has(PushRecipientSubscriptionProviderInterface::class) && $container->has(PushSubscriptionProviderInterface::class)) {
            $container->register(self::ADAPTER_ID, LegacyPushSubscriptionProviderAdapter::class)
                ->setArguments([
                    '$legacy'       => new Reference(PushSubscriptionProviderInterface::class),
                    '$tokenStorage' => new Reference('security.token_storage'),
                ]);
            $provider = new Reference(self::ADAPTER_ID);
        }

        $container->getDefinition(PushTestController::class)->setArgument('$subscriptionProvider', $provider);
    }
}
