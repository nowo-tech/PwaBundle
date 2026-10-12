<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Unit\WebPush;

use Minishlink\WebPush\WebPush;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Nowo\PwaBundle\WebPush\WebPushRequirements;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class WebPushRequirementsTest extends TestCase
{
    public function testDefaultsPassWithDevDependencies(): void
    {
        $requirements = new WebPushRequirements();
        $requirements->assertSatisfied();
        $requirements->assertSecurityAvailable();
        $this->addToAssertionCount(1);
    }

    public function testMissingLibrary(): void
    {
        $requirements = new WebPushRequirements(classExists: static fn (string $class): bool => $class !== WebPush::class);

        $this->expectException(WebPushDependencyException::class);
        $this->expectExceptionMessage('minishlink/web-push is not installed. Run: composer require minishlink/web-push:^11.0 nyholm/psr7');
        $requirements->assertSatisfied();
    }

    public function testOutdatedLibrary(): void
    {
        $requirements = new WebPushRequirements(webPushVersion: static fn (): string => 'v10.0.1');

        $this->expectException(WebPushDependencyException::class);
        $this->expectExceptionMessage('requires minishlink/web-push >= 11.0.0 (installed: v10.0.1)');
        $requirements->assertLibraryInstalled();
    }

    public function testUnknownVersionAndNewerVersionPass(): void
    {
        (new WebPushRequirements(webPushVersion: static fn (): ?string => null))->assertLibraryInstalled();
        (new WebPushRequirements(webPushVersion: static fn (): string => '11.2.0'))->assertLibraryInstalled();
        $this->addToAssertionCount(1);
    }

    public function testMissingPsr17Factories(): void
    {
        $requirements = new WebPushRequirements(findHttpFactories: static function (): never {
            throw new RuntimeException('No PSR-17 request factory found');
        });

        try {
            $requirements->assertSatisfied();
            self::fail('Expected exception');
        } catch (WebPushDependencyException $e) {
            self::assertStringContainsString('composer require nyholm/psr7', $e->getMessage());
            self::assertSame(WebPushRequirements::missingHttpImplementationMessage(), $e->getMessage());
            self::assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    public function testMissingSecurityComponents(): void
    {
        $noSecurity = new WebPushRequirements(classExists: static fn (string $class): bool => !str_contains($class, 'Authorization'));
        try {
            $noSecurity->assertSecurityAvailable();
            self::fail('Expected exception');
        } catch (WebPushDependencyException $e) {
            self::assertStringContainsString('composer require symfony/security-bundle', $e->getMessage());
        }

        $noCsrf = new WebPushRequirements(classExists: static fn (string $class): bool => $class !== CsrfTokenManagerInterface::class);
        $this->expectException(WebPushDependencyException::class);
        $this->expectExceptionMessage('composer require symfony/security-csrf');
        $noCsrf->assertSecurityAvailable();
    }
}
