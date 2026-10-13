<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use PHPUnit\Framework\TestCase;

use function error_reporting;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

final class PwaRouteTargetingTest extends TestCase
{
    public function testModesByRouteName(): void
    {
        $targeting = new PwaRouteTargeting();

        self::assertTrue($targeting->shouldApply('home', '/', PwaRouteTargeting::MODE_ALL, []));
        self::assertTrue($targeting->shouldApply('dashboard', '/dash', PwaRouteTargeting::MODE_ONLY, ['dashboard']));
        self::assertFalse($targeting->shouldApply('home', '/', PwaRouteTargeting::MODE_ONLY, ['dashboard']));
        self::assertFalse($targeting->shouldApply('admin', '/admin', PwaRouteTargeting::MODE_EXCEPT, ['admin']));
        self::assertFalse($targeting->shouldApply('', '/', PwaRouteTargeting::MODE_ONLY, ['home']));
        self::assertTrue($targeting->shouldApply('', '/', PwaRouteTargeting::MODE_EXCEPT, ['admin']));
        self::assertTrue($targeting->shouldApply('home', '/', PwaRouteTargeting::MODE_ONLY, [123, 'home', '']));
    }

    public function testModesByPath(): void
    {
        $targeting = new PwaRouteTargeting();

        self::assertTrue($targeting->shouldApply('', '/vault', PwaRouteTargeting::MODE_ONLY, ['/vault'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('', '/vault/items', PwaRouteTargeting::MODE_ONLY, ['/vault*'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertFalse($targeting->shouldApply('', '/other', PwaRouteTargeting::MODE_ONLY, ['/vault*'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('', '/vault', PwaRouteTargeting::MODE_EXCEPT, ['/admin*'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertFalse($targeting->shouldApply('', '/admin/users', PwaRouteTargeting::MODE_EXCEPT, ['/admin*'], PwaRouteTargeting::MATCH_BY_PATH));
    }

    public function testPathNormalizationAndWildcards(): void
    {
        $targeting = new PwaRouteTargeting();

        self::assertTrue($targeting->shouldApply('', '', PwaRouteTargeting::MODE_ONLY, ['/'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('', 'vault', PwaRouteTargeting::MODE_ONLY, ['/vault'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('', '/anything', PwaRouteTargeting::MODE_ONLY, ['*'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('', '/admin/users', PwaRouteTargeting::MODE_ONLY, ['regex:/^\\/admin/'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertFalse($targeting->shouldApply('', '/public', PwaRouteTargeting::MODE_ONLY, ['regex:/^\\/admin/'], PwaRouteTargeting::MATCH_BY_PATH));
        self::assertTrue($targeting->shouldApply('home', '/', 'unknown-mode', ['admin']));
        self::assertTrue($targeting->shouldApply('home', '/', '', []));
    }

    public function testPlainPathWithSlashesIsNotARegex(): void
    {
        $targeting = new PwaRouteTargeting();
        $path      = PwaRouteTargeting::MATCH_BY_PATH;

        // Before: "/blog/" was used as an unanchored regex and matched "/x/blog/y".
        self::assertFalse($targeting->shouldApply('', '/x/blog/y', PwaRouteTargeting::MODE_ONLY, ['/blog/'], $path));
        self::assertTrue($targeting->shouldApply('', '/blog', PwaRouteTargeting::MODE_ONLY, ['/blog/'], $path));
        self::assertTrue($targeting->shouldApply('', '/blog/', PwaRouteTargeting::MODE_ONLY, ['/blog/'], $path));
        self::assertTrue($targeting->shouldApply('', '/a.b/c', PwaRouteTargeting::MODE_ONLY, ['/a.b/c/'], $path));
    }

    public function testExplicitRegexMarkers(): void
    {
        $targeting = new PwaRouteTargeting();
        $path      = PwaRouteTargeting::MATCH_BY_PATH;

        self::assertTrue($targeting->shouldApply('', '/admin/users', PwaRouteTargeting::MODE_ONLY, ['regex:/^\/admin/'], $path));
        self::assertFalse($targeting->shouldApply('', '/x/admin', PwaRouteTargeting::MODE_ONLY, ['regex:/^\/admin/'], $path));
        self::assertTrue($targeting->shouldApply('', '/ADMIN', PwaRouteTargeting::MODE_ONLY, ['#^/admin(/|$)#i'], $path));
        self::assertFalse($targeting->shouldApply('', '/administrator', PwaRouteTargeting::MODE_ONLY, ['#^/admin(/|$)#'], $path));
        self::assertTrue($targeting->shouldApply('', '/blog/post', PwaRouteTargeting::MODE_ONLY, ['regex: #^/blog/#'], $path));
        // "regex:" with nothing after it is a plain path, not an empty regex.
        self::assertFalse($targeting->shouldApply('', '/blog', PwaRouteTargeting::MODE_ONLY, ['regex:'], $path));
    }

    public function testInvalidRegexNeverMatchesAndRaisesNoWarning(): void
    {
        $targeting = new PwaRouteTargeting();
        $path      = PwaRouteTargeting::MATCH_BY_PATH;

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            // Like PHP's own handler and Symfony's ErrorHandler: ignore what the @ operator silenced.
            if ((error_reporting() & $errno) !== 0) {
                $warnings[] = $errstr;
            }

            return true;
        });

        try {
            self::assertFalse($targeting->shouldApply('', '/admin', PwaRouteTargeting::MODE_ONLY, ['regex:/^(admin/'], $path));
            self::assertFalse($targeting->shouldApply('', '/admin', PwaRouteTargeting::MODE_ONLY, ['#^/(admin#'], $path));
            self::assertTrue($targeting->shouldApply('', '/admin', PwaRouteTargeting::MODE_EXCEPT, ['regex:not-delimited'], $path));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
    }

    public function testLegacySlashRegexStillWorksButIsDeprecated(): void
    {
        $targeting = new PwaRouteTargeting();

        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }

            return true;
        });

        try {
            self::assertTrue($targeting->shouldApply('', '/admin/users', PwaRouteTargeting::MODE_ONLY, ['/^\/admin/'], PwaRouteTargeting::MATCH_BY_PATH));
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $deprecations);
        self::assertStringContainsString('regex:', $deprecations[0]);
    }
}
