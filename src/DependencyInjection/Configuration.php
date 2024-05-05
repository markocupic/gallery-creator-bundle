<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('markocupic_gallery_creator');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('upload_path')
                    ->cannotBeEmpty()
                    ->defaultValue('files/gallery_creator_albums')
                ->end()
                ->booleanNode('copy_images_on_import')
                    ->info('Make a copy of the original when importing images from an foreign directory. Default to true.')
                    ->defaultTrue()
                ->end()
                    ->booleanNode('read_exif_meta_data')
                    ->info('Read exif meta data from file. Default to false.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('valid_extensions')
                    ->prototype('scalar')->end()
                    ->useAttributeAsKey('name')
                    ->defaultValue(['jpg', 'jpeg', 'gif', 'png', 'webp', 'svg', 'svgz', 'webp'])
                ->end()

            ->end()
        ;

        return $treeBuilder;
    }
}
