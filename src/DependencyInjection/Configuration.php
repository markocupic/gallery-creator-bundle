<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * sdfdfsdfsdfsdf
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\GalleryCreatorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('markocupic_gallery_creator');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('upload_path')->cannotBeEmpty()->end()
            ->booleanNode('backend_write_protection')
            ->info('Activate album protection so that only album owners have write access to the picture settings (e.g. edit caption, edit location...). Default to false.')
            ->end()
            ->booleanNode('copy_images_on_import')
            ->info('Make a copy of the original when importing images from an foreign directory. Default to true.')
            ->end()
            ->booleanNode('read_exif_meta_data')
            ->info('Read exif meta data from file. Default to false.')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
