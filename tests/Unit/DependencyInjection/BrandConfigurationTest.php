<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\DependencyInjection;

use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\PwaBundle\Brand\BrandManifestOverlayProvider;
use Nowo\PwaBundle\Brand\PwaBrand;
use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\Service\ManifestOverlayProviderInterface;
use Nowo\PwaBundle\Tests\Support\BrandFixtures;
use Nowo\PwaBundle\Twig\PwaTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function in_array;

/**
 * Spec 220 S4: `nowo_pwa.brand` and the wiring of the brand consumer.
 */
final class BrandConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame(['mode' => 'auto', 'override_name' => false], $config['brand']);
    }

    public function testScalarShorthand(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['brand' => 'off']]);

        self::assertSame(['mode' => 'off', 'override_name' => false], $config['brand']);
    }

    public function testFullForm(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['brand' => ['override_name' => true]]]);

        self::assertSame('auto', $config['brand']['mode']);
        self::assertTrue($config['brand']['override_name']);
    }

    public function testUnknownModeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['brand' => 'always']]);
    }

    public function testAutoRegistersTheConsumerWithAnOptionalProvider(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->hasDefinition(PwaBrand::class));
        $provider = $container->getDefinition(PwaBrand::class)->getArgument('$provider');
        self::assertInstanceOf(Reference::class, $provider);
        self::assertSame(BrandProviderInterface::class, (string) $provider);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $provider->getInvalidBehavior());
        self::assertFalse($container->getDefinition(PwaBrand::class)->getArgument('$overrideName'));

        $overlay = $container->getDefinition(BrandManifestOverlayProvider::class);
        self::assertSame([['priority' => PwaBrand::OVERLAY_PRIORITY]], $overlay->getTag(ManifestOverlayProviderInterface::TAG));
        self::assertSame(['mode' => 'auto', 'override_name' => false], $container->getParameter('nowo_pwa.brand'));
    }

    public function testOverrideNameReachesTheConsumer(): void
    {
        $container = $this->load(['brand' => ['override_name' => true]]);

        self::assertTrue($container->getDefinition(PwaBrand::class)->getArgument('$overrideName'));
    }

    public function testOffRegistersNothing(): void
    {
        $container = $this->load(['brand' => 'off']);

        self::assertFalse($container->hasDefinition(PwaBrand::class));
        self::assertFalse($container->hasDefinition(BrandManifestOverlayProvider::class));
    }

    public function testControllerAndTwigExtensionTakeTheConsumerAsAnOptionalReference(): void
    {
        $container = $this->load([]);

        foreach ([PwaController::class, PwaTwigExtension::class] as $id) {
            $brand = $container->getDefinition($id)->getArgument('$brand');
            self::assertInstanceOf(Reference::class, $brand);
            self::assertSame(PwaBrand::class, (string) $brand);
            self::assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $brand->getInvalidBehavior());
        }
        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(PwaController::class)->getArgument('$manifestOverlayProviders'));
    }

    public function testCompiledContainerWithoutProviderResolvesNoBrand(): void
    {
        $container = $this->load([]);
        $container->getDefinition(PwaBrand::class)->setPublic(true);
        $this->compile($container);

        $pwa = $container->get(PwaBrand::class);
        self::assertInstanceOf(PwaBrand::class, $pwa);
        self::assertNull($pwa->current());
    }

    public function testCompiledContainerWithProviderResolvesTheBrand(): void
    {
        $container = $this->load([]);
        $brand     = BrandFixtures::brand();
        $container->register(BrandProviderInterface::class)->setSynthetic(true);
        $container->getDefinition(PwaBrand::class)->setPublic(true);
        $this->compile($container);
        $container->set(BrandProviderInterface::class, BrandFixtures::provider($brand));

        $pwa = $container->get(PwaBrand::class);
        self::assertInstanceOf(PwaBrand::class, $pwa);
        self::assertSame($brand, $pwa->current());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        (new PwaExtension())->load([$config], $container);

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        // Keep only the brand services: the rest of the bundle needs Twig, the router and the request stack.
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!in_array($id, [PwaBrand::class, BrandProviderInterface::class, 'service_container'], true)) {
                $container->removeDefinition($id);
            }
        }
        $container->compile();
    }
}
