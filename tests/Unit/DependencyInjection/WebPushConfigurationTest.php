<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\DependencyInjection;

use Nowo\PwaBundle\DependencyInjection\Configuration;
use Nowo\PwaBundle\WebPush\WebPushEndpointGuard;
use Nowo\PwaBundle\WebPush\WebPushHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class WebPushConfigurationTest extends TestCase
{
    public function testDefaultsAreDisabledAndSafe(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertFalse($config['web_push']['enabled']);
        self::assertSame(['public_key' => '', 'private_key' => '', 'subject' => ''], $config['web_push']['vapid']);
        self::assertSame(WebPushEndpointGuard::DEFAULT_ALLOWED_HOST_SUFFIXES, $config['web_push']['allowed_endpoint_hosts']);
        self::assertSame(WebPushHttpClientFactory::DEFAULT_TIMEOUT, $config['web_push']['timeout']);
        self::assertSame(WebPushHttpClientFactory::DEFAULT_CONNECT_TIMEOUT, $config['web_push']['connect_timeout']);
        $test = $config['web_push']['test_notification'];
        self::assertFalse($test['enabled']);
        self::assertSame(['ROLE_USER'], $test['roles']);
        self::assertSame('nowo_pwa_push', $test['csrf_token_id']);
        self::assertSame(10, $test['max_subscriptions']);
        self::assertSame('nowo-pwa-push-test', $test['tag']);
        self::assertSame(300, $test['ttl']);
        self::assertSame('high', $test['urgency']);
        self::assertNull($test['title']);
        self::assertNull($test['route']);
        self::assertSame(['path' => '/pwa/push/test', 'name' => 'nowo_pwa_push_test'], $config['routes']['push_test']);
    }

    public function testConnectTimeoutMustNotExceedTotalTimeout(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('connect_timeout must not exceed nowo_pwa.web_push.timeout');

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_push' => ['timeout' => 2, 'connect_timeout' => 3],
        ]]);
    }

    public function testRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_push' => ['timeout' => 0],
        ]]);
    }

    public function testTestNotificationRequiresTheSender(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('requires nowo_pwa.web_push.enabled: true');

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_push' => ['test_notification' => ['enabled' => true]],
        ]]);
    }

    public function testAcceptsFullConfiguration(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_push' => [
                'enabled'                => true,
                'vapid'                  => ['public_key' => 'pub', 'private_key' => 'priv', 'subject' => 'mailto:ops@example.com'],
                'allowed_endpoint_hosts' => ['push.example.org'],
                'test_notification'      => ['enabled' => true, 'roles' => [], 'csrf_token_id' => 'account_push', 'urgency' => 'normal'],
            ],
            'routes' => ['push_test' => ['path' => '/account/push/test', 'name' => 'account_push_test']],
        ]]);

        self::assertTrue($config['web_push']['test_notification']['enabled']);
        self::assertSame([], $config['web_push']['test_notification']['roles']);
        self::assertSame(['push.example.org'], $config['web_push']['allowed_endpoint_hosts']);
        self::assertSame('account_push_test', $config['routes']['push_test']['name']);
    }
}
