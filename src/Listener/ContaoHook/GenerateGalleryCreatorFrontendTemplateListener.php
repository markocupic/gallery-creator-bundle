<?php

namespace Markocupic\GalleryCreatorBundle\Listener\ContaoHook;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

/**
 * @Hook(GenerateGalleryCreatorFrontendTemplateListener::TYPE)
 */
class GenerateGalleryCreatorFrontendTemplateListener
{
    public const TYPE = 'gc_generateFrontendTemplate';

    public function __invoke(GalleryCreatorController $contentElement, Template $template, ?GalleryCreatorAlbumsModel $albumsModel = null)
    {
        //$template->hallo = 'Lorem ipsum';
    }
}
