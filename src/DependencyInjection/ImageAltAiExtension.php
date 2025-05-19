<?php

declare(strict_types=1);

namespace Bluebranch\ImageAltAi\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ImageAltAiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        ))->load('services.yml');
    }
}
