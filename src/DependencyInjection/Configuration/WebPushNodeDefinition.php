<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection\Configuration;

use Nowo\PwaBundle\WebPush\WebPushEndpointGuard;
use Nowo\PwaBundle\WebPush\WebPushHttpClientFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * {@code nowo_pwa.web_push}: server-side Web Push sender (VAPID) and the optional test-notification endpoint.
 *
 * Independent from {@code service_worker.web_push} (which only appends the SW push/notificationclick handlers).
 */
final class WebPushNodeDefinition
{
    public static function configure(ArrayNodeDefinition $node): void
    {
        $node
            ->info('Server-side Web Push sender (minishlink/web-push ^11 + a PSR-17/PSR-18 implementation such as nyholm/psr7) and the push diagnostics test endpoint.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Register the Web Push sender service (WebPushSenderInterface). Checks minishlink/web-push and PSR-17 factories at container compile time.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('vapid')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('public_key')->info('URL-safe base64 VAPID public key (e.g. %env(VAPID_PUBLIC_KEY)%).')->defaultValue('')->end()
                        ->scalarNode('private_key')->info('URL-safe base64 VAPID private key (e.g. %env(VAPID_PRIVATE_KEY)%).')->defaultValue('')->end()
                        ->scalarNode('subject')->info('mailto: or https: contact sent to push services. Empty falls back to mailto:webmaster@localhost.')->defaultValue('')->end()
                    ->end()
                ->end()
                ->floatNode('timeout')
                    ->info('Total deadline in seconds for each push request (Symfony HttpClient max_duration). Keep max_subscriptions × timeout below PHP / proxy deadlines.')
                    ->defaultValue(WebPushHttpClientFactory::DEFAULT_TIMEOUT)
                    ->min(0.1)
                ->end()
                ->floatNode('connect_timeout')
                    ->info('Connect / inactivity deadline in seconds for each push request (Symfony HttpClient timeout). Must not exceed timeout.')
                    ->defaultValue(WebPushHttpClientFactory::DEFAULT_CONNECT_TIMEOUT)
                    ->min(0.1)
                ->end()
                ->arrayNode('allowed_endpoint_hosts')
                    ->info('Push service host suffixes the server may POST to (anti-SSRF). Defaults cover FCM, Mozilla, Apple and Windows WNS.')
                    ->scalarPrototype()->end()
                    ->defaultValue(WebPushEndpointGuard::DEFAULT_ALLOWED_HOST_SUFFIXES)
                ->end()
                ->arrayNode('test_notification')
                    ->info('POST endpoint used by the push diagnostics panel to send a test notification to the current user\'s subscriptions.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Off by default: needs a host PushSubscriptionProviderInterface, symfony/security-bundle and symfony/security-csrf.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('roles')
                            ->info('The user needs at least one of these attributes (empty list: any authenticated user).')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_USER'])
                        ->end()
                        ->scalarNode('csrf_token_id')
                            ->info('CSRF token id expected in the X-CSRF-TOKEN header (or _token body field).')
                            ->defaultValue('nowo_pwa_push')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_subscriptions')->info('Upper bound of devices notified per test request.')->defaultValue(10)->min(1)->end()
                        ->scalarNode('title')->info('Translation key or text (default domain). Null: bundle catalogue push_diagnostics.test_title.')->defaultNull()->end()
                        ->scalarNode('body')->info('Translation key or text (default domain). Null: bundle catalogue push_diagnostics.test_body.')->defaultNull()->end()
                        ->scalarNode('url')->info('URL opened on notification click. Null: service_worker.web_push_defaults.url.')->defaultNull()->end()
                        ->scalarNode('route')->info('Route name generated for the click URL (wins over url).')->defaultNull()->end()
                        ->scalarNode('tag')->defaultValue('nowo-pwa-push-test')->cannotBeEmpty()->end()
                        ->scalarNode('icon')->info('Null: service_worker.web_push_defaults.icon.')->defaultNull()->end()
                        ->scalarNode('badge')->info('Null: service_worker.web_push_defaults.badge.')->defaultNull()->end()
                        ->integerNode('ttl')->info('Seconds the push service keeps the test message when the device is offline.')->defaultValue(300)->min(0)->end()
                        ->enumNode('urgency')->values(['very-low', 'low', 'normal', 'high'])->defaultValue('high')->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => $v['connect_timeout'] > $v['timeout'])
                ->thenInvalid('nowo_pwa.web_push.connect_timeout must not exceed nowo_pwa.web_push.timeout.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $v): bool => $v['test_notification']['enabled'] && !$v['enabled'])
                ->thenInvalid('nowo_pwa.web_push.test_notification.enabled requires nowo_pwa.web_push.enabled: true.')
            ->end();
    }
}
