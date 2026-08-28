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
