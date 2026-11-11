<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration\Ddd;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\PwaBundle\PwaBundle;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\DoctrinePushRecipientSubscriptionProvider;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\FixedCsrfTokenManager;
use Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure\RecordingWebPushSender;
use Nowo\PwaBundle\WebPush\PushRecipientSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * DDD / hexagonal host for the push test endpoint (REQ-ARCH-001):
 *
 * - push subscriptions live in the host's own XML-mapped entity on a dedicated "push" entity manager (the default
 *   entity manager maps nothing of it), and reference the user by an id string (no user entity, no foreign key);
 * - the host implements only {@see PushRecipientSubscriptionProviderInterface} (no Symfony security type);
 * - users come from an in-memory provider (alice, bob).
 *
 * With `$servicesResetter` false the `services_resetter` service is removed, so nothing resets services between
 * requests (FrankenPHP worker mode with kernel reset false, REQ-RUNTIME-002).
 */
final class DddPushTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $servicesResetter = true)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new NowoUiKitBundle();
        yield new PwaBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-pwa-ddd-kernel/' . ($this->servicesResetter ? 'reset' : 'no-reset') . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-pwa-ddd-kernel/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        if ($this->servicesResetter) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->removeDefinition('services_resetter');
            }
        });
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'not-a-real-secret',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router'                => ['utf8' => true],
            'php_errors'            => ['log' => false],
        ]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => [
                'alice' => ['roles' => ['ROLE_USER']],
                'bob'   => ['roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
        ]);
        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'push'    => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default' => ['connection' => 'default'],
                    'push'    => [
                        'connection' => 'push',
                        'mappings'   => ['HostPush' => [
                            'type'      => 'xml',
                            'is_bundle' => false,
                            'dir'       => __DIR__ . '/config/doctrine',
                            'prefix'    => 'Nowo\PwaBundle\Tests\Integration\Ddd\Domain',
                            'alias'     => 'HostPush',
                        ]],
                    ],
                ],
            ],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('nowo_pwa', [
            'web_push' => [
                'enabled'           => true,
                'test_notification' => ['enabled' => true, 'roles' => ['ROLE_USER']],
            ],
        ]);

        $services = $container->services();
        $services->set(RecordingWebPushSender::class)->public();
        $services->alias(WebPushSenderInterface::class, RecordingWebPushSender::class)->public();
        $services->set(DoctrinePushRecipientSubscriptionProvider::class)
            ->args([service('doctrine.orm.push_entity_manager')]);
        $services->alias(PushRecipientSubscriptionProviderInterface::class, DoctrinePushRecipientSubscriptionProvider::class);
        $services->set('security.csrf.token_manager', FixedCsrfTokenManager::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_pwa');
    }
}
