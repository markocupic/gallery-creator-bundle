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

use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the gallery creator frontend template is parsed.
 *
 * Listeners may adapt the frontend output, e.g. by adding template variables
 * via $event->getTemplate()->set('foo', 'bar'). The event carries the content
 * element controller, the template object and the active album (if there is one).
 */
class GenerateFrontendTemplateEvent extends Event
{
    public function __construct(
        private readonly AbstractContentElementController $contentElement,
        private readonly FragmentTemplate $template,
        private readonly Request $request,
        private readonly GalleryCreatorAlbumsModel|null $albumsModel = null,
    ) {
    }

    public function getContentElement(): AbstractContentElementController
    {
        return $this->contentElement;
    }

    public function getTemplate(): FragmentTemplate
    {
        return $this->template;
    }

    public function getAlbumsModel(): GalleryCreatorAlbumsModel|null
    {
        return $this->albumsModel;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
