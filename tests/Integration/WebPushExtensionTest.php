<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Minishlink\WebPush\WebPush;
use Nowo\PwaBundle\Controller\PushTestController;
use Nowo\PwaBundle\DependencyInjection\Compiler\PushSubscriptionProviderPass;
use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Nowo\PwaBundle\WebPush\LegacyPushSubscriptionProviderAdapter;
use Nowo\PwaBundle\WebPush\MinishlinkWebPushSender;
use Nowo\PwaBundle\WebPush\PushRecipientSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\PushSubscriptionData;
use Nowo\PwaBundle\WebPush\PushSubscriptionProviderInterface;
use Nowo\PwaBundle\WebPush\WebPushEndpointGuard;
use Nowo\PwaBundle\WebPush\WebPushHttpClientFactory;
use Nowo\PwaBundle\WebPush\WebPushRequirements;
use Nowo\PwaBundle\WebPush\WebPushSenderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

use function in_array;

final class WebPushExtensionTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        $container = $this->load([]);

        self::assertFalse($this->isRegistered($container, MinishlinkWebPushSender::class));
        self::assertFalse($container->hasAlias(WebPushSenderInterface::class));
        self::assertFalse($this->isRegistered($container, PushTestController::class));
        self::assertFalse($container->getParameter('nowo_pwa.web_push.test_notification.enabled'));
        self::assertSame(
            ['enabled' => false, 'test_enabled' => false, 'test_route' => 'nowo_pwa_push_test', 'csrf_token_id' => 'nowo_pwa_push'],
            $container->getParameter('nowo_pwa.web_push.client'),
        );
    }

    public function testRegistersSenderWithoutTestEndpoint(): void
    {
        $container = $this->load(['web_push' => ['enabled' => true, 'vapid' => ['public_key' => 'pub', 'private_key' => 'priv'], 'allowed_endpoint_hosts' => ['push.example.org']]]);

        self::assertTrue($container->hasDefinition(MinishlinkWebPushSender::class));
        self::assertSame(MinishlinkWebPushSender::class, (string) $container->getAlias(WebPushSenderInterface::class));
        self::assertSame(['push.example.org'], $container->getDefinition(WebPushEndpointGuard::class)->getArgument('$allowedHostSuffixes'));
        self::assertFalse($this->isRegistered($container, PushTestController::class));
        $factory = $container->getDefinition(WebPushHttpClientFactory::class);
        self::assertSame(WebPushHttpClientFactory::DEFAULT_TIMEOUT, $factory->getArgument('$timeout'));
        self::assertSame(WebPushHttpClientFactory::DEFAULT_CONNECT_TIMEOUT, $factory->getArgument('$connectTimeout'));
        self::assertSame(WebPushHttpClientFactory::class, (string) $container->getDefinition(MinishlinkWebPushSender::class)->getArgument('$httpClientFactory'));
    }

    public function testWiresConfiguredHttpTimeouts(): void
    {
        $container = $this->load(['web_push' => ['enabled' => true, 'timeout' => 8, 'connect_timeout' => 1.5]]);

        $factory = $container->getDefinition(WebPushHttpClientFactory::class);
        self::assertSame(8.0, $factory->getArgument('$timeout'));
        self::assertSame(1.5, $factory->getArgument('$connectTimeout'));
    }

    public function testCompilesAndWiresTheTestEndpoint(): void
    {
        $container = $this->load([
            'service_worker' => ['web_push' => true, 'web_push_defaults' => ['url' => '/inbox']],
            'web_push'       => [
                'enabled'           => true,
                'vapid'             => ['public_key' => 'pub', 'private_key' => 'priv'],
                'test_notification' => ['enabled' => true, 'roles' => ['ROLE_USER', 'ROLE_USER'], 'badge' => '/badge.png'],
            ],
        ]);

        self::assertTrue($container->getParameter('nowo_pwa.web_push.test_notification.enabled'));
        $config = $container->getDefinition(PushTestController::class)->getArgument('$config');
        self::assertSame('/inbox', $config['url']);
        self::assertSame('/icons/icon-192.png', $config['icon']);
        self::assertSame('/badge.png', $config['badge']);
        self::assertSame(['ROLE_USER', 'ROLE_USER'], $config['roles']);
        self::assertArrayNotHasKey('enabled', $config);
        self::assertTrue($container->getDefinition(PushTestController::class)->hasTag('controller.service_arguments'));

        $this->compileWith($container, [PushSubscriptionProviderInterface::class => StubPushSubscriptionProvider::class]);

        self::assertInstanceOf(PushTestController::class, $container->get(PushTestController::class));
        self::assertInstanceOf(MinishlinkWebPushSender::class, $container->get(WebPushSenderInterface::class));
    }

    public function testDeprecatedPortIsBridgedByTheAdapter(): void
    {
        $container = $this->testEndpointContainer();
        $this->compileWith($container, [PushSubscriptionProviderInterface::class => StubPushSubscriptionProvider::class], public: true);

        self::assertTrue($container->hasDefinition(PushSubscriptionProviderPass::ADAPTER_ID));
        self::assertSame(LegacyPushSubscriptionProviderAdapter::class, $container->getDefinition(PushSubscriptionProviderPass::ADAPTER_ID)->getClass());
        self::assertInstanceOf(PushTestController::class, $container->get(PushTestController::class));
    }

    public function testIdentifierPortIsPreferredAndNoAdapterIsRegistered(): void
    {
        $container = $this->testEndpointContainer();
        $this->compileWith($container, [
            PushRecipientSubscriptionProviderInterface::class => StubRecipientSubscriptionProvider::class,
            PushSubscriptionProviderInterface::class          => StubPushSubscriptionProvider::class,
        ]);

        self::assertFalse($container->hasDefinition(PushSubscriptionProviderPass::ADAPTER_ID));
        self::assertInstanceOf(PushTestController::class, $container->get(PushTestController::class));
    }

    public function testMissingProviderNamesTheIdentifierPort(): void
    {
        $container = $this->testEndpointContainer();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(PushRecipientSubscriptionProviderInterface::class);
        $this->compileWith($container, []);
    }

    public function testPassIgnoresContainersWithoutTheTestEndpoint(): void
    {
        $container = $this->container();
        (new PushSubscriptionProviderPass())->process($container);

        self::assertFalse($container->hasDefinition(PushSubscriptionProviderPass::ADAPTER_ID));
    }

    private function testEndpointContainer(): ContainerBuilder
    {
        return $this->load(['web_push' => ['enabled' => true, 'test_notification' => ['enabled' => true]]]);
    }

    /**
     * Keeps only the web push services, adds the host/framework services they need, and compiles with the bundle pass.
     *
     * @param array<class-string, class-string> $providers
     */
    private function compileWith(ContainerBuilder $container, array $providers, bool $public = false): void
    {
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!in_array($id, ['service_container', PushTestController::class, MinishlinkWebPushSender::class, WebPushEndpointGuard::class, WebPushHttpClientFactory::class], true)) {
                $container->removeDefinition($id);
            }
        }
        $container->register('security.token_storage', TokenStorage::class);
        $container->register('security.access_decision_manager', AccessDecisionManager::class);
        $container->register('security.authorization_checker', AuthorizationChecker::class)->setArguments([new Reference('security.token_storage'), new Reference('security.access_decision_manager')]);
        $container->register('security.csrf.token_manager', CsrfTokenManager::class);
        $container->register('router', UrlGenerator::class)->setArguments([new RouteCollection(), new RequestContext()]);
        foreach ($providers as $id => $class) {
            $container->register($id, $class);
        }
        $container->addCompilerPass(new PushSubscriptionProviderPass());
        $container->addCompilerPass(new class($public) implements CompilerPassInterface {
            public function __construct(private readonly bool $public)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                if ($this->public && $container->hasDefinition(PushSubscriptionProviderPass::ADAPTER_ID)) {
                    $container->getDefinition(PushSubscriptionProviderPass::ADAPTER_ID)->setPublic(true);
                }
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
        $container->compile();
    }

    public function testMissingDependenciesBreakTheContainerBuild(): void
    {
        $extension = new PwaExtension(new WebPushRequirements(findHttpFactories: static function (): never {
            throw new RuntimeException('none');
        }));

        $this->expectException(WebPushDependencyException::class);
        $this->expectExceptionMessage('composer require nyholm/psr7');
        $extension->load([['web_push' => ['enabled' => true]]], $this->container());
    }

    public function testMissingSecurityBreaksTheContainerBuildOnlyWithTheTestEndpoint(): void
    {
        $requirements = new WebPushRequirements(classExists: static fn (string $class): bool => $class === WebPush::class);
        (new PwaExtension($requirements))->load([['web_push' => ['enabled' => true]]], $this->container());

        $this->expectException(WebPushDependencyException::class);
        $this->expectExceptionMessage('symfony/security-bundle');
        (new PwaExtension($requirements))->load([['web_push' => ['enabled' => true, 'test_notification' => ['enabled' => true]]]], $this->container());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = $this->container();
        (new PwaExtension())->load([$config], $container);

        return $container;
    }

    private function isRegistered(ContainerBuilder $container, string $id): bool
    {
        return $container->hasDefinition($id) && !$container->getDefinition($id)->hasTag('container.excluded');
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        return $container;
    }
}

/**
 * @internal
 */
final class StubPushSubscriptionProvider implements PushSubscriptionProviderInterface
{
    public function findSubscriptions(UserInterface $user): iterable
    {
        return [];
    }

    public function removeSubscription(UserInterface $user, PushSubscriptionData $subscription): void
    {
    }
}

/**
 * @internal
 */
final class StubRecipientSubscriptionProvider implements PushRecipientSubscriptionProviderInterface
{
    public function findRecipientSubscriptions(string $userIdentifier): iterable
    {
        return [];
    }

    public function removeRecipientSubscription(string $userIdentifier, PushSubscriptionData $subscription): void
    {
    }
}
