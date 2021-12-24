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

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ContentElement(GalleryCreatorNewsController::TYPE, category="gallery_creator_elements")
 */
class GalleryCreatorNewsController extends AbstractGalleryCreatorController
{
    public const TYPE = 'gallery_creator_news';

    private AlbumUtil $albumUtil;

    private Connection $connection;

    private PictureUtil $pictureUtil;

    private RequestStack $requestStack;

    private SecurityUtil $securityUtil;

    private ScopeMatcher $scopeMatcher;

    private ?GalleryCreatorAlbumsModel $activeAlbum = null;

    private ?ContentModel $model = null;

    private ?PageModel $pageModel = null;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, PictureUtil $pictureUtil, RequestStack $requestStack, SecurityUtil $securityUtil, ScopeMatcher $scopeMatcher)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->pictureUtil = $pictureUtil;
        $this->requestStack = $requestStack;
        $this->securityUtil = $securityUtil;
        $this->scopeMatcher = $scopeMatcher;

        parent::__construct($albumUtil, $connection, $pictureUtil, $scopeMatcher);
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($this->scopeMatcher->isBackendRequest($request)) {
            $twig = System::getContainer()->get('twig');

            return new Response(
                $twig->render('@MarkocupicGalleryCreator/Backend/backend_element_view.html.twig', [])
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        if (!$this->model->gcPublishSingleAlbum) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->activeAlbum = GalleryCreatorAlbumsModel::findOneBy(
            ['tl_gallery_creator_albums.id = ? AND tl_gallery_creator_albums.published = ?'],
            [$this->model->gcPublishSingleAlbum, '1']
        );

        // Return empty response if the album doesn't exist
        if (null === $this->activeAlbum) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Check permission for protected albums
        if (!$this->securityUtil->isAuthorized($this->activeAlbum)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    /**
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     *
     * @return Response|null
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        // Add the picture collection and the pagination to the template.
        $this->addAlbumPicturesToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

        // Augment template with more properties.
        $this->addAlbumToTemplate($this->activeAlbum, $model, $template, $this->pageModel);

        // Count views
        $this->albumUtil->countAlbumViews($this->activeAlbum);

        // Add content model to template.
        $template->content = $model->row();

        // Add meta tags to the page header.
        $this->addMetaTagsToPage($this->pageModel, $this->activeAlbum);

        // Trigger gcGenerateFrontendTemplateHook
        $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

        return $template->getResponse();
    }

    /**
     * Augment template with some more properties of the active album.
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, Template $template, PageModel $pageModel): void
    {
        parent::addAlbumToTemplate($albumModel, $contentModel, $template, $pageModel);
    }
}
