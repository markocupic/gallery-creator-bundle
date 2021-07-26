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
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\FilesModel;
use Contao\FrontendUser;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class GalleryCreatorNewsController.
 */
class GalleryCreatorNewsController extends AbstractContentElementController
{
    private $intAlbumId;

    private $model;

    private $pageModel;

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        $this->model = $model;
        $this->pageModel = $pageModel;

        if (!$this->model->gc_publish_single_album) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $objAlbum = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=? AND published=?')
            ->execute($this->model->gc_publish_single_album, '1')
        ;

        // if the album doesn't exist
        if (!$objAlbum->numRows) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->intAlbumId = $objAlbum->id;

        // Check Permission for protected albums
        if (TL_MODE === 'FE' && $objAlbum->protected) {
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
        GcHelper::initCounter($objAlbum);

        // Pagination settings
        $limit = $this->model->gc_ThumbsPerPage;

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
            $numberOfLinks = $this->model->gc_PaginationNumberOfLinks < 1 ? 7 : $this->model->gc_PaginationNumberOfLinks;
            $objPagination = new Pagination($itemsTotal, $limit, $numberOfLinks);
            $template->pagination = $objPagination->generate("\n ");
        }

        // Picture sorting
        $str_sorting = empty($this->model->gc_picture_sorting) || empty($this->model->gc_picture_sorting_direction) ? 'sorting ASC' : $this->model->gc_picture_sorting.' '.$this->model->gc_picture_sorting_direction;

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
                $arrPictures[$objPictures->id] = GcHelper::getPictureInformationArray($objPicturesModel, $this->model);
            }
        }

        // Sort by basename
        if ('name' === $this->model->gc_picture_sorting) {
            if ('ASC' === $this->model->gc_picture_sorting_direction) {
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
        $template->Albumname = $objAlbum->name;
        // Album visitors
        $template->visitors = $objAlbum->vistors;
        // Album caption
        $template->albumComment = StringUtil::toHtml5($objAlbum->comment);
        // Insert article pre
        $template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        // Insert article after
        $template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        // event date as unix timestamp
        $template->eventTstamp = $objAlbum->date;
        // formated event date
        $template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        // Margins
        $template->imagemargin = Controller::generateMargin(StringUtil::deserialize($this->model->gc_imagemargin_detailview), 'margin');
        // Cols per row
        $template->colsPerRow = empty($this->model->gc_rows) ? 4 : $this->model->gc_rows;

        $template->objElement = $this->model;
    }
}
