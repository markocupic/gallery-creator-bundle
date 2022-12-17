<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Listener\ContaoHook;

use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

/**
 * This is a demo class!
 */
#[AsHook(GalleryCreatorFrontendTemplateListener::HOOK, priority: 100)]
class GalleryCreatorFrontendTemplateListener
{
    public const HOOK = 'galleryCreatorGenerateFrontendTemplate';

    public function __invoke(AbstractContentElementController $contentElement, Template $template, GalleryCreatorAlbumsModel $albumsModel = null): void
    {
        //$template->foo = 'bar';
    }
}
