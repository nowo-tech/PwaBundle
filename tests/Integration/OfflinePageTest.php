<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\Controller\PwaController;
use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\PwaBundle\Service\ManifestBuilder;
use Nowo\PwaBundle\Service\ServiceWorkerScriptBuilder;
use Nowo\PwaBundle\View\OfflinePage;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\AuthPage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

/**
 * The offline page in production shape: the UiKit status layout (a complete, self-contained document — inline CSS
 * with the CSP nonce, no stylesheet / script request), rendered by the controller.
 */
final class OfflinePageTest extends TestCase
{
    public function testDefaultModelUsesTheStatusLayoutWithoutScripts(): void
    {
        $page = OfflinePage::create('Demo app', '');

        self::assertSame(Configuration::OFFLINE_LAYOUT, $page->layout);
        self::assertFalse($page->scripts);
        self::assertSame('NowoPwaBundle', $page->bundle);
        self::assertSame('Demo app', $page->brand);
        self::assertSame('/', $page->links[0]->href);
        self::assertNull(OfflinePage::create('', '/')->brand);
    }

    public function testControllerRendersASelfContainedDocument(): void
    {
        $twig     = $this->twig('nonce-123');
        $response = $this->controller($twig)->offline();
        $html     = (string) $response->getContent();

        self::assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        self::assertStringContainsString('<title>Offline — Demo app</title>', $html);
        self::assertStringContainsString('<style nonce="nonce-123" data-nowo-ui-status-css>', $html);
        self::assertStringContainsString('<meta name="theme-color" content="#112233">', $html);
        self::assertStringContainsString('data-testid="pwa-offline"', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-page-header__title">You are offline</h1>', $html);
        self::assertStringContainsString('<p class="nowo-ui-auth-panel__brand nowo-pwa-offline__brand">Demo app</p>', $html);
        self::assertStringContainsString('<a href="/app" data-testid="pwa-offline-retry">Try again</a>', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoPwaBundle"', $html);
        // Works offline: nothing to fetch.
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertDoesNotMatchRegularExpression('/(src|href)="(https?:)?\/\//', $html);
    }

    public function testCustomLayoutReachesThePageModel(): void
    {
        $twig       = $this->twig(null);
        $controller = $this->controller($twig, '@NowoUiKit/layout/status.html.twig');
        $html       = (string) $controller->offline()->getContent();

        self::assertStringContainsString('data-nowo-ui-status-css>', $html);
        self::assertStringNotContainsString('nonce=', $html);
    }

    public function testLegacyStandaloneTemplateStillRenders(): void
    {
        $html = $this->twig(null)->render('@NowoPwaBundle/pwa/offline_standalone.html.twig', [
            'page'          => OfflinePage::create('Demo app', '/'),
            'manifest_name' => 'Demo app',
            'manifest'      => ['lang' => 'en'],
            'retry_url'     => '/',
        ]);

        self::assertStringContainsString('<div class="nowo-pwa-offline__brand">Demo app</div>', $html);
        self::assertStringContainsString('You are offline', $html);
    }

    public function testDisabledBundleHasNoOfflinePage(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller($this->twig(null), null, false)->offline();
    }

    private function controller(Environment $twig, ?string $layout = null, bool $enabled = true): PwaController
    {
        $controller = new PwaController(
            $enabled,
            new ManifestBuilder(),
            new ServiceWorkerScriptBuilder(),
            ['name' => 'Demo app', 'start_url' => '/app', 'theme_color' => '#112233'],
            [],
            [],
            Configuration::TEMPLATE_DEFAULTS,
            [],
            [],
            null,
            $layout,
        );
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        return $controller;
    }

    private function twig(?string $nonce): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'NowoPwaBundle');
        $uiKit = dirname((string) (new ReflectionClass(AuthPage::class))->getFileName(), 2) . '/Resources/views';
        $loader->addPath($uiKit, 'NowoUiKit');
        $loader->addPath($uiKit, 'NowoUiKitBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 2) . '/src/Resources/translations/NowoPwaBundle.en.yaml', 'en', 'NowoPwaBundle');
        $twig->addExtension(new TranslationExtension($translator));

        $request = new Request();
        if ($nonce !== null) {
            $request->attributes->set('csp_nonce', $nonce);
        }
        $stack = new RequestStack();
        $stack->push($request);
        $twig->addExtension(new ThemeExtension(Theme::fromConfig('none', []), translator: $translator, requestStack: $stack));
        $app = new AppVariable();
        $app->setRequestStack($stack);
        $twig->addGlobal('app', $app);

        return $twig;
    }
}
