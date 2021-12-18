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

namespace Markocupic\GalleryCreatorBundle\Listener\ContaoHook;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

/**
 * @Hook(GenerateGalleryCreatorFrontendTemplateListener::HOOK)
 */
class GenerateGalleryCreatorFrontendTemplateListener
{
    public const HOOK = 'gc_generateFrontendTemplate';

    public function __invoke(GalleryCreatorController $contentElement, Template $template, GalleryCreatorAlbumsModel $albumsModel = null): void
    {
        //$template->hallo = 'Lorem ipsum';
    }
}
