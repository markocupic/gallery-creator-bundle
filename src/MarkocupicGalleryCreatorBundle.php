<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MarkocupicGalleryCreatorBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
