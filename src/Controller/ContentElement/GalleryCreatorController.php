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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Database;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Haste\Util\Url;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

/**
 * @ContentElement(GalleryCreatorController::TYPE, category="gallery_creator_elements")
 */
class GalleryCreatorController extends AbstractContentElementController
{
    public const TYPE = 'gallery_creator';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var SecurityUtil
     */
    private $securityUtil;

    /**
     * @var AlbumUtil
     */
    private $albumUtil;

    /**
     * @var PictureUtil
     */
    private $pictureUtil;

    /**
     * @var string
     */
    private $viewMode;

    /**
     * @var int
     */
    private $intAlbumId;

    /**
     * @var array Contains the album ids
     */
    private $arrSelectedAlbums;

    /**
     * @var ContentModel
     */
    private $model;

    /**
     * @var PageModel
     */
    private $pageModel;

    /**
     * @var null Session
     */
    private $session;

    /**
     * @var MemberModel
     */
    private $user;

    public function __construct(ContaoFramework $framework, Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher, SecurityUtil $securityUtil, AlbumUtil $albumUtil, PictureUtil $pictureUtil)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->securityUtil = $securityUtil;
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        $this->model = $model;
        $this->pageModel = $pageModel;
        $this->session = $request->getSession();

        if ($this->security->getUser() instanceof FrontendUser) {
            $this->user = MemberModel::findByPk($this->security->getUser()->id);
        }

        // Get items url param from session
        if ($this->session->has('gc_redirect_to_album')) {
            Input::setGet('items', $this->session->get('gc_redirect_to_album'));
            $this->session->remove('gc_redirect_to_album');
        }

        // Set the item from the auto_item parameter
        if (Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('items', Input::get('auto_item'));
        }

        if (!empty(Input::get('items'))) {
            $this->viewMode = 'detail_view';
        }

        // Remove or store the pagination variable "page" in the current session
        if (!Input::get('items')) {
            $this->session->remove('gc_pagination');
        }

        if (Input::get('page') && 'detail_view' !== $this->viewMode) {
            $this->session->set('gc_pagination', Input::get('page'));
        }

        if ($this->model->gcPublishAllAlbums) {
            // If all albums should be shown
            $this->arrSelectedAlbums = $this->listAllAlbums();
        } else {
            // If only selected albums should be shown
            $this->arrSelectedAlbums = StringUtil::deserialize($this->model->gcPublishAlbums, true);
        }

        // Clean array from unpublished or empty or protected albums
        foreach ($this->arrSelectedAlbums as $key => $albumId) {
            // Get all not empty albums
            $objAlbum = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE (SELECT COUNT(id) FROM tl_gallery_creator_pictures WHERE pid = ? AND published=?) > 0 AND id=? AND published=?')
                ->execute($albumId, 1, $albumId, 1)
            ;

            // If the album doesn't exist
            if (!$objAlbum->numRows && !GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) && !$this->model->gcHierarchicalOutput) {
                unset($this->arrSelectedAlbums[$key]);
                continue;
            }

            // Remove id from $this->arrSelectedAlbums if user is not allowed
            $request = $this->requestStack->getCurrentRequest();

