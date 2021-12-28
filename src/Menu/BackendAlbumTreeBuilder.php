<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BackendAlbumTreeBuilder
{
    /**
     * @internal Do not inherit from this class; decorate the "contao.menu.backend_menu_builder" service instead
     */
    public function __construct(FactoryInterface $factory, EventDispatcherInterface $eventDispatcher)
    {
        $this->factory = $factory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function buildBackendAlbumTree(array $options): ItemInterface
    {
        //die(print_r($options,true));
        $menu = $this->factory->createItem('gc_album_tree');
        $menu = $options['menu'];
        $menu->addChild('Home');
        $menu->addChild('Home2');

        // ... add more children

        return $menu;
    }
}
