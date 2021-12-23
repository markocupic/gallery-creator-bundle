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

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Haste\Util\Pagination;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ContentElement(GalleryCreatorNewsController::TYPE, category="gallery_creator_elements")
 */
class GalleryCreatorNewsController extends AbstractContentElementController
{
    public const TYPE = 'gallery_creator_news';

    private Connection $connection;

    private AlbumUtil $albumUtil;

    private PictureUtil $pictureUtil;

    private ScopeMatcher $scopeMatcher;

    private ?GalleryCreatorAlbumsModel $activeAlbum = null;

    private ?ContentModel $model = null;

    private ?PageModel $pageModel = null;

    public function __construct(Connection $connection, AlbumUtil $albumUtil, PictureUtil $pictureUtil, ScopeMatcher $scopeMatcher)
    {
        $this->connection = $connection;
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
        $this->scopeMatcher = $scopeMatcher;
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

        // Check Permission for protected albums
        if ($this->scopeMatcher->isFrontendRequest($request) && $this->activeAlbum->protected) {
            $blnAllowed = false;

            $objUser = FrontendUser::getInstance();

            if (null !== $objUser && \is_array(unserialize($objUser->allGroups))) {
                // Check if logged-in user is in an allowed group
                if (array_intersect(unserialize($objUser->allGroups), unserialize($this->activeAlbum->groups))) {
                    $blnAllowed = true;
                }
            }

            if (!$blnAllowed) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }


    /**
     * @param Template $template
     * @param ContentModel $model
     * @param Request $request
     * @return Response|null
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        // Count pictures
        $itemsTotal = $this->connection->fetchOne('SELECT COUNT(id) as itemsTotal FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?', ['1', $this->activeAlbum->id]);

        $perPage = (int) $this->model->gcThumbsPerPage;

        $objPagination = new Pagination($itemsTotal, $perPage, 'page_g'.$this->model->id);

        if ($objPagination->isOutOfRange()) {
            throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
        }

        $template->pagination = $objPagination->generate();

        // Picture sorting
        $arrSorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? ['sorting', 'ASC'] : [$this->model->gcPictureSorting, $this->model->gcPictureSortingDirection];

        // Sort by name will be done below
        $arrSorting[0] = str_replace('name', 'id', $arrSorting[0]);

        $stmt = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tl_gallery_creator_pictures', 't')
            ->where('t.pid = :pid')
            ->andWhere('t.published = :published')
            ->orderBy(...$arrSorting)
            ->setParameter('published', '1')
            ->setParameter('pid', $this->activeAlbum->id)
            ->setFirstResult($objPagination->getOffset())
            ->setMaxResults($objPagination->getLimit())
            ->execute()
        ;

        $arrPictures = [];

        while (false !== ($rowPicture = $stmt->fetchAssociative())) {
            $filesModel = FilesModel::findByUuid($rowPicture['uuid']);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }

            if (null !== ($picturesModel = GalleryCreatorPicturesModel::findByPk($rowPicture['id']))) {
                // Prevent overriding items with same basename
                $arrPictures[$basename.'-id-'.$rowPicture['id']] = $this->pictureUtil->getPictureData($picturesModel, $this->model);
            }
        }

        // Sort by name
        if ('name' === $this->model->gcPictureSorting) {
            if ('ASC' === $this->model->gcPictureSortingDirection) {
                uksort($arrPictures, static fn ($a, $b): int => strnatcasecmp(basename($a), basename($b)));
            } else {
                uksort($arrPictures, static fn ($a, $b): int => -strnatcasecmp(basename($a), basename($b)));
            }
        }

        // Add pictures to the template
        $arrPictures = array_values($arrPictures);
        $template->arrPictures = $arrPictures;

        // Augment template with more properties
        $this->getAlbumTemplateVars($this->activeAlbum, $template);

        // Count views
        $this->albumUtil->countAlbumViews($this->activeAlbum);

        // Trigger gcGenerateFrontendTemplateHook
        $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

        return $template->getResponse();
    }

    protected function triggerGenerateFrontendTemplateHook(Template $template, GalleryCreatorAlbumsModel $albumModel = null): void
    {
        // Trigger the galleryCreatorGenerateFrontendTemplate - HOOK
        if (isset($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($this, $template, $albumModel);
            }
        }
    }

    /**
     * Set the template-vars to the template object for the selected album.
     */
    protected function getAlbumTemplateVars(GalleryCreatorAlbumsModel $albumModel, Template $template): void
    {
        // Add formatted date string to the album model
        $albumModel->dateFormatted = Date::parse(Config::get('dateFormat'), $albumModel->date);
        $template->album = $albumModel->row();

        // Add meta tags to the page object
        $this->pageModel->description = '' !== $albumModel->description ? StringUtil::specialchars($albumModel->description) : $this->pageModel->description;
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($albumModel->keywords), ',');
    }
}
