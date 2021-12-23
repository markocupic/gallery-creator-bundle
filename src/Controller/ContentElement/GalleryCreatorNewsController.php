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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Database;
use Contao\Date;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
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

    private AlbumUtil $albumUtil;

    private PictureUtil $pictureUtil;

    private ScopeMatcher $scopeMatcher;

    private ?GalleryCreatorAlbumsModel $activeAlbum = null;

    private ?ContentModel $model = null;

    private ?PageModel $pageModel = null;

    public function __construct(AlbumUtil $albumUtil, PictureUtil $pictureUtil, ScopeMatcher $scopeMatcher)
    {
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($request && $this->scopeMatcher->isBackendRequest($request)) {
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

        // if the album doesn't exist
        if (null === $this->activeAlbum) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Check Permission for protected albums
        if ($request && $this->scopeMatcher->isFrontendRequest($request) && $this->activeAlbum->protected) {
            $blnAllowed = false;

            $objUser = FrontendUser::getInstance();

            if (FE_USER_LOGGED_IN && null !== $objUser && \is_array(unserialize($objUser->allGroups))) {
                // Check if logged in user is in the allowed group
                if (array_intersect(unserialize($objUser->allGroups), unserialize($this->activeAlbum->groups))) {
                    $blnAllowed = true;
                }
            }

            if (!$blnAllowed) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $this->model, $section, $classes, $pageModel);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        // Init the counter
        $this->albumUtil->countAlbumViews($this->activeAlbum);

        // Pagination settings
        $limit = $this->model->gcThumbsPerPage;

        if ($limit > 0) {
            $page = Input::get('page') ?: 1;
            $offset = ($page - 1) * $limit;

            // Count pictures
            $objPictures = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?')
                ->execute('1', $this->activeAlbum->id)
            ;
            $itemsTotal = $objPictures->numRows;

            // Create the pagination menu
            $objPagination = new Pagination($itemsTotal, $limit);
            $template->pagination = $objPagination->generate("\n ");
        }

        // Picture sorting
        $str_sorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;

        // Sort by name is done below
        $str_sorting = str_replace('name', 'id', $str_sorting);
        $objPictures = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ? ORDER BY '.$str_sorting)
        ;

        if ($limit > 0) {
            $objPictures->limit($limit, $offset);
        }
        $objPictures = $objPictures->execute('1', $this->activeAlbum->id);

        // Build up $arrPictures
        $arrPictures = [];
        $auxBasename = [];

        while ($objPictures->next()) {
            $filesModel = FilesModel::findByUuid($objPictures->uuid);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }
            $auxBasename[] = $basename;

            if (null !== ($picturesModel = GalleryCreatorPicturesModel::findByPk($objPictures->id))) {
                $arrPictures[$objPictures->id] = $this->pictureUtil->getPictureData($picturesModel, $this->model);
            }
        }

        // Sort by basename
        if ('name' === $this->model->gcPictureSorting) {
            if ('ASC' === $this->model->gcPictureSortingDirection) {
                array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_ASC);
            } else {
                array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_DESC);
            }
        }

        $arrPictures = array_values($arrPictures);

        // Store $arrPictures in the template variable
        $template->arrPictures = $arrPictures;

        // Generate other template variables
        $this->getAlbumTemplateVars($this->activeAlbum, $template);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'] as $callback) {
                $template = System::importStatic($callback[0])->{$callback[1]}($this, $this->activeAlbum);
            }
        }

        return $template->getResponse();
    }

    /**
     * @todo Refactoring needed
     * Set the template-vars to the template object for the selected album.
     */
    private function getAlbumTemplateVars(GalleryCreatorAlbumsModel $albumsModel, Template &$template): void
    {
        $this->pageModel->description = '' !== $albumsModel->description ? StringUtil::specialchars($albumsModel->description) : $this->pageModel->description;
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($albumsModel->keywords), ',');

        // Store all album-data in the array
        $template->arrAlbumdata = $albumsModel->row();

        // Albumname
        $template->albumname = $albumsModel->name;
        // Album visitors
        $template->visitors = $albumsModel->vistors;
        // Album caption
        $template->caption = StringUtil::toHtml5($albumsModel->caption);
        // Insert article pre
        $template->insertArticlePre = $albumsModel->insertArticlePre ? sprintf('{{insert_article::%s}}', $albumsModel->insertArticlePre) : null;
        // Insert article after
        $template->insertArticlePost = $albumsModel->insertArticlePost ? sprintf('{{insert_article::%s}}', $albumsModel->insertArticlePost) : null;
        // event date as unix timestamp
        $template->eventTstamp = $albumsModel->date;
        // formated event date
        $template->eventDate = Date::parse(Config::get('dateFormat'), $albumsModel->date);
        // Content model
        $template->objElement = $this->model;
    }
}
