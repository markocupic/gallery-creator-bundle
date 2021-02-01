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

namespace Markocupic\GalleryCreatorBundle\ContentElement;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\ContentElement;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\GalleryCreatorAlbumsModel;
use Contao\GalleryCreatorPicturesModel;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\Validator;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Patchwork\Utf8;

/**
 * Class ContentGalleryCreator.
 */
class ContentGalleryCreator extends ContentElement
{
    public $defaultThumb = 'bundles/markocupicgallerycreator/images/image_not_found.jpg';

    protected $strTemplate = 'ce_gc_default';

    protected $viewMode;

    protected $intAlbumId;

    protected $strAlbumalias;

    protected $arrSelectedAlbums;

    /**
     * Set the template.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['CTE']['gallery_creator_ce'][0]).' ###';

            $objTemplate->title = $this->headline;

            // for module use only
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        // unset the Session
        unset($_SESSION['gallery_creator']['CURRENT_ALBUM']);

        if ($_SESSION['GcRedirectToAlbum']) {
            Input::setGet('items', $_SESSION['GcRedirectToAlbum']);
            unset($_SESSION['GcRedirectToAlbum']);
        }

        // Ajax Requests
        if (TL_MODE === 'FE' && Environment::get('isAjaxRequest')) {
            $this->generateAjax();
        }

        // set the item from the auto_item parameter
        if (Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('items', Input::get('auto_item'));
        }

        if (!empty(Input::get('items'))) {
            $this->viewMode = 'detail_view';
        }

        // store the pagination variable page in the current session
        if (!Input::get('items')) {
            unset($_SESSION['gallery_creator']['PAGINATION']);
        }

        if (Input::get('page') && 'detail_view' !== $this->viewMode) {
            $_SESSION['gallery_creator']['PAGINATION'] = Input::get('page');
        }

        if ($this->gc_publish_all_albums) {
            // if all albums should be shown
            $this->arrSelectedAlbums = $this->listAllAlbums();
        } else {
            // if only selected albums should be shown
            $this->arrSelectedAlbums = StringUtil::deserialize($this->gc_publish_albums, true);
        }

        // clean array from unpublished or empty or protected albums
        foreach ($this->arrSelectedAlbums as $key => $albumId) {
            // Get all not empty albums
            $objAlbum = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE (SELECT COUNT(id) FROM tl_gallery_creator_pictures WHERE pid = ? AND published=?) > 0 AND id=? AND published=?')
                ->execute($albumId, 1, $albumId, 1)
            ;

            // if the album doesn't exist
            if (!$objAlbum->numRows && !GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) && !$this->gc_hierarchicalOutput) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }

            // remove id from $this->arrSelectedAlbums if user is not allowed
            if (TL_MODE === 'FE' && true === $objAlbum->protected) {
                if (!$this->authenticate($objAlbum->alias)) {
                    unset($this->arrSelectedAlbums[$key]);
                    continue;
                }
            }
        }
        // build up the new array
        $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

        // abort if no album is selected
        if (empty($this->arrSelectedAlbums)) {
            return '';
        }

        // Detail view:
        // Authenticate and get album alias and album id
        if (Input::get('items')) {
            $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $objAlbum) {
                $this->intAlbumId = $objAlbum->id;
                $this->strAlbumalias = $objAlbum->alias;
                $this->viewMode = 'detail_view';
            } else {
                return '';
            }

            //Authentifizierung bei vor Zugriff geschuetzten Alben, dh. der Benutzer bekommt, wenn nicht berechtigt, nur das Albumvorschaubild zu sehen.
            if (!$this->authenticate($this->strAlbumalias)) {
                return '';
            }
        }

        $this->viewMode = $this->viewMode ?: 'list_view';
        $this->viewMode = !empty(Input::get('img')) ? 'single_image' : $this->viewMode;

        if ('list_view' === $this->viewMode) {
            // Redirect to detailview if there is only one album
            if (1 === \count($this->arrSelectedAlbums) && $this->gc_redirectSingleAlb) {
                $_SESSION['GcRedirectToAlbum'] = GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[0])->alias;
                $this->reload();
            }

            //Hierarchische Ausgabe
            if ($this->gc_hierarchicalOutput) {
                foreach ($this->arrSelectedAlbums as $k => $albumId) {
                    $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);

                    if ($objAlbum->pid > 0) {
                        unset($this->arrSelectedAlbums[$k]);
                    }
                }
                $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

                if (empty($this->arrSelectedAlbums)) {
                    return '';
                }
            }
        }

        if ('detail_view' === $this->viewMode) {
            // for security reasons...
            if (!$this->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false)) {
                return '';
            }
        }

        //assigning the frontend template
        $this->strTemplate = !empty($this->gc_template) ? $this->gc_template : $this->strTemplate;
        $this->Template = new FrontendTemplate($this->strTemplate);

        return parent::generate();
    }

    /**
     * responds to ajax-requests.
     *
     * @return string
     */
    public function generateAjax()
    {
        //gibt ein Array mit allen Bildinformationen des Bildes mit der id imageId zurueck
        if (Input::get('isAjax') && Input::get('getImageByPk') && !empty(Input::get('id'))) {
            $arrPicture = $this->getPictureInformationArray(Input::get('id'), null, Input::get('action'));

            echo json_encode($arrPicture);
            exit;
        }

        // Send image-date from a certain album as JSON encoded array to the browser
        // used f.ex. for the ce_gc_colorbox.html template --> https://gist.github.com/markocupic/327413038262b2f84171f8df177cf021
        if (Input::get('isAjax') && Input::get('getImagesByPid') && Input::get('pid')) {
            // Do not send data if album is protected and the user has no access
            $objAlbum = Database::getInstance()
                ->prepare('SELECT alias FROM tl_gallery_creator_albums WHERE id=?')
                ->execute(Input::get('albumId'))
            ;

            if (!$this->authenticate($objAlbum->alias)) {
                return false;
            }

            // Init visit counter
            $this->initCounter(Input::get('pid'));

            // sorting direction
            $sorting = $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;

            $objPicture = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$sorting)
                ->execute(1, Input::get('pid'))
            ;

            $response = [];

            while ($objPicture->next()) {
                $objFile = FilesModel::findByUuid($objPicture->uuid);

                $href = $objFile->path;
                $href = !empty(trim((string) $objPicture->socialMediaSRC)) ? trim((string) $objPicture->socialMediaSRC) : $href;
                $href = !empty(trim((string) $objPicture->localMediaSRC)) ? trim((string) $objPicture->localMediaSRC) : $href;
                $arrPicture = [
                    'href' => StringUtil::specialchars($href),
                    'pid' => $objPicture->pid,
                    'caption' => StringUtil::specialchars($objPicture->comment),
                    'id' => $objPicture->id,
                    'uuid' => StringUtil::binToUuid($objFile->uuid),
                ];
                $response[] = array_merge($objPicture->row(), $arrPicture);
            }
            echo json_encode(['src' => $response, 'success' => 'true']);
            exit;
        }
    }

    /**
     * Returns the path to the preview-thumbnail of an album.
     *
     * @param $intAlbumId
     *
     * @return array
     */
    public function getAlbumPreviewThumb($intAlbumId)
    {
        $thumbSRC = $this->defaultThumb;

        // Check for an alternate thumbnail
        if ('' !== Config::get('gc_error404_thumb')) {
            if (Validator::isStringUuid(Config::get('gc_error404_thumb'))) {
                $objFile = FilesModel::findByUuid(StringUtil::uuidToBin(Config::get('gc_error404_thumb')));

                if (null !== $objFile) {
                    if (is_file(TL_ROOT.'/'.$objFile->path)) {
                        $thumbSRC = $objFile->path;
                    }
                }
            }
        }

        // Predefine thumb
        $arrThumb = [
            'name' => basename($thumbSRC),
            'path' => $thumbSRC,
        ];

        $objAlb = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null !== $objAlb->thumb) {
            $objPreviewThumb = GalleryCreatorPicturesModel::findByPk($objAlb->thumb);
        } else {
            $objPreviewThumb = GalleryCreatorPicturesModel::findOneByPid($intAlbumId);
        }

        if (null !== $objPreviewThumb) {
            $oFile = FilesModel::findByUuid($objPreviewThumb->uuid);

            if (null !== $oFile) {
                if (is_file(TL_ROOT.'/'.$oFile->path)) {
                    $arrThumb = [
                        'name' => basename($oFile->path),
                        'path' => $oFile->path,
                    ];
                }
            }
        }

        return $arrThumb;
    }

    /**
     * Hilfsmethode
     * generiert den back-Link.
     *
     * @param string $intAlbumId
     * @param int
     *
     * @return string
     */
    public function generateBackLink($intAlbumId)
    {
        global $objPage;

        if (TL_MODE === 'BE') {
            return false;
        }

        // Get the page model
        $objPageModel = PageModel::findByPk($objPage->id);

        //generiert den Link zum Parent-Album
        if ($this->gc_hierarchicalOutput && GalleryCreatorAlbumsModel::getParentAlbum($intAlbumId)) {
            $arrParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($intAlbumId);

            return ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$arrParentAlbum['alias']));
        }

        //generiert den Link zur Startuebersicht unter Beruecksichtigung der pagination
        $url = $objPageModel->getFrontendUrl();
        $url .= isset($_SESSION['gallery_creator']['PAGINATION']) ? '?page='.$_SESSION['gallery_creator']['PAGINATION'] : '';

        return ampersand($url);
    }

    /**
     * initCounter.
     *
     * @param int $intAlbumId
     */
    public static function initCounter($intAlbumId): void
    {
        if (preg_match('/bot|sp[iy]der|crawler|lib(?:cur|www)|search|archive/i', $_SERVER['HTTP_USER_AGENT'])) {
            // do not count spiders/bots
            return;
        }

        if (TL_MODE === 'FE') {
            $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

            if (strpos((string) $objAlbum->visitors_details, $_SERVER['REMOTE_ADDR'])) {
                // return if the visitor is allready registered
                return;
            }

            $arrVisitors = StringUtil::deserialize($objAlbum->visitors_details, true);
            // keep visiors data in the db unless 50 other users have visited the album
            if (50 === \count($arrVisitors)) {
                // slice the last position
                $arrVisitors = \array_slice($arrVisitors, 0, \count($arrVisitors) - 1);
            }

            //build up the array
            $newVisitor = [
                $_SERVER['REMOTE_ADDR'] => [
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'pid' => $intAlbumId,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'tstamp' => time(),
                    'url' => Environment::get('request'),
                ],
            ];

            if (!empty($arrVisitors)) {
                // insert the element to the beginning of the array
                array_unshift($arrVisitors, $newVisitor);
            } else {
                $arrVisitors[] = [$_SERVER['REMOTE_ADDR'] => $newVisitor];
            }

            // update database
            $objAlbum->visitors = ++$objAlbum->visitors;
            $objAlbum->visitors_details = serialize($arrVisitors);
            $objAlbum->save();
        }
    }

    /**
     * Generate module.
     */
    protected function compile(): void
    {
        global $objPage;

        switch ($this->viewMode) {
            case 'list_view':

                // pagination settings
                $limit = (int) $this->gc_AlbumsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->id;
                    $page = Input::get($id) ? \Input::get($id) : 1;
                    $offset = ($page - 1) * $limit;

                    // count albums
                    $itemsTotal = \count($this->arrSelectedAlbums);

                    // create pagination menu
                    $numberOfLinks = $this->gc_PaginationNumberOfLinks < 1 ? 7 : $this->gc_PaginationNumberOfLinks;
                    $objPagination = new Pagination($itemsTotal, $limit, $numberOfLinks, $id);
                    $this->Template->pagination = $objPagination->generate("\n ");
                }

                if (0 === $limit || $limit > \count($this->arrSelectedAlbums)) {
                    $limit = \count($this->arrSelectedAlbums);
                }
                $arrAlbums = [];

                for ($i = $offset; $i < $offset + $limit; ++$i) {
                    if (isset($this->arrSelectedAlbums[$i])) {
                        $arrAlbums[] = GcHelper::getAlbumInformationArray($this->arrSelectedAlbums[$i], $this);
                    }
                }

                // Add css classes
                if (\count($arrAlbums) > 0) {
                    $arrAlbums[0]['cssClass'] .= ' first';
                    $arrAlbums[\count($arrAlbums) - 1]['cssClass'] .= ' last';
                }

                $this->Template->imagemargin = $this->generateMargin(unserialize($this->gc_imagemargin_albumlisting));
                $this->Template->arrAlbums = $arrAlbums;

                // Call gcGenerateFrontendTemplateHook
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this);
                break;

            case 'detail_view':
                // generate the subalbum array
                if ($this->gc_hierarchicalOutput) {
                    $arrSubalbums = GcHelper::getSubalbumsInformationArray($this->intAlbumId, $this);
                    $this->Template->subalbums = \count($arrSubalbums) ? $arrSubalbums : null;
                }

                // count pictures
                $objTotal = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')
                    ->execute('1', $this->intAlbumId)
                ;
                $total = $objTotal->numRows;

                // pagination settings
                $limit = $this->gc_ThumbsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->id;
                    $page = null !== Input::get($id) ? Input::get($id) : 1;

                    // Do not index or cache the page if the page number is outside the range
                    if ($page < 1 || $page > max(ceil($total / $limit), 1)) {
                        /** @var \PageError404 $objHandler */
                        $objHandler = new $GLOBALS['TL_PTY']['error_404']();
                        $objHandler->generate($objPage->id);
                    }
                    $offset = ($page - 1) * $limit;

                    // create the pagination menu
                    $numberOfLinks = $this->gc_PaginationNumberOfLinks ?: 7;
                    $objPagination = new Pagination($total, $limit, $numberOfLinks, $id);
                    $this->Template->pagination = $objPagination->generate("\n  ");
                }

                // picture sorting
                $str_sorting = empty($this->gc_picture_sorting) || empty($this->gc_picture_sorting_direction) ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;

                // sort by name is done below
                $str_sorting = str_replace('name', 'id', $str_sorting);

                $objPictures = Database::getInstance()
                    ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting)
                ;

                if ($limit > 0) {
                    $objPictures->limit($limit, $offset);
                }
                $objPictures = $objPictures->execute(1, $this->intAlbumId);

                // build up $arrPictures
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

                // sort by basename
                if ('name' === $this->gc_picture_sorting) {
                    if ('ASC' === $this->gc_picture_sorting_direction) {
                        array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_ASC);
                    } else {
                        array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_DESC);
                    }
                }

                $arrPictures = array_values($arrPictures);

                // store $arrPictures in the template variable
                $this->Template->arrPictures = $arrPictures;

                // generate other template variables
                $this->getAlbumTemplateVars($this->intAlbumId);

                // init the counter
                $this->initCounter($this->intAlbumId);

                // Call gcGenerateFrontendTemplateHook
                $objAlbum = GalleryCreatorAlbumsModel::findByAlias($this->strAlbumalias);
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this, $objAlbum);
                break;

            case 'single_image':
                $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

                if (null === $objAlbum) {
                    throw new \Exception('Invalid album alias: '.Input::get('items'));
                }

                $objPic = Database::getInstance()
                    ->prepare("SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND name LIKE '".Input::get('img').".%'")
                    ->execute($objAlbum->id)
                ;

                if (!$objPic->numRows) {
                    throw new \Exception(sprintf('File with filename "%s" does not exist in album with alias "%s".', Input::get('img'), Input::get('items')));
                }

                $picId = $objPic->id;
                $published = $objPic->published ? true : false;
                $published = $objAlbum->published ? $published : false;

                // for security reasons...
                if (!$published || (!$this->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false))) {
                    throw new \Exception('Picture with id '.$picId." is either not published or not available or you haven't got enough permission to watch it!!!");
                }

                // picture sorting
                $str_sorting = empty($this->gc_picture_sorting) || empty($this->gc_picture_sorting_direction) ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;
                $objPictures = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting)
                    ->execute('1', $this->intAlbumId)
                ;

                // build up $arrPictures
                $arrIDS = [];
                $i = 0;
                $currentIndex = null;

                while ($objPictures->next()) {
                    if ((int) $picId === (int) $objPictures->id) {
                        $currentIndex = $i;
                    }
                    $arrIDS[] = $objPictures->id;
                    ++$i;
                }

                $arrPictures = [];

                if (\count($arrIDS)) {
                    // store $arrPictures in the template variable
                    $arrPictures['prev'] = GcHelper::getPictureInformationArray($arrIDS[$currentIndex - 1], $this);
                    $arrPictures['current'] = GcHelper::getPictureInformationArray($arrIDS[$currentIndex], $this);
                    $arrPictures['next'] = GcHelper::getPictureInformationArray($arrIDS[$currentIndex + 1], $this);

                    // add navigation href's to the template
                    $this->Template->prevHref = $arrPictures['prev']['single_image_url'];
                    $this->Template->nextHref = $arrPictures['next']['single_image_url'];

                    if (0 === $currentIndex) {
                        $arrPictures['prev'] = null;
                        $this->Template->prevHref = null;
                    }

                    if ($currentIndex === \count($arrIDS) - 1) {
                        $arrPictures['next'] = null;
                        $this->Template->nextHref = null;
                    }

                    if (1 === \count($arrIDS)) {
                        $arrPictures['next'] = null;
                        $arrPictures['prev'] = null;
                        $this->Template->nextHref = null;
                        $this->Template->prevItem = null;
                    }
                }

                // Get the page model
                $objPageModel = PageModel::findByPk($objPage->id);
                $this->Template->returnHref = ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items'), $objPage->language));
                $this->Template->arrPictures = $arrPictures;

                // generate other template variables
                $this->getAlbumTemplateVars($this->intAlbumId);

                // init the counter
                $this->initCounter($this->intAlbumId);

                // Call gcGenerateFrontendTemplateHook
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this, $objAlbum);

                break;
        }
        // end switch
    }

    /**
     * return a sorted array with all album ID's.
     *
     * @param int $pid
     *
     * @return array
     */
    protected function listAllAlbums($pid = 0)
    {
        $strSorting = empty($this->gc_sorting) || empty($this->gc_sorting_direction) ? 'date DESC' : $this->gc_sorting.' '.$this->gc_sorting_direction;
        $objAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($pid, 1)
        ;

        return $objAlbums->fetchEach('id');
    }

    /**
     * @param ContentGalleryCreator $objModule
     */
    protected function callGcGenerateFrontendTemplateHook(self $objModule, GalleryCreatorAlbumsModel $objAlbum = null): FrontendTemplate
    {
        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $this->import($callback[0]);
                $objModule->Template = $this->$callback[0]->$callback[1]($objModule, $objAlbum);
            }
        }

        return $objModule->Template;
    }

    /**
     * Check if fe-user has access to a certain album.
     */
    protected function authenticate(string $strAlbumalias): bool
    {
        if (TL_MODE === 'FE') {
            $objAlb = GalleryCreatorAlbumsModel::findByAlias($strAlbumalias);

            if (null !== $objAlb) {
                if (!$objAlb->protected) {
                    return true;
                }

                $this->import('FrontendUser', 'User');
                $groups = StringUtil::deserialize($objAlb->groups);

                if (!FE_USER_LOGGED_IN || !\is_array($groups) || \count($groups) < 1 || !array_intersect($groups, $this->User->groups)) {
                    return false;
                }
            }
        }

        return true;
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
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null === $objAlbum) {
            return;
        }

        // add meta tags to the page object
        if (TL_MODE === 'FE' && 'detail_view' === $this->viewMode) {
            $objPage->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $objPage->description;
            $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');
        }

        //store all album-data in the array
        $this->Template->arrAlbumdata = $objAlbum->row();

        // store the data of the current album in the session
        $_SESSION['gallery_creator']['CURRENT_ALBUM'] = $this->Template->arrAlbumdata;
        //der back-Link
        $this->Template->backLink = $this->generateBackLink($intAlbumId);
        //Der dem Bild uebergeordnete Albumname
        $this->Template->Albumname = $objAlbum->name;
        // Albumbesucher (Anzahl Klicks)
        $this->Template->visitors = $objAlbum->vistors;
        //Der Kommentar zum gewaehlten Album
        $this->Template->albumComment = StringUtil::toHtml5($objAlbum->comment);
        // In der Detailansicht kann optional ein Artikel vor dem Album hinzugefuegt werden
        $this->Template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        // In der Detailansicht kann optional ein Artikel nach dem Album hinzugefuegt werden
        $this->Template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        //Das Event-Datum des Albums als unix-timestamp
        $this->Template->eventTstamp = $objAlbum->date;
        //Das Event-Datum des Albums formatiert
        $this->Template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        //Abstaende
        $this->Template->imagemargin = 'detail_view' === $this->viewMode ? $this->generateMargin(StringUtil::deserialize($this->gc_imagemargin_detailview), 'margin') : $this->generateMargin(deserialize($this->gc_imagemargin_albumlisting), 'margin');
        //Anzahl Spalten pro Reihe
        $this->Template->colsPerRow = empty($this->gc_rows) ? 4 : $this->gc_rows;

        $this->Template->objElement = $this;
    }
}
