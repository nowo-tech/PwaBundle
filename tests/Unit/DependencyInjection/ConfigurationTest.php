<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\DependencyInjection;

use Nowo\PwaBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertTrue($config['enabled']);
        self::assertSame('standalone', $config['manifest']['display']);
        self::assertTrue($config['manifest']['absolute_start_url']);
        self::assertSame('/sw.js', $config['routes']['service_worker']['path']);
        self::assertTrue($config['service_worker']['enabled']);
        self::assertTrue($config['client']['register_on_load']);
        self::assertTrue($config['install_links']['enabled']);
        self::assertSame('banner', $config['install_prompt']['display']);
        self::assertSame('nowo_pwa_install_never', $config['install_prompt']['never_dismiss_key']);
        self::assertTrue($config['install_prompt']['show_never_option']);
        self::assertSame('name', $config['route_targeting']['match_by']);
        self::assertSame('all', $config['install_prompt']['route_targeting']['mode']);
        self::assertSame(3600, $config['http']['manifest_cache_max_age']);
    }
}
