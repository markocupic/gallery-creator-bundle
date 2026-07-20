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

namespace Markocupic\GalleryCreatorBundle\EventListener;

use Markocupic\GalleryCreatorBundle\Event\ImagePostInsertEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * This is a demo class!
 */
#[AsEventListener(priority: 100)]
class GalleryCreatorImagePostInsertListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function __invoke(ImagePostInsertEvent $event): void
    {
        /*
        $picturesModel = $event->getPicturesModel();
        $user = $this->security->getUser();

        // E.g. automatically add a caption to the uploaded image
        if ($user instanceof BackendUser && $user->name) {
            $picturesModel->caption = 'Holidays '.date('Y').', Photo: '.$user->name;
            $picturesModel->save();
        }
        */
    }
}
