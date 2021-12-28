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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Environment;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\DBAL\ParameterType;
use Haste\Util\Pagination;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @ContentElement(GalleryCreatorController::TYPE, category="gallery_creator_elements")
 */
class GalleryCreatorController extends AbstractGalleryCreatorController
{
    public const TYPE = 'gallery_creator';
    public const GC_VIEW_MODE_LIST = 'list_view';
    public const GC_VIEW_MODE_DETAIL = 'detail_view';
    public const GC_VIEW_MODE_SINGLE_IMAGE = 'single_image';

    protected ?string $viewMode = null;

    protected ?GalleryCreatorAlbumsModel $activeAlbum = null;

    protected array $arrAlbums = [];

    protected ?ContentModel $model;

    protected ?PageModel $pageModel;

    private AlbumUtil $albumUtil;

    private Connection $connection;

    private PictureUtil $pictureUtil;

    private RequestStack $requestStack;

    private SecurityUtil $securityUtil;

    private ScopeMatcher $scopeMatcher;

    private TwigEnvironment $twig;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, PictureUtil $pictureUtil, RequestStack $requestStack, SecurityUtil $securityUtil, ScopeMatcher $scopeMatcher, TwigEnvironment $twig)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->pictureUtil = $pictureUtil;
        $this->requestStack = $requestStack;
        $this->securityUtil = $securityUtil;
        $this->scopeMatcher = $scopeMatcher;
        $this->twig = $twig;

        parent::__construct($albumUtil, $connection, $pictureUtil, $securityUtil, $scopeMatcher);
    }

    /**
     * @throws DoctrineDBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render('@MarkocupicGalleryCreator/Backend/backend_element_view.html.twig', [])
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        // Set the item from the auto_item parameter
        if (Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('items', Input::get('auto_item'));
        }

        if (!empty(Input::get('items'))) {
            $this->viewMode = self::GC_VIEW_MODE_DETAIL;
        }

        if (!Input::get('items')) {
            $this->arrAlbums = $this->getAllAlbums(0);
        } else {
            $this->activeAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (null !== $this->activeAlbum && $this->activeAlbum->published) {
                $this->viewMode = self::GC_VIEW_MODE_DETAIL;
            } else {
                return new Response('aa', Response::HTTP_NO_CONTENT);
            }

            $this->arrAlbums = $this->getAllAlbums($this->activeAlbum->pid);

            // Check if user is authorized.
            // If not, show empty page.
            if (!$this->securityUtil->isAuthorized($this->activeAlbum)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        // Abort if no album is selected
        if (empty($this->arrAlbums)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->viewMode = $this->viewMode ?: self::GC_VIEW_MODE_LIST;
        $this->viewMode = !empty(Input::get('img')) ? self::GC_VIEW_MODE_SINGLE_IMAGE : $this->viewMode;

        if (self::GC_VIEW_MODE_DETAIL === $this->viewMode) {
            // For security reasons...
            if (!\in_array($this->activeAlbum->id, $this->arrAlbums, false)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    /**
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        $template->viewMode = $this->viewMode;

        switch ($this->viewMode) {
            case self::GC_VIEW_MODE_LIST:

                $itemsTotal = \count($this->arrAlbums);
                $perPage = (int) $this->model->gcAlbumsPerPage;

                $pagination = new Pagination($itemsTotal, $perPage, 'page_g'.$this->model->id);

                if ($pagination->isOutOfRange()) {
                    throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
                }

                // Paginate the result
                $arrItems = $this->arrAlbums;
                $arrItems = \array_slice($arrItems, $pagination->getOffset(), $pagination->getLimit());

                $template->pagination = $pagination->generate();

                $template->albums = array_map(
                    function ($id) {
                        $albumModel = GalleryCreatorAlbumsModel::findByPk($id);

                        return null !== $albumModel ? $this->getAlbumData($albumModel, $this->model) : [];
                    },
                    $arrItems
                );

                $template->content = $this->model->row();

                // Trigger gcGenerateFrontendTemplateHook
                $this->triggerGenerateFrontendTemplateHook($template);
                break;

            case self::GC_VIEW_MODE_DETAIL:

                // Add the picture collection and the pagination to the template.
                $this->addAlbumPicturesToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

                // Augment template with more properties.
                $this->addAlbumToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

                // Count views
                $this->albumUtil->countAlbumViews($this->activeAlbum);

                // Add content model to template.
                $template->content = $model->row();

                // Add meta tags to the page header.
                $this->addMetaTagsToPage($this->pageModel, $this->activeAlbum);

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
                $this->addAlbumToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

                // Count views
                $this->albumUtil->countAlbumViews($this->activeAlbum);

                // Add content model to template.
                $template->content = $model->row();

                // Add meta tags to the page header
                $this->addMetaTagsToPage($this->pageModel, $this->activeAlbum);

                // Trigger gcGenerateFrontendTemplateHook
                $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

                break;
        }

        // End switch
        return $template->getResponse();
    }

    /**
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

        // Generate the link to the startup overview taking into account the pagination
        $url = $this->pageModel->getFrontendUrl();

        return StringUtil::ampersand($url);
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function getAllAlbums($pid = 0): array
    {
        $arrIDS = [];
        $strSorting = empty($this->model->gcSorting) || empty($this->model->gcSortingDirection) ? 't.date DESC' : $this->model->gcSorting.' '.$this->model->gcSortingDirection;

        $stmt = $this->connection
            ->executeQuery(
                'SELECT id,pid FROM tl_gallery_creator_albums AS t WHERE t.published = ? ORDER BY '.$strSorting,
                ['1'],
                [ParameterType::STRING],
            )
       ;

        while (false !== ($arrAlbum = $stmt->fetchAssociative())) {
            $albumModel = GalleryCreatorAlbumsModel::findByPk($arrAlbum['id']);

            // Show only albums with the right pid
            if ((int) $pid !== (int) $albumModel->pid) {
                continue;
            }

            // If selection has been activated then do only show selected albums
            if ($this->model->gcShowAlbumSelection) {
                $arrSelection = StringUtil::deserialize($this->model->gcAlbumSelection, true);

                if (!\in_array($arrAlbum['id'], $arrSelection, false)) {
                    continue;
                }
            }

            // Do not show protected albums to unauthorized users.
            if (!$this->securityUtil->isAuthorized($albumModel)) {
                continue;
            }

            $arrIDS[] = (int) $arrAlbum['id'];
        }

        return $arrIDS;
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, Template $template, PageModel $pageModel): void
    {
        parent::addAlbumToTemplate($albumModel, $contentModel, $template, $pageModel);

        // Back link
        $template->backLink = $this->generateBackLink($albumModel);

        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $albumModel->insertArticlePre ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePre) : null;

        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $albumModel->insertArticlePost ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePost) : null;
    }
}
