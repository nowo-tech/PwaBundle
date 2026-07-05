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
        self::assertSame(3600, $config['http']['manifest_cache_max_age']);
    }
}
