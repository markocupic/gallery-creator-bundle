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
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\GalleryCreatorAlbumsModel;
use Contao\GalleryCreatorPicturesModel;
use Contao\Input;
use Contao\PageError404;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class GalleryCreatorController.
 */
class GalleryCreatorController extends AbstractContentElementController
{
    private $viewMode;

    private $intAlbumId;

    private $arrSelectedAlbums;

    private $model;

    private $pageModel;

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        $this->model = $model;
        $this->pageModel = $pageModel;

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

        if ($this->model->gc_publish_all_albums) {
            // if all albums should be shown
            $this->arrSelectedAlbums = $this->listAllAlbums();
        } else {
            // if only selected albums should be shown
            $this->arrSelectedAlbums = StringUtil::deserialize($this->model->gc_publish_albums, true);
        }

        // clean array from unpublished or empty or protected albums
        foreach ($this->arrSelectedAlbums as $key => $albumId) {
            // Get all not empty albums
            $objAlbum = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE (SELECT COUNT(id) FROM tl_gallery_creator_pictures WHERE pid = ? AND published=?) > 0 AND id=? AND published=?')
                ->execute($albumId, 1, $albumId, 1)
            ;

            // if the album doesn't exist
            if (!$objAlbum->numRows && !GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) && !$this->model->gc_hierarchicalOutput) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }
            // remove id from $this->arrSelectedAlbums if user is not allowed
            if (TL_MODE === 'FE' && true === $objAlbum->protected) {
                if (!$this->authenticate($objAlbum)) {
                    unset($this->arrSelectedAlbums[$key]);
                    continue;
                }
            }
        }
        // build up the new array
        $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

        // abort if no album is selected
        if (empty($this->arrSelectedAlbums)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Detail view:
        // Authenticate and get album alias and album id
        if (Input::get('items')) {
            $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $objAlbum) {
                $this->intAlbumId = $objAlbum->id;
                $this->viewMode = 'detail_view';
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            //Authentifizierung bei vor Zugriff geschuetzten Alben, dh. der Benutzer bekommt, wenn nicht berechtigt, nur das Albumvorschaubild zu sehen.
            if (!$this->authenticate($objAlbum)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }
        $this->viewMode = $this->viewMode ?: 'list_view';
        $this->viewMode = !empty(Input::get('img')) ? 'single_image' : $this->viewMode;

        if ('list_view' === $this->viewMode) {
            // Redirect to detailview if there is only one album
            if (1 === \count($this->arrSelectedAlbums) && $this->model->gc_redirectSingleAlb) {
                $_SESSION['GcRedirectToAlbum'] = GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[0])->alias;
                Controller::reload();
            }

            //Hierarchische Ausgabe
            if ($this->model->gc_hierarchicalOutput) {
                foreach ($this->arrSelectedAlbums as $k => $albumId) {
                    $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);

                    if ($objAlbum->pid > 0) {
                        unset($this->arrSelectedAlbums[$k]);
                    }
                }
                $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

                if (empty($this->arrSelectedAlbums)) {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }
            }
        }

        if ('detail_view' === $this->viewMode) {
            // for security reasons...
            if (!$this->model->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false)) {
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
        switch ($this->viewMode) {
            case 'list_view':

                // pagination settings
                $limit = (int) $this->model->gc_AlbumsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->id;
                    $page = Input::get($id) ?: 1;
                    $offset = ($page - 1) * $limit;

                    // count albums
                    $itemsTotal = \count($this->arrSelectedAlbums);

                    // create pagination menu
                    $numberOfLinks = $this->model->gc_PaginationNumberOfLinks < 1 ? 7 : $this->model->gc_PaginationNumberOfLinks;
                    $objPagination = new Pagination($itemsTotal, $limit, $numberOfLinks, $id);
                    $template->pagination = $objPagination->generate("\n ");
                }

                if (0 === $limit || $limit > \count($this->arrSelectedAlbums)) {
                    $limit = \count($this->arrSelectedAlbums);
                }
                $arrAlbums = [];

                for ($i = $offset; $i < $offset + $limit; ++$i) {
                    if (isset($this->arrSelectedAlbums[$i])) {
                        $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[$i]);

                        if (null !== $objAlbum) {
                            $arrAlbums[] = GcHelper::getAlbumInformationArray($objAlbum, $this->model);
                        }
                    }
                }

                // Add css classes
                if (\count($arrAlbums) > 0) {
                    $arrAlbums[0]['cssClass'] .= ' first';
                    $arrAlbums[\count($arrAlbums) - 1]['cssClass'] .= ' last';
                }

                $template->imagemargin = Controller::generateMargin(unserialize($this->model->gc_imagemargin_albumlisting));
                $template->arrAlbums = $arrAlbums;

                // Call gcGenerateFrontendTemplateHook
                $template = $this->callGcGenerateFrontendTemplateHook($this, $template, null);
                break;

            case 'detail_view':

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->intAlbumId);

                // generate the subalbum array
                if ($this->model->gc_hierarchicalOutput) {
                    $arrSubalbums = GcHelper::getSubalbumsInformationArray($objAlbum, $this->model);
                    $template->subalbums = \count($arrSubalbums) ? $arrSubalbums : null;
                }

                // count pictures
                $objTotal = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')
                    ->execute('1', $this->intAlbumId)
                ;
                $total = $objTotal->numRows;

                // pagination settings
                $limit = $this->model->gc_ThumbsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->id;
                    $page = null !== Input::get($id) ? Input::get($id) : 1;

                    // Do not index or cache the page if the page number is outside the range
                    if ($page < 1 || $page > max(ceil($total / $limit), 1)) {
                        /** @var PageError404 $objHandler */
                        $objHandler = new $GLOBALS['TL_PTY']['error_404']();
                        $objHandler->generate($this->pageModel->id);
                    }
                    $offset = ($page - 1) * $limit;

                    // create the pagination menu
                    $numberOfLinks = $this->model->gc_PaginationNumberOfLinks ?: 7;
                    $objPagination = new Pagination($total, $limit, $numberOfLinks, $id);
                    $template->pagination = $objPagination->generate("\n  ");
                }

                // picture sorting
                $str_sorting = empty($this->model->gc_picture_sorting) || empty($this->model->gc_picture_sorting_direction) ? 'sorting ASC' : $this->model->gc_picture_sorting.' '.$this->model->gc_picture_sorting_direction;

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

                    if (null !== ($objPicturesModel = GalleryCreatorPicturesModel::findByPk($objPictures->id))) {
                        $arrPictures[$objPictures->id] = GcHelper::getPictureInformationArray($objPicturesModel, $this->model);
                    }
                }

                // sort by basename
                if ('name' === $this->model->gc_picture_sorting) {
                    if ('ASC' === $this->model->gc_picture_sorting_direction) {
                        array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_ASC);
                    } else {
                        array_multisort($arrPictures, SORT_STRING, $auxBasename, SORT_DESC);
                    }
                }

                $arrPictures = array_values($arrPictures);

                // store $arrPictures in the template variable
                $template->arrPictures = $arrPictures;

                // generate other template variables
                $this->getAlbumTemplateVars($objAlbum, $template);

                // init the counter
                GcHelper::initCounter($objAlbum);

                // Call gcGenerateFrontendTemplateHook
                $template = $this->callGcGenerateFrontendTemplateHook($this, $template, $objAlbum);
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
                if (!$published || (!$this->model->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false))) {
                    throw new \Exception('Picture with id '.$picId." is either not published or not available or you haven't got enough permission to watch it!!!");
                }

                // picture sorting
                $str_sorting = empty($this->model->gc_picture_sorting) || empty($this->model->gc_picture_sorting_direction) ? 'sorting ASC' : $this->model->gc_picture_sorting.' '.$this->model->gc_picture_sorting_direction;
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
                    if (null !== ($objPicturePrev = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex - 1]))) {
                        $arrPictures['prev'] = GcHelper::getPictureInformationArray($objPicturePrev, $this->model);
                    }

                    if (null !== ($objPictureCurrent = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex]))) {
                        $arrPictures['current'] = GcHelper::getPictureInformationArray($objPictureCurrent, $this->model);
                    }

                    if (null !== ($objPictureNext = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex + 1]))) {
                        $arrPictures['next'] = GcHelper::getPictureInformationArray($objPictureNext, $this->model);
                    }

                    // add navigation href's to the template
                    $template->prevHref = $arrPictures['prev']['single_image_url'];
                    $template->nextHref = $arrPictures['next']['single_image_url'];

                    if (0 === $currentIndex) {
                        $arrPictures['prev'] = null;
                        $template->prevHref = null;
                    }

                    if ($currentIndex === \count($arrIDS) - 1) {
                        $arrPictures['next'] = null;
                        $template->nextHref = null;
                    }

                    if (1 === \count($arrIDS)) {
                        $arrPictures['next'] = null;
                        $arrPictures['prev'] = null;
                        $template->nextHref = null;
                        $template->prevItem = null;
                    }
                }

                // Get the page model
                $template->returnHref = ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items'), $this->pageModel->language));
                $template->arrPictures = $arrPictures;

                // generate other template variables
                $this->getAlbumTemplateVars($objAlbum, $template);

                // init the counter
                GcHelper::initCounter($objAlbum);

                // Call gcGenerateFrontendTemplateHook
                $template = $this->callGcGenerateFrontendTemplateHook($this, $template, $objAlbum);

                break;
        }

        // end switch
        return $template->getResponse();
    }

    /**
     * Ajax responses.
     *
     * @return false|string
     */
    protected function generateAjax()
    {
        //gibt ein Array mit allen Bildinformationen des Bildes mit der id imageId zurueck
        if (Input::get('isAjax') && Input::get('getImageByPk') && !empty(Input::get('id'))) {
            if (null !== ($objPicture = GalleryCreatorPicturesModel::findByPk(Input::get('id')))) {
                $arrPicture = GcHelper::getPictureInformationArray(Input::get('id'), $this->model);
                echo json_encode($arrPicture);
            }

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

            if (!$this->authenticate($objAlbum)) {
                return false;
            }

            // Init visit counter
            GcHelper::initCounter($objAlbum);

            // sorting direction
            $sorting = $this->model->gc_picture_sorting.' '.$this->model->gc_picture_sorting_direction;

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
     * Generates the back link.
     *
     * @return false|string|array<string>|null
     */
    protected function generateBackLink(GalleryCreatorAlbumsModel $objAlbum)
    {
        if (TL_MODE === 'BE') {
            return false;
        }

        //generiert den Link zum Parent-Album
        if ($this->model->gc_hierarchicalOutput && null !== ($objParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($objAlbum))) {
            return ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$objParentAlbum->alias));
        }

        //generiert den Link zur Startuebersicht unter Beruecksichtigung der pagination
        $url = $this->pageModel->getFrontendUrl();
        $url .= isset($_SESSION['gallery_creator']['PAGINATION']) ? '?page='.$_SESSION['gallery_creator']['PAGINATION'] : '';

        return ampersand($url);
    }

    /**
     * return a sorted array with all album ID's.
     *
     * @param int $pid
     */
    protected function listAllAlbums($pid = 0): array
    {
        $strSorting = empty($this->model->gc_sorting) || empty($this->model->gc_sorting_direction) ? 'date DESC' : $this->model->gc_sorting.' '.$this->model->gc_sorting_direction;
        $objAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($pid, 1)
        ;

        return $objAlbums->fetchEach('id');
    }

    protected function callGcGenerateFrontendTemplateHook(self $objModule, Template $template, GalleryCreatorAlbumsModel $objAlbum = null): Template
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $template = $systemAdapter->importStatic($callback[0])->{$callback[1]}($objModule, $objAlbum);
            }
        }

        return $template;
    }

    /**
     * Check if a frontend user has access to a protected album.
     */
    protected function authenticate(GalleryCreatorAlbumsModel $objAlbum): bool
    {
        if (TL_MODE === 'FE') {
            if (!$objAlbum->protected) {
                return true;
            }
            $objUser = FrontendUser::getInstance();

            if (FE_USER_LOGGED_IN && null !== $objUser) {
                $groups = StringUtil::deserialize($objAlbum->groups, true);
                $userGroups = StringUtil::deserialize($objUser->groups, true);

                if (!empty(array_intersect($groups, $this->User->groups))) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Set the template-vars to the template object for the selected album.
     */
    protected function getAlbumTemplateVars(GalleryCreatorAlbumsModel $objAlbum, Template &$template): void
    {
        // add meta tags to the page object
        if (TL_MODE === 'FE' && 'detail_view' === $this->viewMode) {
            $this->pageModel->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $this->pageModel->description;
            $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');
        }

        //store all album-data in the array
        $template->arrAlbumdata = $objAlbum->row();

        // store the data of the current album in the session
        $_SESSION['gallery_creator']['CURRENT_ALBUM'] = $template->arrAlbumdata;
        //der back-Link
        $template->backLink = $this->generateBackLink($objAlbum);
        //Der dem Bild uebergeordnete Albumname
        $template->Albumname = $objAlbum->name;
        // Albumbesucher (Anzahl Klicks)
        $template->visitors = $objAlbum->vistors;
        //Der Kommentar zum gewaehlten Album
        $template->albumComment = StringUtil::toHtml5($objAlbum->comment);
        // In der Detailansicht kann optional ein Artikel vor dem Album hinzugefuegt werden
        $template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        // In der Detailansicht kann optional ein Artikel nach dem Album hinzugefuegt werden
        $template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        //Das Event-Datum des Albums als unix-timestamp
        $template->eventTstamp = $objAlbum->date;
        //Das Event-Datum des Albums formatiert
        $template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        //Abstaende
        $template->imagemargin = 'detail_view' === $this->viewMode ? Controller::generateMargin(StringUtil::deserialize($this->model->gc_imagemargin_detailview), 'margin') : $this->generateMargin(deserialize($this->model->gc_imagemargin_albumlisting), 'margin');
        //Anzahl Spalten pro Reihe
        $template->colsPerRow = empty($this->model->gc_rows) ? 4 : $this->model->gc_rows;

        $template->objElement = $this->model;
    }
}
