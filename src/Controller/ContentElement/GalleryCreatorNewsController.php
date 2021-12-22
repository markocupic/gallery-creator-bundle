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

    /**
     * @var AlbumUtil
     */
    private AlbumUtil $albumUtil;

    /**
     * @var PictureUtil
     */
    private PictureUtil $pictureUtil;

    /**
     * @var ScopeMatcher
     */
    private ScopeMatcher $scopeMatcher;


    private ?int $intAlbumId = null;


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

        $objAlbum = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=? AND published=?')
            ->execute($this->model->gcPublishSingleAlbum, '1')
        ;

        // if the album doesn't exist
        if (!$objAlbum->numRows) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->intAlbumId = (int) $objAlbum->id;

        // Check Permission for protected albums
        if ($request && $this->scopeMatcher->isFrontendRequest($request) && $objAlbum->protected) {
            $blnAllowed = false;

            $objUser = FrontendUser::getInstance();

            if (FE_USER_LOGGED_IN && null !== $objUser && \is_array(unserialize($objUser->allGroups))) {
                // Check if logged in user is in the allowed group
                if (array_intersect(unserialize($objUser->allGroups), unserialize($objAlbum->groups))) {
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
        // Get the album object
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->intAlbumId);

        // Init the counter
        $this->albumUtil->countAlbumViews($objAlbum);

        // Pagination settings
        $limit = $this->model->gcThumbsPerPage;

        if ($limit > 0) {
            $page = Input::get('page') ?: 1;
            $offset = ($page - 1) * $limit;

            // Count pictures
            $objPictures = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')
                ->execute('1', $this->intAlbumId)
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
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting)
        ;

        if ($limit > 0) {
            $objPictures->limit($limit, $offset);
        }
        $objPictures = $objPictures->execute('1', $this->intAlbumId);

        // Build up $arrPictures
        $arrPictures = [];
        $auxBasename = [];

        while ($objPictures->next()) {
            $objFilesModel = FilesModel::findByUuid($objPictures->uuid);
            $basename = 'undefined';

            if (null !== $objFilesModel) {
                $basename = $objFilesModel->name;
            }
            $auxBasename[] = $basename;

            if (null !== ($objPicturesModel = GalleryCreatorPicturesModel::findByPk($objPictures->id))) {
                $arrPictures[$objPictures->id] = $this->pictureUtil->getPictureData($objPicturesModel, $this->model);
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
        $this->getAlbumTemplateVars($objAlbum, $template);

        /** @var System $systemAdapter */
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $template = $systemAdapter->importStatic($callback[0])->{$callback[1]}($this, $objAlbum);
            }
        }

        return $template->getResponse();
    }

    /**
     * Set the template-vars to the template object for the selected album.
     */
    private function getAlbumTemplateVars(GalleryCreatorAlbumsModel $objAlbum, Template &$template): void
    {
        $this->pageModel->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $this->pageModel->description;
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');

        // Store all album-data in the array
        $template->arrAlbumdata = $objAlbum->row();

        // Albumname
        $template->albumname = $objAlbum->name;
        // Album visitors
        $template->visitors = $objAlbum->vistors;
        // Album caption
        $template->caption = StringUtil::toHtml5($objAlbum->caption);
        // Insert article pre
        $template->insertArticlePre = $objAlbum->insertArticlePre ? sprintf('{{insert_article::%s}}', $objAlbum->insertArticlePre) : null;
        // Insert article after
        $template->insertArticlePost = $objAlbum->insertArticlePost ? sprintf('{{insert_article::%s}}', $objAlbum->insertArticlePost) : null;
        // event date as unix timestamp
        $template->eventTstamp = $objAlbum->date;
        // formated event date
        $template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        // Content model
        $template->objElement = $this->model;
    }
}
