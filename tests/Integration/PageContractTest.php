<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration;

use Nowo\PwaBundle\View\OfflinePage;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

use function dirname;

/**
 * REQ-UI-003 contract test of the bundle's only page, the offline fallback (unique page, page/auth regions). The fixture leaves
 * the layout null so the page renders through the stub guest layout; in production it defaults to the
 * self-contained UiKit status layout.
 */
final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        // layout null: the stub guest layout of the contract test (production default: the UiKit status layout).
        $page = OfflinePage::create('Demo app', '/', null);

        yield 'offline' => new PageFixture('@NowoPwaBundle/pwa/offline.html.twig', [
            'page'          => $page,
            'manifest_name' => 'Demo app',
            'manifest'      => ['name' => 'Demo app', 'theme_color' => '#0f172a'],
            'retry_url'     => '/',
        ]);
    }

    protected static function templatePaths(): array
    {
        return ['NowoPwaBundle' => dirname(__DIR__, 2) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 2) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 2) . '/docs/TEMPLATES.md';
    }
}
