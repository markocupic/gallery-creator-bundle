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

namespace Markocupic\GalleryCreatorBundle\Dto;

use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Image\Studio\Figure;

readonly class GalleryAlbumDto
{
    public function __construct(
        public Figure|null $figure,
        public Metadata $metadata,
        public array $data,
    ) {
    }

    public function __isset(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key];
    }
}
