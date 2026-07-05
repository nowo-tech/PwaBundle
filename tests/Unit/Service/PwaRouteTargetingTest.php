<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\Service;

use Nowo\PwaBundle\Service\PwaRouteTargeting;
use PHPUnit\Framework\TestCase;

final class PwaRouteTargetingTest extends TestCase
{
    public function testModes(): void
    {
        $targeting = new PwaRouteTargeting();

        self::assertTrue($targeting->shouldApply('home', PwaRouteTargeting::MODE_ALL, []));
        self::assertTrue($targeting->shouldApply('dashboard', PwaRouteTargeting::MODE_ONLY, ['dashboard']));
        self::assertFalse($targeting->shouldApply('home', PwaRouteTargeting::MODE_ONLY, ['dashboard']));
        self::assertFalse($targeting->shouldApply('admin', PwaRouteTargeting::MODE_EXCEPT, ['admin']));
        self::assertFalse($targeting->shouldApply('', PwaRouteTargeting::MODE_ONLY, ['home']));
        self::assertTrue($targeting->shouldApply('', PwaRouteTargeting::MODE_EXCEPT, ['admin']));
        self::assertTrue($targeting->shouldApply('home', PwaRouteTargeting::MODE_ONLY, [123, 'home', '']));
    }
}
