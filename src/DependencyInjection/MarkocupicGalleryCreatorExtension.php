<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\GalleryCreatorBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MarkocupicGalleryCreatorExtension.
 */
class MarkocupicGalleryCreatorExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yml');

        // Configuration
        $container->setParameter('markocupic_gallery_creator.upload_path', $config['upload_path']);
        $container->setParameter('markocupic_gallery_creator.backend_write_protection', $config['backend_write_protection']);
        $container->setParameter('markocupic_gallery_creator.copy_images_on_import', $config['copy_images_on_import']);
        $container->setParameter('markocupic_gallery_creator.read_exif_meta_data', $config['read_exif_meta_data']);
    }
}
