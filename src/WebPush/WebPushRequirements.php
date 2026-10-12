<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush;

use Closure;
use Composer\InstalledVersions;
use Http\Discovery\Psr17FactoryDiscovery;
use Minishlink\WebPush\WebPush;
use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Nowo\PwaBundle\WebPush\Exception\WebPushDependencyException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;

use function class_exists;
use function interface_exists;
use function ltrim;
use function sprintf;
use function version_compare;

/**
 * Fail-fast checks for the optional Web Push dependencies.
 *
 * Run at container compile time by {@see PwaExtension} when
 * {@code nowo_pwa.web_push.enabled} is true, so a missing PSR-17 implementation (the classic
 * "push silently never arrives" production bug) breaks {@code cache:clear} with an actionable message
 * instead of failing on the first delivery.
 */
final readonly class WebPushRequirements
{
    public const MINIMUM_WEB_PUSH_VERSION = '11.0.0';

    public const INSTALL_COMMAND = 'composer require minishlink/web-push:^11.0 nyholm/psr7';

    /** @var Closure(string): bool */
    private Closure $classExists;

    /** @var Closure(): ?string */
    private Closure $webPushVersion;

    /** @var Closure(): void */
    private Closure $findHttpFactories;

    /**
     * @param (Closure(string): bool)|null $classExists defaults to class_exists() || interface_exists()
     * @param (Closure(): ?string)|null $webPushVersion defaults to the Composer-installed minishlink/web-push version
     * @param (Closure(): void)|null $findHttpFactories defaults to PSR-17 request + stream factory discovery
     */
    public function __construct(
        ?Closure $classExists = null,
        ?Closure $webPushVersion = null,
        ?Closure $findHttpFactories = null,
    ) {
        $this->classExists       = $classExists ?? static fn (string $class): bool => class_exists($class) || interface_exists($class);
        $this->webPushVersion    = $webPushVersion ?? $this->installedWebPushVersion(...);
        $this->findHttpFactories = $findHttpFactories ?? static function (): void {
            Psr17FactoryDiscovery::findRequestFactory();
            Psr17FactoryDiscovery::findStreamFactory();
        };
    }

    /**
     * Library + PSR-17 factories (everything {@see MinishlinkWebPushSender} needs besides a PSR-18 client).
     *
     * @throws WebPushDependencyException
     */
    public function assertSatisfied(): void
    {
        $this->assertLibraryInstalled();
        $this->assertHttpFactoriesAvailable();
    }

    /**
     * @throws WebPushDependencyException when minishlink/web-push is missing or older than 11.0
     */
    public function assertLibraryInstalled(): void
    {
        if (!($this->classExists)(WebPush::class)) {
            throw new WebPushDependencyException(sprintf('nowo_pwa.web_push.enabled is true but minishlink/web-push is not installed. Run: %s', self::INSTALL_COMMAND));
        }

        $version = ($this->webPushVersion)();
        if ($version !== null && version_compare(ltrim($version, 'v'), self::MINIMUM_WEB_PUSH_VERSION, '<')) {
            throw new WebPushDependencyException(sprintf('nowo_pwa.web_push requires minishlink/web-push >= %s (installed: %s). Run: %s', self::MINIMUM_WEB_PUSH_VERSION, $version, self::INSTALL_COMMAND));
        }
    }

    /**
     * @throws WebPushDependencyException when no PSR-17 request/stream factory can be discovered
     */
    public function assertHttpFactoriesAvailable(): void
    {
        try {
            ($this->findHttpFactories)();
        } catch (Throwable $e) {
            throw new WebPushDependencyException(self::missingHttpImplementationMessage(), 0, $e);
        }
    }

    /**
     * @throws WebPushDependencyException when the test-notification endpoint cannot be protected (no security / CSRF component)
     */
    public function assertSecurityAvailable(): void
    {
        foreach ([AuthorizationCheckerInterface::class => 'symfony/security-bundle', CsrfTokenManagerInterface::class => 'symfony/security-csrf'] as $class => $package) {
            if (!($this->classExists)($class)) {
                throw new WebPushDependencyException(sprintf('nowo_pwa.web_push.test_notification.enabled requires %s (authenticated, CSRF-protected endpoint). Run: composer require %s', $package, $package));
            }
        }
    }

    public static function missingHttpImplementationMessage(): string
    {
        return sprintf(
            'Web Push needs a PSR-17 HTTP factory (and a PSR-18 client) to build push requests, but none was found. Run: composer require nyholm/psr7 (and symfony/http-client if no PSR-18 client is installed). Full install: %s',
            self::INSTALL_COMMAND,
        );
    }

    private function installedWebPushVersion(): ?string
    {
        return InstalledVersions::isInstalled('minishlink/web-push')
            ? InstalledVersions::getPrettyVersion('minishlink/web-push')
            : null;
    }
}
