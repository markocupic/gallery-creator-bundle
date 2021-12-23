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
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Haste\Util\Pagination;
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
    public const GC_VIEW_MODE_LIST = 'list_view';
    public const GC_VIEW_MODE_DETAIL = 'detail_view';
    public const GC_VIEW_MODE_SINGLE_IMAGE = 'single_image';

    private Security $security;

    private RequestStack $requestStack;

    private ScopeMatcher $scopeMatcher;

    private Connection $connection;

    private SecurityUtil $securityUtil;

    private AlbumUtil $albumUtil;

    private PictureUtil $pictureUtil;

    private ?string $viewMode = null;

    private ?GalleryCreatorAlbumsModel $activeAlbum = null;

    private array $arrSelectedAlbums = [];

    private ?ContentModel $model;

    private ?PageModel $pageModel;

    public function __construct(Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher, Connection $connection, SecurityUtil $securityUtil, AlbumUtil $albumUtil, PictureUtil $pictureUtil)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->connection = $connection;
        $this->securityUtil = $securityUtil;
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
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
        $session = $request->getSession();

        if ($this->security->getUser() instanceof FrontendUser) {
            $this->user = MemberModel::findByPk($this->security->getUser()->id);
        }

        // Get items url param from session
        if ($session->has('gc_redirect_to_album')) {
            Input::setGet('items', $session->get('gc_redirect_to_album'));
            $session->remove('gc_redirect_to_album');
        }

        // Set the item from the auto_item parameter
        if (Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('items', Input::get('auto_item'));
        }

        if (!empty(Input::get('items'))) {
            $this->viewMode = self::GC_VIEW_MODE_DETAIL;
        }

        // Remove or store the pagination variable "page" in the current session
        if (!Input::get('items')) {
            $session->remove('gc_pagination');
        }

        if (Input::get('page') && self::GC_VIEW_MODE_DETAIL !== $this->viewMode) {
            $session->set('gc_pagination', Input::get('page'));
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
            // Remove id from $this->arrSelectedAlbums if user is not allowed
            $albumsModel = GalleryCreatorAlbumsModel::findByPk($albumId);

            if (null !== $albumsModel) {
                if ($albumsModel->protected) {
                    if (!$this->securityUtil->isAuthorized($albumsModel)) {
                        unset($this->arrSelectedAlbums[$key]);
                    }
                }
            } else {
                unset($this->arrSelectedAlbums[$key]);
            }
        }

        // Build the new array
        $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

        // Abort if no album is selected
        if (empty($this->arrSelectedAlbums)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (Input::get('items')) {
            $this->activeAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $this->activeAlbum) {
                $this->viewMode = self::GC_VIEW_MODE_DETAIL;
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            // Check if user is authorized.
            // If not, show empty page.
            if (!$this->securityUtil->isAuthorized($this->activeAlbum)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }
        $this->viewMode = $this->viewMode ?: self::GC_VIEW_MODE_LIST;
        $this->viewMode = !empty(Input::get('img')) ? self::GC_VIEW_MODE_SINGLE_IMAGE : $this->viewMode;

        if (self::GC_VIEW_MODE_LIST === $this->viewMode) {
            // Redirect to detail view if there is only one album in the selection
            if (1 === \count($this->arrSelectedAlbums) && $this->model->gcRedirectSingleAlb) {
                $session->set('gc_redirect_to_album', GalleryCreatorAlbumsModel::findByPk($this->arrSelectedAlbums[0])->alias);
                Controller::reload();
            }

            // Show child albums? Yes or no?
            if ($this->model->gcShowChildAlbums) {
                foreach ($this->arrSelectedAlbums as $k => $albumId) {
                    $albumModel = GalleryCreatorAlbumsModel::findByPk($albumId);

                    if ($albumModel->pid > 0) {
                        unset($this->arrSelectedAlbums[$k]);
                    }
                }
                $this->arrSelectedAlbums = array_values($this->arrSelectedAlbums);

                if (empty($this->arrSelectedAlbums)) {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }
            }
        }

        if (self::GC_VIEW_MODE_DETAIL === $this->viewMode) {
            // For security reasons...
            if (!$this->model->gcPublishAllAlbums && !\in_array($this->activeAlbum->id, $this->arrSelectedAlbums, false)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        $template->viewMode = $this->viewMode;

        switch ($this->viewMode) {
            case self::GC_VIEW_MODE_LIST:

                $itemsTotal = \count($this->arrSelectedAlbums);
                $perPage = (int) $this->model->gcAlbumsPerPage;

                $objPagination = new Pagination($itemsTotal, $perPage, 'page_g'.$this->model->id);

                if ($objPagination->isOutOfRange()) {
                    throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
                }

                // Paginate the result
                $arrItems = $this->arrSelectedAlbums;
                $arrItems = \array_slice($arrItems, $objPagination->getOffset(), $objPagination->getLimit());

                $template->pagination = $objPagination->generate();

                $template->arrAlbums = array_map(
                    function ($id) {
                        $albumModel = GalleryCreatorAlbumsModel::findByPk($id);

                        return null !== $albumModel ? $this->albumUtil->getAlbumData($albumModel, $this->model) : [];
                    },
                    $arrItems
                );

                // Trigger gcGenerateFrontendTemplateHook
                $this->triggerGenerateFrontendTemplateHook($template);
                break;

            case self::GC_VIEW_MODE_DETAIL:

                // Get child albums
                if ($this->model->gcShowChildAlbums) {
                    $arrChildAlbums = $this->albumUtil->getChildAlbums($this->activeAlbum, $this->model);
                    $template->childAlbums = \count($arrChildAlbums) ? $arrChildAlbums : null;
                    $template->hasChildAlbums = (bool) \count($arrChildAlbums);
                }

                // Count items
                $itemsTotal = $this->connection->fetchOne(
                    'SELECT COUNT(id) AS itemsTotal FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?',
                    ['1', $this->activeAlbum->id]
                );

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
                break;

            case self::GC_VIEW_MODE_SINGLE_IMAGE:

                $arrActivePicture = $this->connection
                    ->fetchAssociative(
                        "SELECT * FROM tl_gallery_creator_pictures WHERE pid = ? AND name LIKE '".Input::get('img').".%'",
                        [$this->activeAlbum->id],
                    )
                ;

                if (!$arrActivePicture) {
                    throw new \Exception(sprintf('File with filename "%s" does not exist in album with alias "%s".', Input::get('img'), Input::get('items')));
                }

                $activePictureId = $arrActivePicture['id'];
                $published = $arrActivePicture['published'] && $this->activeAlbum->published;

                // For security reasons...
                if (!$published || (!$this->model->gcPublishAllAlbums && !\in_array($this->activeAlbum->id, $this->arrSelectedAlbums, false))) {
                    throw new \Exception('Picture with id '.$activePictureId." is either not published or not available or you haven't got enough permission to watch it!!!");
                }

                // Picture sorting
                $strSorting = empty($this->model->gcPictureSorting) || empty($this->model->gcPictureSortingDirection) ? 'sorting ASC' : $this->model->gcPictureSorting.' '.$this->model->gcPictureSortingDirection;
                $arrPictureIDS = $this->connection
                    ->fetchFirstColumn(
                        'SELECT id FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ? ORDER BY '.$strSorting,
                        ['1', $this->activeAlbum->id]
                    )
                ;

                $arrIDS = [];
                $currentIndex = null;

                if ($arrPictureIDS) {
                    foreach ($arrPictureIDS as $i => $id) {
                        if ((int) $activePictureId === (int) $id) {
                            $currentIndex = $i;
                        }
                        $arrIDS[] = $id;
                    }
                }

                $arrPictures = [];

                if (\count($arrIDS)) {
                    if (null !== ($picturesModelPrev = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex - 1]))) {
                        $arrPictures['prev'] = $this->pictureUtil->getPictureData($picturesModelPrev, $this->model);
                    }

                    if (null !== ($picturesModelCurrent = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex]))) {
                        $arrPictures['current'] = $this->pictureUtil->getPictureData($picturesModelCurrent, $this->model);
                    }

                    if (null !== ($picturesModelNext = GalleryCreatorPicturesModel::findByPk($arrIDS[$currentIndex + 1]))) {
                        $arrPictures['next'] = $this->pictureUtil->getPictureData($picturesModelNext, $this->model);
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

                // Augment template with more properties
                $this->getAlbumTemplateVars($this->activeAlbum, $template);

                // Count views
                $this->albumUtil->countAlbumViews($this->activeAlbum);

                // Trigger gcGenerateFrontendTemplateHook
                $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

                break;
        }

        // end switch
        return $template->getResponse();
    }

    /**
     * Generates the back link.
     *
     * @throws \Exception
     */
    protected function generateBackLink(GalleryCreatorAlbumsModel $albumModel): ?string
    {
        if ($this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest())) {
            return null;
        }

        // Generates the link to the parent album
        if ($this->model->gcShowChildAlbums && null !== ($objParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($albumModel))) {
            return StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$objParentAlbum->alias));
        }

        // Generates the link to the startup overview taking into account the pagination
        $url = $this->pageModel->getFrontendUrl();
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        if ($session->has('gc_pagination')) {
            $url = Url::addQueryString('page_g='.$session->get('gc_pagination'), $url);
        }

        return StringUtil::ampersand($url);
    }

    protected function listAllAlbums(int $pid = 0): array
    {
        $strSorting = empty($this->model->gcSorting) || empty($this->model->gcSortingDirection) ? 'date DESC' : $this->model->gcSorting.' '.$this->model->gcSortingDirection;

        if ($this->model->showChildAlbums) {
            $arrAlbumIDS = $this->connection
                ->fetchFirstColumn(
                    'SELECT id FROM tl_gallery_creator_albums WHERE published = ? ORDER BY '.$strSorting,
                    ['1']
                )
            ;
        } else {
            $arrAlbumIDS = $this->connection
                ->fetchFirstColumn(
                    'SELECT id FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY '.$strSorting,
                    [$pid, '1']
                )
            ;
        }

        return false !== $arrAlbumIDS ? array_map('intval', $arrAlbumIDS) : [];
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
        if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) && self::GC_VIEW_MODE_DETAIL === $this->viewMode) {
            $this->pageModel->description = '' !== $albumModel->description ? StringUtil::specialchars($albumModel->description) : $this->pageModel->description;
            $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($albumModel->keywords), ',');
        }

        // Back link
        $template->backLink = $this->generateBackLink($albumModel);
        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $albumModel->insertArticlePre ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePre) : null;
        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $albumModel->insertArticlePost ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePost) : null;
    }
}
