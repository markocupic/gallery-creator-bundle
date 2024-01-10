<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\ContentElement;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\GalleryCreatorUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ContentGalleryCreator extends ContentElement
{
    public const DISPLAY_MODE_LISTING = 'list_view';
    public const DISPLAY_MODE_READER = 'detail_view';
    public const DISPLAY_MODE_SINGLE_IMAGE = 'single_image';
    public string $defaultThumb = 'bundles/markocupicgallerycreator/images/image_not_found.jpg';

    protected string|null $viewMode = null;
    protected $strTemplate = 'ce_gc_default';
    protected int|null $intAlbumId = null;
    protected string|null $strAlbumAlias = null;
    protected array|null $arrSelectedAlbums = null;

    public function generate(): string
    {
        /** @var Request $request */
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['CTE']['gallery_creator_ce'][0].' ###';
            $objTemplate->title = $this->headline ?? '';

            return $objTemplate->parse();
        }

        $session = $request->getSession();

        $bag = $session->get('GALLERY_CREATOR', []);

        if (isset($bag['CURRENT_ALBUM'])) {
            unset($bag['CURRENT_ALBUM']);
            $session->set('GALLERY_CREATOR', $bag);
        }

        $bag = $session->get('GALLERY_CREATOR', []);

        if (!empty($bag['REDIRECT_TO_ALBUM'])) {
            Input::setGet('items', $bag['REDIRECT_TO_ALBUM']);
            unset($bag['REDIRECT_TO_ALBUM']);
            $session->set('GALLERY_CREATOR', $bag);
        }

        // Ajax Requests
        if (System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request) && Environment::get('isAjaxRequest')) {
            $this->generateAjax();
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['items']) && isset($_GET['auto_item']) && Config::get('useAutoItem')) {
            Input::setGet('items', Input::get('auto_item'));
        }

        if (Input::get('items')) {
            $this->viewMode = self::DISPLAY_MODE_READER;
        }

        // Store the pagination variable page in the current session
        if (!Input::get('items')) {
            $bag = $session->get('GALLERY_CREATOR', []);

            if (isset($bag['PAGINATION'])) {
                unset($bag['PAGINATION']);
                $session->set('GALLERY_CREATOR', $bag);
            }
        }

        if (Input::get('page') && self::DISPLAY_MODE_READER !== $this->viewMode) {
            $bag = $session->get('GALLERY_CREATOR', []);
            $bag['PAGINATION'] = Input::get('page');
            $session->set('GALLERY_CREATOR', $bag);
        }

        if ($this->gc_publish_all_albums) {
            $arrSelectedAlbums = $this->listAllPublishedAlbums();
        } else {
            $arrSelectedAlbums = StringUtil::deserialize($this->gc_publish_albums, true);
        }

        $this->arrSelectedAlbums = array_map('intval', $arrSelectedAlbums);

        // Clean array from unpublished or empty or protected albums
        foreach ($this->arrSelectedAlbums as $key => $albumId) {
            $countImages = Database::getInstance()
                ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE pid = ? AND published = ?')
                ->execute($albumId, '1')
                ->numRows
            ;

            $objAlbum = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id = ?')
                ->execute($albumId)
            ;

            // Remove the album from selection if it doesn't exist or is not published.
            if (!$objAlbum->numRows || !$objAlbum->published) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }

            // Remove the album from selection if it is empty...
            if (!$countImages && !$this->gc_hierarchicalOutput) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }

            // or if the album is empty, we've got hierarchical output and the album has no child albums
            if (!$countImages && !GalleryCreatorAlbumsModel::hasChildAlbums($albumId) && $this->gc_hierarchicalOutput) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }

            // Remove the album from selection if the frontend user is not allowed.
            if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request) && true === $objAlbum->protected) {
                if (!$this->isAuthorised($objAlbum->alias)) {
                    unset($this->arrSelectedAlbums[$key]);
                }
            }
        }

        // Build new array
        $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

        // abort if no album is selected
        if (empty($this->arrSelectedAlbums)) {
            return '';
        }

        // Detail view:
        if (Input::get('items')) {
            $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $objAlbum) {
                $this->intAlbumId = $objAlbum->id;
                $this->strAlbumAlias = $objAlbum->alias;
                $this->viewMode = self::DISPLAY_MODE_READER;
            } else {
                return '';
            }

            // Check if the user has access to a protected album.
            if (!$this->isAuthorised($this->strAlbumAlias)) {
                return '';
            }
        }

        $this->viewMode = $this->viewMode ?: self::DISPLAY_MODE_LISTING;
        $this->viewMode = Input::get('img') ? self::DISPLAY_MODE_SINGLE_IMAGE : $this->viewMode;

        if (self::DISPLAY_MODE_LISTING === $this->viewMode) {
            // Redirect to detail view if there is only one album in the selection
            if (!empty($this->arrSelectedAlbums) && 1 === \count($this->arrSelectedAlbums) && $this->gc_redirectSingleAlb) {
                $bag = $session->get('GALLERY_CREATOR', []);
                $bag['REDIRECT_TO_ALBUM'] = GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[0])->alias;
                $session->set('GALLERY_CREATOR', $bag);
                $this->reload();
            }

            // Hierarchical listing
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

        if (self::DISPLAY_MODE_READER === $this->viewMode) {
            // for security reasons...
            if (!$this->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, true)) {
                return '';
            }
        }

        // Assigning the frontend template
        $this->strTemplate = $this->gc_template ?: $this->strTemplate;
        $this->Template = new FrontendTemplate($this->strTemplate);

        return parent::generate();
    }

    /**
     * Respond to ajax requests
     * and
     * returns an array with all image information of the image with the id imageId.
     */
    public function generateAjax(): void
    {
        if (Input::get('isAjax') && Input::get('getImageByPk') && Input::get('id')) {
            $arrPicture = GalleryCreatorUtil::getPictureInformationArray(Input::get('id'), $this);
            $arrPicture = mb_convert_encoding($arrPicture, 'UTF-8');

            $response = new JsonResponse($arrPicture);

            throw new ResponseException($response);
        }

        // Send image-date from a certain album as JSON encoded array to the browser
        // used f.ex. for the ce_gc_colorbox.html template --> https://gist.github.com/markocupic/327413038262b2f84171f8df177cf021
        if (Input::get('isAjax') && Input::get('getImagesByPid') && Input::get('pid')) {
            // Do not send data if album is protected and the user has no access
            $objAlbum = Database::getInstance()
                ->prepare('SELECT alias FROM tl_gallery_creator_albums WHERE id=?')
                ->execute(Input::get('albumId'))
            ;

            if (!$this->isAuthorised($objAlbum->alias)) {
                throw new AccessDeniedException('Access denied!');
            }

            // Init visit counter
            $this->initCounter(Input::get('pid'));

            // Sorting direction
            $sorting = !$this->gc_picture_sorting || !$this->gc_picture_sorting_direction ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;

            $objPicture = Database::getInstance()
                ->prepare("SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY $sorting")
                ->execute(1, Input::get('pid'))
            ;

            $data = [];

            while ($objPicture->next()) {
                $objFile = FilesModel::findByUuid($objPicture->uuid);

                $href = $objFile->path;
                $href = '' !== trim($objPicture->socialMediaSRC) ? trim($objPicture->socialMediaSRC) : $href;
                $href = '' !== trim($objPicture->localMediaSRC) ? trim($objPicture->localMediaSRC) : $href;
                $arrPicture = [
                    'href' => StringUtil::specialchars($href),
                    'pid' => $objPicture->pid,
                    'caption' => StringUtil::specialchars($objPicture->comment),
                    'id' => $objPicture->id,
                    'uuid' => StringUtil::binToUuid($objFile->uuid),
                ];
                $data[] = array_merge($objPicture->row(), $arrPicture);
                $data = mb_convert_encoding($data, 'UTF-8');
            }

            $response = new JsonResponse(['src' => $data, 'success' => 'true']);

            throw new ResponseException($response);
        }
    }

    /**
     * Returns the path to the preview-thumbnail of an album.
     */
    public function getAlbumPreviewThumb(int $intAlbumId): array
    {
        $thumbSRC = $this->defaultThumb;

        // Check for an alternate thumbnail
        if ('' !== Config::get('gc_error404_thumb')) {
            if (Validator::isStringUuid(Config::get('gc_error404_thumb'))) {
                $objFile = FilesModel::findByUuid(StringUtil::uuidToBin(Config::get('gc_error404_thumb')));

                if (null !== $objFile) {
                    if (is_file(System::getContainer()->getParameter('kernel.project_dir').'/'.$objFile->path)) {
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
                if (is_file(System::getContainer()->getParameter('kernel.project_dir').'/'.$oFile->path)) {
                    $arrThumb = [
                        'name' => basename($oFile->path),
                        'path' => $oFile->path,
                    ];
                }
            }
        }

        return $arrThumb;
    }

    public function generateBackLink(int $intAlbumId): string|null
    {
        global $objPage;

        /** @var Request $request */
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            return null;
        }

        // Get the page model
        $objPageModel = PageModel::findByPk($objPage->id);

        // Generates the link to the parent album
        if ($this->gc_hierarchicalOutput && GalleryCreatorAlbumsModel::getParentAlbum($intAlbumId)) {
            $arrParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($intAlbumId);

            return StringUtil::ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$arrParentAlbum['alias']));
        }

        // Generates the link to the start overview, taking into account the pagination
        $session = $request->getSession();
        $bag = $session->get('GALLERY_CREATOR', []);

        $url = $objPageModel->getFrontendUrl();
        $url .= !empty($bag['PAGINATION']) ? '?page='.$bag['PAGINATION'] : '';

        return StringUtil::ampersand($url);
    }

    public static function initCounter(int $intAlbumId): void
    {
        if (preg_match('/bot|sp[iy]der|crawler|lib(?:cur|www)|search|archive/i', $_SERVER['HTTP_USER_AGENT'])) {
            // do not count spiders/bots
            return;
        }

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
            $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

            if (strpos((string) $objAlbum->visitors_details, $_SERVER['REMOTE_ADDR'])) {
                // return if the visitor is already registered
                return;
            }

            $arrVisitors = StringUtil::deserialize($objAlbum->visitors_details, true);
            // keep visitors data in the db unless 50 other users have visited the album
            if (50 === \count($arrVisitors)) {
                // slice the last position
                $arrVisitors = \array_slice($arrVisitors, 0, \count($arrVisitors) - 1);
            }

            // Build the array
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
            case self::DISPLAY_MODE_LISTING:

                // Pagination
                $limit = $this->gc_AlbumsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->id;
                    $page = (int) (Input::get($id) ?? 1);

                    // Count albums
                    $total = \count($this->arrSelectedAlbums);

                    // Do not index or cache the page if the page number is outside the range
                    if ($page < 1 || $page > max(ceil($total / $limit), 1)) {
                        throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
                    }
                    $offset = ($page - 1) * $limit;

                    // Create the pagination menu
                    $paginationLinks = !$this->gc_PaginationNumberOfLinks ? Config::get('maxPaginationLinks') : $this->gc_PaginationNumberOfLinks;
                    $objPagination = new Pagination($total, $limit, $paginationLinks, $id);
                    $this->Template->pagination = $objPagination->generate("\n ");
                }

                if (0 === $limit || $limit > \count($this->arrSelectedAlbums)) {
                    $limit = \count($this->arrSelectedAlbums);
                }

                $arrAlbums = [];

                for ($i = $offset; $i < $offset + $limit; ++$i) {
                    if (isset($this->arrSelectedAlbums[$i])) {
                        $arrAlbums[] = GalleryCreatorUtil::getAlbumInformationArray($this->arrSelectedAlbums[$i], $this);
                    }
                }

                // Add css classes
                if (\count($arrAlbums) > 0) {
                    $arrAlbums[0]['cssClass'] .= ' first';
                    $arrAlbums[\count($arrAlbums) - 1]['cssClass'] .= ' last';
                }

                $this->Template->imagemargin = $this->generateMargin(unserialize($this->gc_imagemargin_albumlisting));
                $this->Template->arrAlbums = $arrAlbums;

                // Trigger gcGenerateFrontendTemplateHook
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this);
                break;

            case self::DISPLAY_MODE_READER:
                // Generate the child album array
                if ($this->gc_hierarchicalOutput) {
                    $arrSubalbums = GalleryCreatorUtil::getSubalbumsInformationArray($this->intAlbumId, $this);
                    $this->Template->subalbums = \count($arrSubalbums) ? $arrSubalbums : null;
                }

                // Count pictures
                $objTotal = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')
                    ->execute('1', $this->intAlbumId)
                ;

                $total = $objTotal->numRows;

                // Pagination
                $limit = $this->gc_ThumbsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    $id = 'page_g'.$this->id;
                    $page = (int) (Input::get($id) ?? 1);

                    // Do not index or cache the page if the page number is outside the range
                    if ($page < 1 || $page > max(ceil($total / $limit), 1)) {
                        throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
                    }

                    $offset = ($page - 1) * $limit;

                    // Create the pagination menu
                    $paginationLinks = $this->gc_PaginationNumberOfLinks ?: Config::get('maxPaginationLinks');
                    $objPagination = new Pagination($total, $limit, $paginationLinks, $id);
                    $this->Template->pagination = $objPagination->generate("\n  ");
                }

                // Picture sorting
                $str_sorting = !$this->gc_picture_sorting || !$this->gc_picture_sorting_direction ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;

                // Sort by name is done below
                $str_sorting = str_replace('name', 'id', $str_sorting);

                $objPictures = Database::getInstance()
                    ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting)
                ;

                if ($limit > 0) {
                    $objPictures->limit($limit, $offset);
                }

                $objPictures = $objPictures->execute('1', $this->intAlbumId);

                // Build $arrPictures
                $arrPictures = [];
                $auxBasename = [];

                while ($objPictures->next()) {
                    $objFilesModel = FilesModel::findByUuid($objPictures->uuid);
                    $basename = 'undefined';

                    if (null !== $objFilesModel) {
                        $basename = $objFilesModel->name;
                    }

                    $auxBasename[] = $basename;
                    $arrPictures[$objPictures->id] = GalleryCreatorUtil::getPictureInformationArray($objPictures->id, $this);
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

                // Add picture array to the template
                $this->Template->arrPictures = $arrPictures;

                // Add more data to the template
                $this->addTemplateData($this->intAlbumId);

                // Init the counter
                $this->initCounter($this->intAlbumId);

                // Trigger gcGenerateFrontendTemplateHook
                $objAlbum = GalleryCreatorAlbumsModel::findByAlias($this->strAlbumAlias);
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this, $objAlbum);
                break;

            case self::DISPLAY_MODE_SINGLE_IMAGE:
                $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

                if (null === $objAlbum) {
                    throw new PageNotFoundException('Invalid album alias: '.Input::get('items'));
                }

                $objPic = Database::getInstance()
                    ->prepare("SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND name LIKE '".Input::get('img').".%'")
                    ->execute($objAlbum->id)
                ;

                if (!$objPic->numRows) {
                    $message = sprintf('File "%s" does not exist in album with alias "%s".', Input::get('img'), Input::get('items'));

                    throw new PageNotFoundException($message);
                }

                $picId = $objPic->id;
                $published = $objAlbum->published && $objPic->published;

                // for security reasons...
                if (!$published || (!$this->gc_publish_all_albums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, true))) {
                    $message = "Picture with id $picId is either not published or not available or you haven't got enough permission to watch it!";

                    throw new PageNotFoundException($message);
                }

                // Picture sorting
                $str_sorting = !$this->gc_picture_sorting || !$this->gc_picture_sorting_direction ? 'sorting ASC' : $this->gc_picture_sorting.' '.$this->gc_picture_sorting_direction;
                $objPictures = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$str_sorting)
                    ->execute('1', $this->intAlbumId)
                ;

                // Build $arrPictures
                $arrIDS = [];
                $i = 0;
                $currentIndex = null;

                while ($objPictures->next()) {
                    if ($picId === $objPictures->id) {
                        $currentIndex = $i;
                    }
                    $arrIDS[] = $objPictures->id;
                    ++$i;
                }

                $arrPictures = [];

                if (\count($arrIDS)) {
                    // Add $arrPictures to the template
                    $arrPictures['prev'] = GalleryCreatorUtil::getPictureInformationArray($arrIDS[$currentIndex - 1], $this);
                    $arrPictures['current'] = GalleryCreatorUtil::getPictureInformationArray($arrIDS[$currentIndex], $this);
                    $arrPictures['next'] = GalleryCreatorUtil::getPictureInformationArray($arrIDS[$currentIndex + 1], $this);

                    // Add navigation to the template
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
                $this->Template->returnHref = StringUtil::ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items')));
                $this->Template->arrPictures = $arrPictures;

                // Add more data to the template
                $this->addTemplateData($this->intAlbumId);

                // Init the counter
                $this->initCounter($this->intAlbumId);

                // Trigger gcGenerateFrontendTemplateHook
                $this->Template = $this->callGcGenerateFrontendTemplateHook($this, $objAlbum);

                break;
        }
        // end switch
    }

    /**
     * Return a sorted array with all album ID's.
     */
    protected function listAllPublishedAlbums(int $pid = null): array
    {
        $strSorting = !$this->gc_sorting || !$this->gc_sorting_direction ? 'date DESC' : $this->gc_sorting.' '.$this->gc_sorting_direction;

        if (null !== $pid) {
            $objAlbums = Database::getInstance()
                ->prepare("SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY $strSorting")
                ->execute($pid, '1')
            ;
        } else {
            $objAlbums = Database::getInstance()
                ->prepare("SELECT * FROM tl_gallery_creator_albums WHERE published = ? ORDER BY $strSorting")
                ->execute('1')
            ;
        }

        return $objAlbums->fetchEach('id');
    }

    protected function callGcGenerateFrontendTemplateHook(self $objModule, GalleryCreatorAlbumsModel $objAlbum = null): Template
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
     * Check if the frontend user has access to a maybe protected certain album.
     */
    protected function isAuthorised(string $strAlbumAlias): bool
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
            $objAlbum = GalleryCreatorAlbumsModel::findByAlias($strAlbumAlias);

            if (null !== $objAlbum) {
                if (!$objAlbum->protected) {
                    return true;
                }

                $groups = StringUtil::deserialize($objAlbum->groups);

                if (System::getContainer()->get('contao.security.token_checker')->hasFrontendUser()) {
                    if (($user = System::getContainer()->get('security.helper')->getUser()) instanceof FrontendUser) {
                        $hasLoggedInFrontendUser = true;
                    }
                }

                if (!isset($hasLoggedInFrontendUser) || !\is_array($groups) || empty($groups) || empty(array_intersect($groups, $user->groups))) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function addTemplateData(int $intAlbumId): void
    {
        global $objPage;

        // Load the current album from db
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null === $objAlbum) {
            return;
        }

        /** @var Request $request */
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        // Add meta tags to the page object
        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request) && self::DISPLAY_MODE_READER === $this->viewMode) {
            $objPage->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $objPage->description;
            $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');
        }

        $this->Template->arrAlbumdata = $objAlbum->row();

        // store the data of the current album in the session
        $session = $request->getSession();
        $bag = $session->get('GALLERY_CREATOR', []);
        $bag['CURRENT_ALBUM'] = $objAlbum->row();
        $session->set('GALLERY_CREATOR', $bag);

        $this->Template->backLink = $this->generateBackLink($intAlbumId);
        $this->Template->Albumname = $objAlbum->name;
        $this->Template->visitors = $objAlbum->vistors;
        $this->Template->albumComment = 'xhtml' === $objPage->outputFormat ? StringUtil::toXhtml($objAlbum->comment) : StringUtil::toHtml5($objAlbum->comment);
        $this->Template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        $this->Template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        $this->Template->eventTstamp = $objAlbum->date;
        $this->Template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        $this->Template->imagemargin = self::DISPLAY_MODE_READER === $this->viewMode ? $this->generateMargin(StringUtil::deserialize($this->gc_imagemargin_detailview, true)) : $this->generateMargin(StringUtil::deserialize($this->gc_imagemargin_albumlisting, true));
        $this->Template->colsPerRow = '' === $this->gc_rows ? 4 : $this->gc_rows;

        $this->Template->objElement = $this;
    }
}
