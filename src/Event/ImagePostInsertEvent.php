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

namespace Markocupic\GalleryCreatorBundle\Event;

use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched right after a new image has been uploaded and written to the database.
 *
 * Listeners may adapt the picture entity, e.g. by setting a caption via
 * $event->getPicturesModel()->caption = '...'; and calling ->save() on the model.
 */
class ImagePostInsertEvent extends Event
{
    public function __construct(private readonly GalleryCreatorPicturesModel $picturesModel)
    {
    }

    public function getPicturesModel(): GalleryCreatorPicturesModel
    {
        return $this->picturesModel;
    }
}
