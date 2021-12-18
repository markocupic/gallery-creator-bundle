<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * sdfdfsdfsdfsdf
 *
 * @license LGPL-3.0-or-later
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
