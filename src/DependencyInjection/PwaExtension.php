<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads PwaBundle services and framework asset package configuration.
 */
final class PwaExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $config['service_worker'] = $this->resolveServiceWorkerAppendScript($config['service_worker']);

        $container->setParameter('nowo_pwa.enabled', $config['enabled']);
        $container->setParameter('nowo_pwa.route_prefix', $config['route_prefix']);
        $container->setParameter('nowo_pwa.manifest', $config['manifest']);
        $container->setParameter('nowo_pwa.meta', $config['meta']);
        $container->setParameter('nowo_pwa.service_worker', $config['service_worker']);
        $container->setParameter('nowo_pwa.install_prompt', $config['install_prompt']);
        $container->setParameter('nowo_pwa.install_links', $config['install_links']);
        $container->setParameter('nowo_pwa.client', $config['client']);
        $container->setParameter('nowo_pwa.http', $config['http']);
        $container->setParameter('nowo_pwa.route_targeting', $config['route_targeting']);
        $container->setParameter('nowo_pwa.routes', $config['routes']);
        $container->setParameter('nowo_pwa.templates', $config['templates']);
        $container->setParameter(
            'nowo_pwa.http.strip_set_cookie_on_bootstrap',
            (bool) ($config['http']['strip_set_cookie_on_bootstrap'] ?? true),
        );
        $container->setParameter('nowo_pwa.bootstrap_paths', [
            (string) $config['routes']['manifest']['path'],
            (string) $config['routes']['service_worker']['path'],
        ]);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if ($container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')) {
            $loader->load('data_collector.yaml');
        }
    }

    /**
     * Merge kit Web Push handlers into {@code append_script} when {@code web_push} is enabled.
     *
     * @param array<string, mixed> $serviceWorker
     *
     * @return array<string, mixed>
     */
    private function resolveServiceWorkerAppendScript(array $serviceWorker): array
    {
        $parts = [];

        if (!empty($serviceWorker['web_push'])) {
            $parts[] = $this->loadWebPushAppendScript(
                \is_array($serviceWorker['web_push_defaults'] ?? null)
                    ? $serviceWorker['web_push_defaults']
                    : [],
            );
        }

        $hostAppend = trim((string) ($serviceWorker['append_script'] ?? ''));
        if ('' !== $hostAppend) {
            $parts[] = $hostAppend;
        }

        $serviceWorker['append_script'] = [] === $parts ? null : implode("\n", $parts);

        return $serviceWorker;
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function loadWebPushAppendScript(array $defaults): string
    {
        $path = __DIR__ . '/../Resources/js/web_push_sw_append.js';
        $script = file_get_contents($path);
        if (false === $script || '' === $script) {
            throw new \RuntimeException(\sprintf('Unable to read PwaBundle Web Push SW append script at "%s".', $path));
        }

        $replacements = [
            '__NOWO_PWA_PUSH_TITLE__' => $this->jsStringLiteral((string) ($defaults['title'] ?? 'Notification')),
            '__NOWO_PWA_PUSH_ICON__' => $this->jsStringLiteral((string) ($defaults['icon'] ?? '/icons/icon-192.png')),
            '__NOWO_PWA_PUSH_BADGE__' => $this->jsStringLiteral((string) ($defaults['badge'] ?? '/icons/icon-192.png')),
            '__NOWO_PWA_PUSH_URL__' => $this->jsStringLiteral((string) ($defaults['url'] ?? '/')),
            '__NOWO_PWA_PUSH_TAG__' => $this->jsStringLiteral((string) ($defaults['tag'] ?? 'nowo-pwa')),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $script);
    }

    /**
     * Encode a PHP string as a JS string literal contents (no surrounding quotes — placeholders sit inside quotes in the template).
     */
    private function jsStringLiteral(string $value): string
    {
        return str_replace(
            ['\\', "'", "\n", "\r", '</'],
            ['\\\\', "\\'", '\\n', '', '<\\/'],
            $value,
        );
    }

    /**
     * @return non-empty-string
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Registers the bundle asset package before the FrameworkExtension processes assets.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'assets' => [
                'packages' => [
                    'nowo_pwa' => [
                        'base_path' => '/bundles/pwa',
                    ],
                ],
            ],
        ]);
    }
}
