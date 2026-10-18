<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\Twig\PwaWebPushTwigExtension;
use Nowo\PwaBundle\WebPush\MinishlinkWebPushSender;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function basename;
use function glob;
use function html_entity_decode;
use function is_array;
use function json_decode;
use function preg_match;
use function sort;

final class PushTemplatesTest extends TestCase
{
    private const VIEWS        = __DIR__ . '/../../src/Resources/views';
    private const TRANSLATIONS = __DIR__ . '/../../src/Resources/translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (glob(self::TRANSLATIONS . '/NowoPwaBundle.*.yaml') ?: [] as $file) {
            $locale = (string) preg_replace('/^NowoPwaBundle\.(.+)\.yaml$/', '$1', basename($file));
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheSameKeysAsEnglish(string $locale): void
    {
        self::assertSame($this->keys('en'), $this->keys($locale));
    }

    #[DataProvider('locales')]
    public function testPushDiagnosticsRendersTranslatedLabels(string $locale): void
    {
        $html = $this->twig($locale)->render('@NowoPwaBundle/pwa/push_diagnostics.html.twig');

        self::assertStringNotContainsString('push_diagnostics.', $html);
        self::assertStringContainsString('data-controller="push-diagnostics"', $html);
        self::assertStringContainsString('data-push-diagnostics-test-url-value="/pwa/push/test"', $html);
        self::assertStringContainsString('data-push-diagnostics-csrf-token-value="tok"', $html);
        self::assertStringContainsString('data-push-diagnostics-vapid-public-key-value="pub"', $html);
        self::assertStringContainsString('data-push-diagnostics-preference-value=""', $html);
        self::assertStringContainsString('data-push-diagnostics-auto-value="true"', $html);

        self::assertSame(1, preg_match('/data-push-diagnostics-labels-value="([^"]*)"/', $html, $m));
        $labels = json_decode(html_entity_decode($m[1]), true);
        self::assertIsArray($labels);
        self::assertCount(7, $labels['steps']);
        self::assertArrayHasKey('browser_subscription.push_service_error', $labels['codes']);
        self::assertArrayHasKey('server_subscription.network', $labels['codes']);
    }

    public function testPushDiagnosticsHostOverrides(): void
    {
        $html = $this->twig('en')->render('@NowoPwaBundle/pwa/push_diagnostics.html.twig', [
            'stimulus_controller' => 'my-push',
            'config_url'          => '/account/realtime/config',
            'subscribe_url'       => '/account/push/subscribe',
            'test_url'            => '/account/push/test',
            'csrf_token'          => 'host-token',
            'vapid_public_key'    => null,
            'preference'          => false,
            'auto'                => false,
            'check_class_prefix'  => 'push-check',
            'css_class'           => 'panel',
            'button_class'        => 'btn-ghost',
        ]);

        self::assertStringContainsString('data-controller="my-push"', $html);
        self::assertStringContainsString('data-my-push-config-url-value="/account/realtime/config"', $html);
        self::assertStringContainsString('data-my-push-subscribe-url-value="/account/push/subscribe"', $html);
        self::assertStringContainsString('data-my-push-test-url-value="/account/push/test"', $html);
        self::assertStringContainsString('data-my-push-csrf-token-value="host-token"', $html);
        self::assertStringContainsString('data-my-push-vapid-public-key-value=""', $html);
        self::assertStringContainsString('data-my-push-preference-value="false"', $html);
        self::assertStringContainsString('data-my-push-auto-value="false"', $html);
        self::assertStringContainsString('data-my-push-class-prefix-value="push-check"', $html);
        self::assertStringContainsString('data-action="my-push#test"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary panel__check btn-ghost"', $html);
        self::assertStringContainsString('Press “Check again” to run the check for this device.', $html);
    }

    #[DataProvider('locales')]
    public function testBrowserHintAndGuideRender(string $locale): void
    {
        $twig = $this->twig($locale);
        $hint = $twig->render('@NowoPwaBundle/pwa/browser_hint.html.twig', ['guide_url' => '/guide']);
        self::assertStringNotContainsString('browser_hint.', $hint);
        self::assertStringContainsString('data-controller="browser-hint"', $hint);
        self::assertStringContainsString('href="/guide#browser-brave"', $hint);
        self::assertStringContainsString('data-browser-hint-id="other"', $hint);

        $guide = $twig->render('@NowoPwaBundle/pwa/browser_guide.html.twig');
        self::assertStringNotContainsString('browser_hint.', $guide);
        self::assertStringContainsString('id="browser-ios"', $guide);
        self::assertStringContainsString('__toc', $guide);
    }

    public function testBrowserHintWithoutGuideAndCustomBrowsers(): void
    {
        $twig = $this->twig('en');
        $hint = $twig->render('@NowoPwaBundle/pwa/browser_hint.html.twig', ['browsers' => ['brave'], 'stimulus_controller' => 'hint']);
        self::assertStringNotContainsString('<a ', $hint);
        self::assertStringContainsString('data-hint-target="hint"', $hint);
        self::assertStringNotContainsString('data-browser-hint-id="chrome"', $hint);

        $guide = $twig->render('@NowoPwaBundle/pwa/browser_guide.html.twig', ['show_toc' => false, 'browsers' => ['firefox']]);
        self::assertStringNotContainsString('__toc', $guide);
        self::assertStringContainsString('id="browser-firefox"', $guide);
    }

    private function twig(string $locale): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::VIEWS, 'NowoPwaBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', self::TRANSLATIONS . '/NowoPwaBundle.' . $locale . '.yaml', $locale, 'NowoPwaBundle');
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new ThemeExtension(Theme::fromConfig('none', [])));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/pwa/push/test');
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturn(new CsrfToken('nowo_pwa_push', 'tok'));
        $twig->addExtension(new PwaWebPushTwigExtension(
            ['enabled' => true, 'test_enabled' => true, 'test_route' => 'nowo_pwa_push_test', 'csrf_token_id' => 'nowo_pwa_push'],
            $urlGenerator,
            new MinishlinkWebPushSender('pub', 'priv'),
            $csrf,
        ));

        return $twig;
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $flatten = static function (array $data, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($data as $key => $value) {
                $keys = is_array($value) ? [...$keys, ...$flatten($value, $prefix . $key . '.')] : [...$keys, $prefix . $key];
            }

            return $keys;
        };
        $keys = $flatten((array) Yaml::parseFile(self::TRANSLATIONS . '/NowoPwaBundle.' . $locale . '.yaml'));
        sort($keys);

        return $keys;
    }
}
