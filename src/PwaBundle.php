<?php

declare(strict_types=1);

namespace Nowo\PwaBundle;

use Nowo\PwaBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\PwaBundle\DependencyInjection\PwaExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Progressive Web App integration for Symfony applications.
 */
final class PwaBundle extends Bundle
{
    public const TRANSLATION_DOMAIN = 'NowoPwaBundle';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PwaExtension();
        }

        return $this->extension;
    }
}
