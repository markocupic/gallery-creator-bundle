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
use Contao\Date;
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
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
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

    public function __construct(Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        $this->model = $model;
        $this->pageModel = $pageModel;
        $this->session = $request->getSession();

        if ($this->security->getUser() instanceof FrontendUser) {
            $this->user = MemberModel::findByPk($this->security->getUser()->id);
        }

        // Unset session entries
        $this->session->remove('gc_current_album');

        // Get items url param from session
        if ($this->session->has('gc_redirect_to_album')) {
            Input::setGet('items', $this->session->get('gc_redirect_to_album'));
            $this->session->remove('gc_redirect_to_album');
        }

        // Handle ajax requests
        if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) && Environment::get('isAjaxRequest')) {
            $this->handleAjax();
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
            if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) && true === $objAlbum->protected) {
                if (!$this->authenticate($objAlbum)) {
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
            if (!$this->authenticate($objAlbum)) {
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

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
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
                            $arrAlbums[] = GcHelper::getAlbumInformationArray($objAlbum, $this->model);
                        }
                    }
                }

                $template->imagemargin = Controller::generateMargin(unserialize($this->model->gcImageMarginAlbumListing));
                $template->arrAlbums = $arrAlbums;

                // Call gcGenerateFrontendTemplateHook
                $template = $this->callGcGenerateFrontendTemplateHook($this, $template, null);
                break;

            case 'detail_view':

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($this->intAlbumId);

                // generate the subalbum array
                if ($this->model->gcHierarchicalOutput) {
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
                $str_sorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;

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
                if (!$published || (!$this->model->gcPublishAllAlbums && !\in_array($this->intAlbumId, $this->arrSelectedAlbums, false))) {
                    throw new \Exception('Picture with id '.$picId." is either not published or not available or you haven't got enough permission to watch it!!!");
                }

                // picture sorting
                $str_sorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;
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

                    // Add previous and next links to the template
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
                $template->returnHref = StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items'), $this->pageModel->language));
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
    protected function handleAjax()
    {
        // Returns an array with all data to certain picture
        if (Input::get('isAjax') && Input::get('getImageByPk') && !empty(Input::get('id'))) {
            if (null !== ($objPicture = GalleryCreatorPicturesModel::findByPk(Input::get('id')))) {
                $arrPicture = GcHelper::getPictureInformationArray(Input::get('id'), $this->model);
                echo json_encode($arrPicture);
            }

            exit;
        }

        // Send image-date from a certain album as JSON encoded array to the browser
        // used f.ex. for the ce_gc_colorbox.html template
        // --> https://gist.github.com/markocupic/327413038262b2f84171f8df177cf021
        if (Input::get('isAjax') && Input::get('getImagesByPid') && Input::get('pid')) {
            // Do not send data if album is protected and the user has no access
            $objAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('albumId'));

            if (!$this->authenticate($objAlbum)) {
                return false;
            }

            // Init visit counter
            GcHelper::initCounter($objAlbum);

            // Sorting direction
            $sorting = $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;

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
        $strSorting = empty($this->model->gc_sorting) || empty($this->model->gcSortingDirection) ? 'date DESC' : $this->model->gc_sorting.' '.$this->model->gcSortingDirection;
        $objAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($pid, 1)
        ;

        return array_map('intval', $objAlbums->fetchEach('id'));
    }

    protected function callGcGenerateFrontendTemplateHook(self $objModule, Template $template, ?GalleryCreatorAlbumsModel $objAlbum = null): Template
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);

        // HOOK: modify the page or template object
        if (isset($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'] as $callback) {
                $template = $systemAdapter->importStatic($callback[0])->{$callback[1]}($objModule, $template, $objAlbum);
            }
        }

        return $template;
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

        // Store all album-data in the array
        $template->arrAlbumdata = $objAlbum->row();
        // Store the data of the current album in the session
        $this->session->set('gc_current_album', $objAlbum->row());
        // Back link
        $template->backLink = $this->generateBackLink($objAlbum);
        // The superordinate album name for the picture
        $template->albumname = $objAlbum->name;
        // Count album visitors
        $template->visitors = $objAlbum->vistors;
        // Album comment/description
        $template->albumComment = StringUtil::toHtml5($objAlbum->comment);
        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $objAlbum->insert_article_pre ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_pre) : null;
        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $objAlbum->insert_article_post ? sprintf('{{insert_article::%s}}', $objAlbum->insert_article_post) : null;
        // The event date as a unix timestamp
        $template->eventTstamp = $objAlbum->date;
        // The event date as a formatted date
        $template->eventDate = Date::parse(Config::get('dateFormat'), $objAlbum->date);
        // Margins
        $template->imagemargin = 'detail_view' === $this->viewMode ? Controller::generateMargin(StringUtil::deserialize($this->model->gcImageMarginDetailView), 'margin') : $this->generateMargin(deserialize($this->model->gcImageMarginAlbumListing), 'margin');
        // Content model
        $template->objElement = $this->model;
    }

    /**
     * Check if a logged in frontend user has access to a protected album.
     */
    private function authenticate(GalleryCreatorAlbumsModel $objAlbum): bool
    {
        if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest())) {
            if (!$objAlbum->protected) {
                return true;
            }

            if (null !== $this->user) {
                $allowedGroups = StringUtil::deserialize($objAlbum->groups, true);
                $userGroups = StringUtil::deserialize($this->user->groups, true);

                if (!empty(array_intersect($allowedGroups, $userGroups))) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
