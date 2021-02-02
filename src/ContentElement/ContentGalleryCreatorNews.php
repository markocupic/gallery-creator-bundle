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

/**
 * Run in a custom namespace, so the class can be replaced.
 */

namespace Markocupic\GalleryCreatorBundle\ContentElement;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\ContentElement;
use Contao\Date;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\GalleryCreatorAlbumsModel;
use Contao\Input;
use Contao\Pagination;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Patchwork\Utf8;

/**
 * Class ContentGalleryCreatorNews.
 */
class ContentGalleryCreatorNews extends ContentElement
{
    protected $strTemplate = 'ce_gallery_creator_news';

    protected $intAlbumId;

    /**
     * Set the template.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['CTE']['gallery_creator_ce_news'][0]).' ###';
            $objTemplate->title = $this->headline;

            // for module use only
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        if (!$this->gc_publish_single_album) {
            return '';
        }

        $objAlbum = $this->Database->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=? AND published=?')->execute($this->gc_publish_single_album, '1');

        // if the album doesn't exist
        if (!$objAlbum->numRows) {
            return '';
        }

        $this->intAlbumId = $objAlbum->id;

        // Check Permission for protected albums
        if (TL_MODE === 'FE' && $objAlbum->protected) {
            $blnAllowed = false;
            $this->import('FrontendUser', 'User');

            if (FE_USER_LOGGED_IN && \is_array(unserialize($this->User->allGroups))) {
                // Check if logged in user is in the allowed group
                if (array_intersect(unserialize($this->User->allGroups), unserialize($objAlbum->groups))) {
                    $blnAllowed = true;
                }
            }

            if (!$blnAllowed) {
                return '';
            }
        }

        // Assigning the frontend template
        $this->strTemplate = '' !== $this->gc_template ? $this->gc_template : $this->strTemplate;
        $this->Template = new FrontendTemplate($this->strTemplate);

        return parent::generate();
    }

    /**
     * Generate module.
     */
    protected function compile(): void
    {
        // Get the album object
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->intAlbumId);

        // Init the counter
        ContentGalleryCreator::initCounter($this->intAlbumId);

        // Pagination settings
        $limit = $this->gc_ThumbsPerPage;

        if ($limit > 0) {
            $page = Input::get('page') ?: 1;
            $offset = ($page - 1) * $limit;

            // Count pictures
            $objPictures = $this->Database->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')->execute('1', $this->intAlbumId);
            $itemsTotal = $objPictures->numRows;

            // Create the pagination menu
            $numberOfLinks = $this->gc_PaginationNumberOfLinks < 1 ? 7 : $this->gc_PaginationNumberOfLinks;
            $objPagination = new Pagination($itemsTotal, $limit, $numberOfLinks);
            $this->Template->pagination = $objPagination->generate("\n ");
        }

        // Picture sorting
        $str_sorting = empty($this->gc_picture_sorting) || empty($this->gc_picture_sorting_direction) ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;

        // Sort by name is done below
        $str_sorting = str_replace('name', 'id', $str_sorting);
        $objPictures = $this->Database->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting);

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
            $arrPictures[$objPictures->id] = GcHelper::getPictureInformationArray($objPictures->id, $this);
        }

        // Sort by basename
        if ('name' === $this->gc_picture_sorting) {
            if ('ASC' === $this->gc_picture_sorting_direction) {
                array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_ASC);
            } else {
                array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_DESC);
            }
        }

        $arrPictures = array_values($arrPictures);

        // Store $arrPictures in the template variable
        $this->Template->arrPictures = $arrPictures;

        // Generate other template variables
        $this->getAlbumTemplateVars($this->intAlbumId);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $this->import($callback[0]);
                $this->Template = $this->$callback[0]->$callback[1]($this, $objAlbum);
            }
        }
    }

    /**
     * Set the template-vars to the template object for the selected album.
     *
     * @param $intAlbumId
     */
    protected function getAlbumTemplateVars($intAlbumId): void
    {
        global $objPage;

        // Load the current album from db
        $objAlbum = $this->Database->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')->execute($intAlbumId);

        $objPage->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $objPage->description;
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');

        // Store all album-data in the array
        $objAlbum->reset();
        $this->Template->arrAlbumdata = $objAlbum->fetchAssoc();

        // Albumname
        $this->Template->Albumname = $objAlbum->name;
        // Album visitors
        $this->Template->visitors = $objAlbum->vistors;
        // Album caption
        $this->Template->albumComment = StringUtil::toHtml5($objAlbum->comment);
        // Insert article pre
        $this->Template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        // Insert article after
        $this->Template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        // event date as unix timestamp
        $this->Template->eventTstamp = $objAlbum->date;
        // formated event date
        $this->Template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        // Margins
        $this->Template->imagemargin = $this->generateMargin(StringUtil::deserialize($this->gc_imagemargin_detailview), 'margin');
        // Cols per row
        $this->Template->colsPerRow = empty($this->gc_rows) ? 4 : $this->gc_rows;

        $this->Template->objElement = $this;
    }
}
