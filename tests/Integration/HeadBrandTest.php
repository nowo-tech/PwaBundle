<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\Brand\PwaBrand;
use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\PwaBundle\Service\PwaRouteTargeting;
use Nowo\PwaBundle\Tests\Support\BrandFixtures;
use Nowo\PwaBundle\Twig\PwaTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Spec 220 S4: the bundle head template with and without a brand provider.
 */
final class HeadBrandTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../src/Resources/views';

    /** Host config in the style of a real app: explicit theme colours, Apple title and apple-touch icon. */
    private const HOST_CONFIG = [
        'manifest' => [
            'name'             => 'Configured',
            'short_name'       => 'Configured',
            'theme_color'      => '#1f6f54',
            'background_color' => '#f3f6f4',
            'icons'            => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any']],
        ],
        'meta' => [
            'apple_mobile_web_app_title' => 'Configured',
            'theme_color_light'          => '#1f6f54',
            'theme_color_dark'           => '#4aad7f',
            'apple_touch_icons'          => [['href' => '/icons/apple-touch-icon.png', 'sizes' => '180x180']],
        ],
    ];

    public function testWithoutBrandProviderTheHeadIsByteIdentical(): void
    {
        foreach ([[], self::HOST_CONFIG] as $config) {
            $plain = $this->render($config, null);

            self::assertSame($plain, $this->render($config, new PwaBrand()));
            self::assertSame($plain, $this->render($config, new PwaBrand(BrandFixtures::failingProvider())));
        }
    }

    public function testBrandDrivesTheHeadTags(): void
    {
        $brand = BrandFixtures::appIconBrand();
        $html  = $this->render(self::HOST_CONFIG, new PwaBrand(BrandFixtures::provider($brand)));

        self::assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest?v=' . $brand->version . '">', $html);
        self::assertStringContainsString('<meta name="theme-color" content="#f3f6f4" media="(prefers-color-scheme: light)">', $html);
        self::assertStringContainsString('<meta name="theme-color" content="#0c1210" media="(prefers-color-scheme: dark)">', $html);
        self::assertStringContainsString('<meta name="apple-mobile-web-app-title" content="Acme Portal">', $html);
        self::assertStringContainsString('<link rel="apple-touch-icon" sizes="512x512" href="/_settings-file/brand/app_icon/0123456789ab.png">', $html);
        self::assertStringNotContainsString('/icons/apple-touch-icon.png', $html);
    }

    public function testBrandIconBecomesTheIconLinksWithoutConfiguredAppleIcons(): void
    {
        $html = $this->render([], new PwaBrand(BrandFixtures::provider(BrandFixtures::appIconBrand(isDefault: true))));

        self::assertStringContainsString('<link rel="icon" type="image/png" sizes="512x512" href="/_settings-file/brand/app_icon/0123456789ab.png">', $html);
        self::assertStringContainsString('<link rel="apple-touch-icon" href="/_settings-file/brand/app_icon/0123456789ab.png">', $html);
        self::assertSame(1, substr_count($html, 'rel="icon"'));
        // Default brand: the configured short name stays the Apple title.
        self::assertStringContainsString('<meta name="apple-mobile-web-app-title" content="App">', $html);
    }

    public function testBrandNameIsEscapedInTheHead(): void
    {
        $html = $this->render([], new PwaBrand(BrandFixtures::provider(BrandFixtures::brand('A"><b>x</b>', shortName: 'A"><b>'))));

        self::assertStringContainsString('content="A&quot;&gt;&lt;b&gt;"', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    /**
     * @param array{manifest?: array<string, mixed>, meta?: array<string, mixed>} $hostConfig
     */
    private function render(array $hostConfig, ?PwaBrand $brand): string
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [$hostConfig]);

        $loader = new FilesystemLoader();
        $loader->addPath(self::VIEWS, 'NowoPwaBundle');
        $twig = new Environment($loader, ['strict_variables' => false]);
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/bundles/pwa/' . $path));

        $requestStack = new RequestStack();
        $request      = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $requestStack->push($request);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name): string => $name === 'nowo_pwa_manifest' ? '/manifest.webmanifest' : '/sw.js',
        );

        $extension = new PwaTwigExtension(
            true,
            $twig,
            $requestStack,
            $urlGenerator,
            new PwaRouteTargeting(),
            $config['manifest'],
            $config['meta'],
            $config['service_worker'],
            $config['install_prompt'],
            $config['install_links'],
            $config['client'],
            $config['route_targeting'],
            $config['web_ui']['templates'],
            $config['routes'],
            $brand,
        );

        return $extension->renderHead();
    }
}
