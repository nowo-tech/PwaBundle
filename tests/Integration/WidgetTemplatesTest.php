<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Install prompt / install links widgets with a stub UiKit theme (REQ-UI-003 "theme slots"): slot classes and
 * attributes reach the markup, the deprecated 1.x class options still win, and a host `@!` override of one block
 * keeps the rest of the upstream template.
 */
final class WidgetTemplatesTest extends TestCase
{
    private const VIEWS        = __DIR__ . '/../../src/Resources/views';
    private const TRANSLATIONS = __DIR__ . '/../../src/Resources/translations';

    /** Stub host theme: one marker class per slot, one attribute on the card. */
    private const STUB_THEME = [
        'classes' => [
            'card'          => 'stub-card',
            'card_title'    => 'stub-title',
            'card_intro'    => 'stub-intro',
            'toolbar'       => 'stub-toolbar',
            'btn_primary'   => 'stub-primary',
            'btn_secondary' => 'stub-secondary',
            'btn_link'      => 'stub-link',
            'btn_sm'        => 'stub-sm',
        ],
        'attributes' => [
            'card' => ['data-stub-card' => 'yes'],
        ],
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function frameworks(): iterable
    {
        yield 'none' => ['none'];
        yield 'bootstrap5' => ['bootstrap5'];
        yield 'tailwind' => ['tailwind'];
    }

    #[DataProvider('frameworks')]
    public function testInstallPromptReadsTheThemeSlots(string $framework): void
    {
        $html = $this->twig($framework)->render('@NowoPwaBundle/pwa/install_prompt.html.twig', $this->context());

        self::assertMatchesRegularExpression('/class="nowo-ui-card [^"]*stub-card nowo-pwa-install__panel" data-stub-card="yes"/', $html);
        self::assertMatchesRegularExpression('/<h2 id="nowo-pwa-install-title" class="nowo-ui-card__title stub-title nowo-pwa-install__title">Install<\/h2>/', $html);
        self::assertStringContainsString('stub-intro nowo-pwa-install__message', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-toolbar [^"]*stub-toolbar nowo-pwa-install__actions"/', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-btn nowo-ui-btn--primary [^"]*stub-primary nowo-pwa-install__button nowo-pwa-install__button--install"/', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-btn nowo-ui-btn--secondary [^"]*stub-secondary nowo-pwa-install__button nowo-pwa-install__button--dismiss"/', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-btn nowo-ui-btn--link [^"]*stub-link nowo-pwa-install__button nowo-pwa-install__button--never"/', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoPwaBundle"', $html);
        self::assertStringContainsString('aria-describedby="nowo-pwa-install-message"', $html);
        $this->assertSafeMarkup($html);
    }

    public function testFrameworkDefaultsComeFromTheSlots(): void
    {
        $html = $this->twig('bootstrap5', [])->render('@NowoPwaBundle/pwa/install_prompt.html.twig', $this->context());

        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-pwa-install__button', $html);
        self::assertStringContainsString('class="nowo-ui-card card nowo-pwa-install__panel"', $html);
    }

    public function testPerBundleThemeExceptionApplies(): void
    {
        $theme = self::STUB_THEME + ['bundles' => ['NowoPwaBundle' => ['classes' => ['btn_primary' => 'pwa-only']]]];
        $html  = $this->twig('none', $theme)->render('@NowoPwaBundle/pwa/install_prompt.html.twig', $this->context());

        self::assertStringContainsString('nowo-ui-btn--primary pwa-only nowo-pwa-install__button--install', str_replace('nowo-pwa-install__button ', '', $html));
        self::assertStringNotContainsString('stub-primary', $html);
    }

    public function testDeprecatedButtonClassesStillReplaceTheSlotClasses(): void
    {
        $html = $this->twig('bootstrap5')->render('@NowoPwaBundle/pwa/install_prompt.html.twig', $this->context([
            'button_class'         => 'app-install',
            'dismiss_button_class' => 'app-later',
            'never_button_class'   => '',
        ]));

        self::assertStringContainsString('class="app-install" data-pwa-install-action="install"', $html);
        self::assertStringContainsString('class="app-later" data-pwa-install-action="dismiss-remind"', $html);
        self::assertStringContainsString('class="" data-pwa-install-action="dismiss-never"', $html);
    }

    public function testModalDisplayBrandAndEyebrow(): void
    {
        $html = $this->twig('none')->render('@NowoPwaBundle/pwa/install_prompt.html.twig', $this->context([
            'display'           => 'modal',
            'mark_asset'        => '/icons/mark.svg',
            'eyebrow'           => 'New',
            'title'             => 'Get the app',
            'show_never_option' => false,
        ]));

        self::assertStringContainsString('nowo-pwa-install--modal', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('class="nowo-pwa-install__backdrop" data-pwa-install-action="dismiss-remind"', $html);
        self::assertStringContainsString('<img src="/icons/mark.svg" alt="Demo"', $html);
        self::assertStringContainsString('class="nowo-ui-muted nowo-pwa-install__eyebrow">New</p>', $html);
        self::assertStringContainsString('>Get the app</h2>', $html);
        self::assertStringNotContainsString('dismiss-never', $html);
    }

    public function testHostOverridesOneBlockThroughTheParentTemplate(): void
    {
        $twig = $this->twig('none', self::STUB_THEME, [
            'host/install_prompt.html.twig' => "{% extends '@!NowoPwaBundle/pwa/install_prompt.html.twig' %}"
                . '{% block pwa_install_mark %}<span class="app-mark" data-host-mark>M</span>{% endblock %}',
        ]);
        $html = $twig->render('host/install_prompt.html.twig', $this->context());

        self::assertStringContainsString('<span class="app-mark" data-host-mark>M</span>', $html);
        self::assertStringContainsString('stub-primary nowo-pwa-install__button', $html);
        self::assertStringContainsString('data-pwa-install-action="dismiss-never"', $html);
        self::assertSame(1, substr_count($html, 'id="nowo-pwa-install"'));
    }

    #[DataProvider('frameworks')]
    public function testInstallLinksReadTheThemeSlots(string $framework): void
    {
        $html = $this->twig($framework)->render('@NowoPwaBundle/pwa/install_links.html.twig', ['install_links' => ['css_class' => 'nowo-pwa-install-links', 'visibility' => 'all']]);

        self::assertMatchesRegularExpression('/class="nowo-ui-btn nowo-ui-btn--primary [^"]*stub-primary nowo-ui-btn--sm [^"]*stub-sm nowo-pwa-install-links__install"/', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-btn nowo-ui-btn--secondary [^"]*stub-secondary nowo-ui-btn--sm [^"]*stub-sm nowo-pwa-install-links__uninstall"/', $html);
        self::assertStringContainsString('>Install app</a>', $html);
        $this->assertSafeMarkup($html);
    }

    /**
     * @param array<string, mixed> $installPrompt
     *
     * @return array<string, mixed>
     */
    private function context(array $installPrompt = []): array
    {
        return [
            'manifest'       => ['name' => 'Demo app', 'short_name' => 'Demo'],
            'install_prompt' => $installPrompt + [
                'display'    => 'banner',
                'css_class'  => 'nowo-pwa-install',
                'position'   => 'bottom',
                'mark_asset' => null,
                'title'      => null,
                'eyebrow'    => null,
            ],
        ];
    }

    private function assertSafeMarkup(string $html): void
    {
        self::assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son[a-z]+\s*=/i', $html);
        self::assertDoesNotMatchRegularExpression('/(src|href)="(https?:)?\/\//i', $html);
        self::assertDoesNotMatchRegularExpression('/>\s*(install|offline)\.[a-z_]+\s*</', $html);
    }

    /**
     * @param array<string, mixed> $theme
     * @param array<string, string> $inline
     */
    private function twig(string $framework, array $theme = self::STUB_THEME, array $inline = []): Environment
    {
        $filesystem = new FilesystemLoader();
        $filesystem->addPath(self::VIEWS, 'NowoPwaBundle');
        $filesystem->addPath(self::VIEWS, '!NowoPwaBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader($inline), $filesystem]), ['strict_variables' => true]);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', self::TRANSLATIONS . '/NowoPwaBundle.en.yaml', 'en', 'NowoPwaBundle');
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new ThemeExtension(Theme::fromConfig($framework, $theme)));

        return $twig;
    }
}
