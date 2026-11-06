<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function sprintf;

/**
 * install_prompt.button_paint and the pwa.css tokens: `host` leaves the buttons to the host's slot classes (no kit
 * selector of pwa.css matches them any more); `kit` and the tokens' defaults keep the previous output.
 */
final class InstallPromptButtonPaintTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../src/Resources/views';
    private const CSS   = __DIR__ . '/../../src/Resources/public/pwa.css';

    public function testHostPaintKeepsOnlyTheSlotClassesAndTheJsHooks(): void
    {
        $html = $this->render(['button_paint' => 'host']);

        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary stub-primary" data-pwa-install-action="install"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--secondary stub-secondary" data-pwa-install-action="dismiss-remind"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--link stub-link" data-pwa-install-action="dismiss-never"', $html);
        self::assertStringContainsString('nowo-pwa-install__actions-host"', $html);
        self::assertStringNotContainsString('nowo-pwa-install__button', $html);
        self::assertStringNotContainsString('nowo-pwa-install__actions"', $html);
    }

    /**
     * Every kit rule that paints a button hangs on __button* or __actions, so without them host classes win.
     */
    public function testNoKitButtonRuleMatchesInHostPaint(): void
    {
        $css = (string) file_get_contents(self::CSS);

        self::assertSame(1, preg_match_all('/__actions-host/', $css));
        preg_match_all('/^[^{};\n]*\bbutton\b[^{};\n]*$|^[^{};\n]*data-pwa-install-action[^{};\n]*$/m', $css, $selectors);
        self::assertNotSame([], $selectors[0]);

        foreach ($selectors[0] as $selector) {
            self::assertMatchesRegularExpression('/nowo-pwa-install__(button|actions )/', $selector);
        }
    }

    public function testKitPaintIsTheDefaultMarkup(): void
    {
        $default = $this->render([]);

        self::assertSame($default, $this->render(['button_paint' => 'kit']));
        self::assertStringContainsString('stub-primary nowo-pwa-install__button nowo-pwa-install__button--install"', $default);
        self::assertStringContainsString('nowo-pwa-install__actions"', $default);
        self::assertStringNotContainsString('__actions-host', $default);
    }

    public function testDeprecatedClassOptionKeepsWinningInHostPaint(): void
    {
        $html = $this->render(['button_paint' => 'host', 'button_class' => 'app-install']);

        self::assertStringContainsString('class="app-install" data-pwa-install-action="install"', $html);
    }

    public function testConfigurationDefaultsToKitAndRejectsOtherValues(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), []);
        self::assertSame('kit', $config['install_prompt']['button_paint']);

        $config = $processor->processConfiguration(new Configuration(), [['install_prompt' => ['button_paint' => 'host']]]);
        self::assertSame('host', $config['install_prompt']['button_paint']);

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['install_prompt' => ['button_paint' => 'x" onclick="y']]]);
    }

    /**
     * The new tokens hold the literal values pwa.css had, and the rules read them.
     */
    public function testTokensKeepThePreviousValues(): void
    {
        $css = (string) file_get_contents(self::CSS);

        $tokens = [
            'z-index'                => ['z-index', '1050'],
            'banner-max-width'       => ['max-width', '38rem'],
            'banner-inset-x'         => ['left', '1rem'],
            'banner-offset-bottom'   => ['bottom', '1rem'],
            'banner-offset-top'      => ['top', '1rem'],
            'flash-max-width'        => ['max-width', '42rem'],
            'modal-padding'          => ['padding', '1rem'],
            'modal-panel-padding'    => ['padding', '1.25rem 1.5rem'],
            'eyebrow-font-weight'    => ['font-weight', '700'],
            'eyebrow-letter-spacing' => ['letter-spacing', '0.08em'],
            'title-line-height'      => ['line-height', '1.25'],
            'links-font-size'        => ['font-size', '0.8125rem'],
            'links-font-weight'      => ['font-weight', '600'],
            'links-line-height'      => ['line-height', '1.25'],
        ];

        foreach ($tokens as $token => [$property, $value]) {
            self::assertStringContainsString(sprintf('--nowo-pwa-install-%s: %s;', $token, $value), $css);
            self::assertStringContainsString(sprintf('%s: var(--nowo-pwa-install-%s);', $property, $token), $css);
        }

        self::assertStringContainsString('--nowo-pwa-install-button-border-color: transparent;', $css);
        self::assertStringContainsString('border: 1px solid var(--nowo-pwa-install-button-border-color);', $css);
    }

    /**
     * @param array<string, mixed> $installPrompt
     */
    private function render(array $installPrompt): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::VIEWS, 'NowoPwaBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new Translator('en')));
        $twig->addExtension(new ThemeExtension(Theme::fromConfig('none', ['classes' => [
            'btn_primary'   => 'stub-primary',
            'btn_secondary' => 'stub-secondary',
            'btn_link'      => 'stub-link',
            'toolbar'       => 'stub-toolbar',
        ]])));

        return $twig->render('@NowoPwaBundle/pwa/install_prompt.html.twig', [
            'manifest'       => ['name' => 'Demo app', 'short_name' => 'Demo'],
            'install_prompt' => $installPrompt + [
                'display'    => 'banner',
                'css_class'  => 'nowo-pwa-install',
                'position'   => 'bottom',
                'mark_asset' => null,
                'title'      => null,
                'eyebrow'    => null,
            ],
        ]);
    }
}