            if ($request && $this->scopeMatcher->isFrontendRequest($request) && true === $objAlbum->protected) {
                if (!$this->securityUtil->isAuthorized($objAlbum)) {
                    unset($this->arrSelectedAlbums[$key]);
                    continue;
                }
            }
        }

        // Build up the new array
        $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

        // Abort if no album is selected
        if (empty($this->arrSelectedAlbums)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Authenticate and get album alias and album id
        if (Input::get('items')) {
            $objAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $objAlbum) {
                $this->intAlbumId = (int) $objAlbum->id;
                $this->viewMode = 'detail_view';
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            // Authentication for protected albums:
            // If not authorized, the user gets to see the album preview image only.
            if (!$this->securityUtil->isAuthorized($objAlbum)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }
        $this->viewMode = $this->viewMode ?: 'list_view';
        $this->viewMode = !empty(Input::get('img')) ? 'single_image' : $this->viewMode;

        if ('list_view' === $this->viewMode) {
            // Redirect to detail view if there is only one album
            if (1 === \count($this->arrSelectedAlbums) && $this->model->gcRedirectSingleAlb) {
                $this->session->set('gc_redirect_to_album', GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[0])->alias);
                Controller::reload();
            }

            // Hierarchical output
            if ($this->model->gcHierarchicalOutput) {
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
            if (!$this->model->gcPublishAllAlbums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $this->model, $section, $classes, $pageModel);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        switch ($this->viewMode) {
            case 'list_view':

                // pagination settings
                $limit = (int) $this->model->gcAlbumsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->model->id;
                    $page = Input::get($id) ?: 1;
                    $offset = ($page - 1) * $limit;

                    // Count albums
                    $itemsTotal = \count($this->arrSelectedAlbums);

                    // Create pagination menu
                    $numberOfLinks = $this->model->gcPaginationNumberOfLinks < 1 ? 7 : $this->model->gcPaginationNumberOfLinks;
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
                            $arrAlbums[] = $this->albumUtil->getAlbumData($objAlbum, $this->model);
                        }
                    }
                }

                $template->arrAlbums = $arrAlbums;

                // Call gcGenerateFrontendTemplateHook
                $this->callGcGenerateFrontendTemplateHook($template, null);
                break;

            case 'detail_view':

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->intAlbumId);

                // Get child albums
                if ($this->model->gcHierarchicalOutput) {
                    $arrChildAlbums = $this->albumUtil->getChildAlbums($objAlbum, $this->model);
                    $template->subalbums = \count($arrChildAlbums) ? $arrChildAlbums : null;
                    $template->hasChildAlbums = \count($arrChildAlbums) ? true : false;
                }

                // count pictures
                $objTotal = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=?')
                    ->execute('1', $this->intAlbumId)
                ;
                $total = $objTotal->numRows;

                // pagination settings
                $limit = $this->model->gcThumbsPerPage;
                $offset = 0;

                if ($limit > 0) {
                    // Get the current page
                    $id = 'page_g'.$this->model->id;
                    $page = Input::get($id) ?? 1;

                    // Do not index or cache the page if the page number is outside the range
                    if ($page < 1 || $page > max(ceil($total / $limit), 1)) {
                        throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
                    }

                    $offset = ($page - 1) * $limit;

                    // create the pagination menu
                    $numberOfLinks = $this->model->gcPaginationNumberOfLinks ?? 7;
                    $objPagination = new Pagination($total, $limit, $numberOfLinks, $id);
                    $template->pagination = $objPagination->generate("\n  ");
                }

                // picture sorting
                $strSorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;

                // sort by name is done below
                $strSorting = str_replace('name', 'id', $strSorting);

                $objPictures = Database::getInstance()
                    ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$strSorting)
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
                        $arrPictures[$objPictures->id] = $this->pictureUtil->getPictureData($objPicturesModel, $this->model);
                    }
                }

                // sort by basename
                if ('name' === $this->model->gcPictureSorting) {
                    if ('ASC' === $this->model->gcPictureSortingDirection) {
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
                $this->albumUtil->countAlbumViews($objAlbum);

                // Call gcGenerateFrontendTemplateHook
                $this->callGcGenerateFrontendTemplateHook($template, $objAlbum);
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
                if (!$published || (!$this->model->gcPublishAllAlbums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false))) {
                    throw new \Exception('Picture with id '.$picId." is either not published or not available or you haven't got enough permission to watch it!!!");
                }

                // picture sorting
                $strSorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;
                $objPictures = Database::getInstance()
                    ->prepare('SELECT id FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$strSorting)
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
                        $arrPictures['prev'] = $this->pictureUtil->getPictureData($objPicturePrev, $this->model);
                    }

                    if (null !== ($objPictureCurrent = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex]))) {
                        $arrPictures['current'] = $this->pictureUtil->getPictureData($objPictureCurrent, $this->model);
                    }

                    if (null !== ($objPictureNext = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex + 1]))) {
                        $arrPictures['next'] = $this->pictureUtil->getPictureData($objPictureNext, $this->model);
                    }

                    // Add previous and next links to the template
                    $template->prevHref = $arrPictures['prev']['singleImageUrl'];
                    $template->nextHref = $arrPictures['next']['singleImageUrl'];

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
                $template->returnHref = StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items'), $this->pageModel->language));
                $template->arrPictures = $arrPictures;

                // generate other template variables
                $this->getAlbumTemplateVars($objAlbum, $template);

                // init the counter
                $this->albumUtil->countAlbumViews($objAlbum);

                // Call gcGenerateFrontendTemplateHook
                $this->callGcGenerateFrontendTemplateHook($template, $objAlbum);

                break;
        }

        // end switch
        return $template->getResponse();
    }

    /**
     * Generates the back link.
     *
     * @return false|string|array<string>|null
     */
    protected function generateBackLink(GalleryCreatorAlbumsModel $objAlbum): ?string
    {
        if ($this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest())) {
            return null;
        }

        // Generates the link to the parent album
        if ($this->model->gcHierarchicalOutput && null !== ($objParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($objAlbum))) {
            return StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$objParentAlbum->alias));
        }

        // Generates the link to the startup overview taking into account the pagination
        $url = $this->pageModel->getFrontendUrl();

        if ($this->session->has('gc_pagination')) {
            $url = Url::addQueryString('page_g='.$this->session->get('gc_pagination'), $url);
        }

        return StringUtil::ampersand($url);
    }

    protected function listAllAlbums(int $pid = 0): array
    {
        $strSorting = empty($this->model->gcSorting) || empty($this->model->gcSortingDirection) ? 'date DESC' : $this->model->gcSorting.' '.$this->model->gcSortingDirection;
        $objAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($pid, 1)
        ;

        return array_map('intval', $objAlbums->fetchEach('id'));
    }

    protected function callGcGenerateFrontendTemplateHook(Template $template, GalleryCreatorAlbumsModel $objAlbum = null): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $systemAdapter->importStatic($callback[0])->{$callback[1]}($this, $template, $objAlbum);
            }
        }
    }

    /**
     * Set the template-vars to the template object for the selected album.
     */
    private function getAlbumTemplateVars(GalleryCreatorAlbumsModel $objAlbum, Template &$template): void
    {
        // Add meta tags to the page object
        if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) && 'detail_view' === $this->viewMode) {
            $this->pageModel->description = '' !== $objAlbum->description ? StringUtil::specialchars($objAlbum->description) : $this->pageModel->description;
            $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($objAlbum->keywords), ',');
        }

        // Back link
        $template->backLink = $this->generateBackLink($objAlbum);
        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $objAlbum->insertArticlePre ? sprintf('{{insert_article::%s}}', $objAlbum->insertArticlePre) : null;
        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $objAlbum->insertArticlePost ? sprintf('{{insert_article::%s}}', $objAlbum->insertArticlePost) : null;
    }
}
