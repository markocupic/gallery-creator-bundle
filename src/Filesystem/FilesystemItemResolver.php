<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Filesystem;

use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\FilesystemUtil;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FilesystemItemResolver
{
    public function __construct(
        #[Autowire('@contao.filesystem.virtual.files')]
        private readonly VirtualFilesystem $filesStorage,
    ) {
    }

    public function first(mixed $uuid): FilesystemItem|null
    {
        return FilesystemUtil::listContentsFromSerialized($this->filesStorage, $uuid)
            ->first()
        ;
    }
}
