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

namespace Markocupic\GalleryCreatorBundle\EventListener\ContaoHook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * This is a demo class!
 */
#[AsHook(GalleryCreatorImagePostInsertListener::HOOK, priority: 100)]
class GalleryCreatorImagePostInsertListener
{
    public const HOOK = 'galleryCreatorImagePostInsert';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function __invoke(GalleryCreatorPicturesModel $picturesModel): void
    {
        /*
        $user = $this->security->getUser();

        // E.g automatically add a caption to the uploaded image
        if ($user instanceof BackendUser && $user->name) {
            $picturesModel->caption = 'Holidays '.date('Y').', Photo: '.$user->name;
            $picturesModel->save();
        }
        */
    }
}
