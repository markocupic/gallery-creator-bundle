<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @ContentElement(GalleryCreatorController::TYPE, category="gallery_creator_elements", template="ce_gallery_creator")
 */
class GalleryCreatorController extends AbstractGalleryCreatorController
{
    public const TYPE = 'gallery_creator';

    protected ContaoFramework $framework;
    protected TwigEnvironment $twig;
    protected HtmlDecoder $htmlDecoder;
    protected ?SymfonyResponseTagger $responseTagger;
    protected ?string $viewMode = null;
    protected ?GalleryCreatorAlbumsModel $activeAlbum = null;
    protected array $arrAlbumListing = [];
    protected ?ContentModel $model;
    protected ?PageModel $pageModel;

    // Adapters
    protected Adapter $config;
    protected Adapter $environment;
    protected Adapter $galleryCreatorAlbumsModel;
    protected Adapter $input;
    protected Adapter $stringUtil;

    private bool $showAlbumDetail = false;
    private bool $showAlbumListing = false;

    public function __construct(DependencyAggregate $dependencyAggregate, ContaoFramework $framework, TwigEnvironment $twig, HtmlDecoder $htmlDecoder, ?SymfonyResponseTagger $responseTagger)
    {
        parent::__construct($dependencyAggregate);
        $this->framework = $framework;
        $this->twig = $twig;
        $this->htmlDecoder = $htmlDecoder;
        $this->responseTagger = $responseTagger;

        // Adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->galleryCreatorAlbumsModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);
        $this->input = $this->framework->getAdapter(Input::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
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

        // Set the item from the auto_item parameter and remove auto_item from unused route parameters
        if (isset($_GET['auto_item']) && '' !== $_GET['auto_item']) {
            $this->input->setGet('auto_item', $_GET['auto_item']);
        }

        // It's important to call Input::get('auto_item') at least once,
        // otherwise Contao throws a Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        if (!$this->input->get('auto_item')) {
            if (!$model->gcShowAlbumSelection) {
                $this->arrAlbumListing = $this->getAlbumsByPid(0);
            } else {
                // Find the pid of the root album
                $arrIds = $this->stringUtil->deserialize($model->gcAlbumSelection, true);

                if (!empty($arrIds)) {
                    $pid = $this->connection->fetchOne('SELECT pid FROM tl_gallery_creator_albums WHERE id IN('.implode(',', $arrIds).') ORDER BY pid');
                    $this->arrAlbumListing = $this->getAlbumsByPid($pid);
                } else {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }
            }

            $this->showAlbumListing = true;
        } else {
            $albumAlias = $this->input->get('auto_item');
            $this->activeAlbum = $this->galleryCreatorAlbumsModel->findOneBy(
                ['tl_gallery_creator_albums.alias = ? AND tl_gallery_creator_albums.published = ?'],
                [$albumAlias, '1']
            );

            if (null !== $this->activeAlbum && $this->securityUtil->isAuthorized($this->activeAlbum) && $this->isInSelection($this->activeAlbum)) {
                $this->showAlbumDetail = true;

                // Show album listing if active album contains child albums
                $this->showAlbumListing = $this->activeAlbum->hasChildAlbums((int) $this->activeAlbum->id);
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->arrAlbumListing = $this->getAlbumsByPid($this->activeAlbum->id);
        }

        // Tag the albums
        if ($this->responseTagger) {
            $arrIds = $this->arrAlbumListing;

            if ($this->activeAlbum) {
                $arrIds[] = $this->activeAlbum->id;
                $arrIds = array_unique($arrIds);
            }

            $this->responseTagger->addTags(array_map(static fn ($id) => 'contao.db.tl_gallery_creator_albums.'.$id, $arrIds));
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    /**
     * If an album contains child albums, we have both,
     * "$this->showAlbumListing" and "$this->showAlbumDetail".
     *
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        // Set defaults
        $template->showAlbumDetail = false;
        $template->showAlbumListing = false;
        $template->hasBreadcrumb = false;

        if ($this->model->gcAddBreadcrumb) {
            $template->hasBreadcrumb = true;
            $template->breadcrumb = $this->generateBreadcrumb();
        }

        if ($this->showAlbumListing) {
            $template->showAlbumListing = true;
            $template->listPagination = '';

            // Add a CSS class to the body tag
            $this->pageModel->loadDetails()->cssClass = $this->pageModel->loadDetails()->cssClass.' gc-listing-view';

            $arrItems = $this->arrAlbumListing;

            $perPage = (int) $this->model->gcAlbumsPerPage;

            if ($perPage > 0) {
                $id = 'page_g'.$this->model->id;
                $page = $this->input->get($id) ?? 1;
                $total = \count($arrItems);

                // Do not index or cache the page if the page number is outside the range
                if ($page < 1 || $page > max(ceil($total / $perPage), 1)) {
                    throw new PageNotFoundException('Page not found: '.$this->environment->get('uri'));
                }

                $offset = ($page - 1) * $perPage;
                $limit = min($perPage + $offset, $total);

                $objPagination = new Pagination($total, $perPage, $this->config->get('maxPaginationLinks'), $id);
                $template->listPagination = $objPagination->generate("\n  ");

                // Paginate the result
                $arrItems = \array_slice($arrItems, $offset, $limit);
            }

            $template->albums = array_map(
                function ($id) {
                    $albumModel = $this->galleryCreatorAlbumsModel->findByPk($id);

                    return null !== $albumModel ? $this->getAlbumData($albumModel, $this->model) : [];
                },
                $arrItems
            );

            $template->content = $this->model->row();
        }

        if ($this->showAlbumDetail) {
            $template->showAlbumDetail = true;

            // Add a CSS class to the body tag
            $this->pageModel->loadDetails()->cssClass = $this->pageModel->loadDetails()->cssClass.' gc-detail-view';

            $this->overridePageMetaData($this->activeAlbum);

            // Add the picture collection and the pagination to the template.
            $this->addAlbumPicturesToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

            // Augment template with more properties.
            $this->addAlbumToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

            // Count views
            $this->albumUtil->countAlbumViews($this->activeAlbum);

            // Add content model to template.
            $template->content = $model->row();

            // Get the album level
            $template->level = $this->albumUtil->getAlbumLevelFromPid((int) $this->activeAlbum->pid);

            // Add meta tags to the page header.
            $this->addMetaTagsToPage($this->pageModel, $this->activeAlbum);
        }

        // Trigger gcGenerateFrontendTemplateHook
        $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

        return $template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function generateBackLink(GalleryCreatorAlbumsModel $albumModel): string
    {
        // Generate the link to the parent album
        if (null !== ($parentAlbum = $this->galleryCreatorAlbumsModel->getParentAlbum($albumModel))) {
            if ($this->model->gcShowAlbumSelection) {
                if ($this->isInSelection($parentAlbum)) {
                    $params = '/'.$parentAlbum->alias;

                    return $this->stringUtil->ampersand($this->pageModel->getFrontendUrl($params));
                }
            } else {
                $params = '/'.$parentAlbum->alias;

                return $this->stringUtil->ampersand($this->pageModel->getFrontendUrl($params));
            }
        }

        // Generate the link to the startup overview
        $url = $this->pageModel->getFrontendUrl();

        return $this->stringUtil->ampersand($url);
    }

    /**
     * @throws DoctrineDBALException
     */
    protected function getAlbumsByPid($pid = 0): array
    {
        $arrIDS = [];

        $strSorting = empty($this->model->gcSorting) || empty($this->model->gcSortingDirection) ? 't.date DESC' : $this->model->gcSorting.' '.$this->model->gcSortingDirection;

        $stmt = $this->connection
            ->executeQuery(
                'SELECT id,pid FROM tl_gallery_creator_albums AS t WHERE t.pid = ? AND t.published = ? ORDER BY '.$strSorting,
                [$pid, '1'],
            )
        ;

        while (false !== ($arrAlbum = $stmt->fetchAssociative())) {
            $albumModel = $this->galleryCreatorAlbumsModel->findByPk($arrAlbum['id']);

            // #1 Do only show selected albums, if album selector has been activated in the CE settings
            // #2 Do not show protected albums to unauthorized users.
            if (!$this->isInSelection($albumModel) || !$this->securityUtil->isAuthorized($albumModel)) {
                continue;
            }

            $arrIDS[] = (int) $arrAlbum['id'];
        }

        return $arrIDS;
    }

    protected function isInSelection(GalleryCreatorAlbumsModel $albumModel): bool
    {
        //  Do only show selected albums, if selection has been activated then
        if ($this->model->gcShowAlbumSelection) {
            $arrSelection = $this->stringUtil->deserialize($this->model->gcAlbumSelection, true);

            if (!\in_array($albumModel->id, $arrSelection, false)) {
                return false;
            }
        }

        return true;
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
        $template->backLink = $this->generateBackLink($albumModel) ?: false;

        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $albumModel->insertArticlePre ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePre) : false;

        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $albumModel->insertArticlePost ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePost) : false;
    }

    protected function generateBreadcrumb(): string
    {
        $items = [];

        $template = new FrontendTemplate('mod_breadcrumb');

        $album = $this->activeAlbum;

        while (null !== $album) {
            if (!$this->isInSelection($album) || !$this->securityUtil->isAuthorized($album)) {
                break;
            }

            $item = [];
            $item['class'] = 'gc-breadcrumb-item';
            $item['link'] = $this->htmlDecoder->inputEncodedToPlainText($album->name);
            $item['name'] = $this->htmlDecoder->inputEncodedToPlainText($album->name);

            if ($album->id === $this->activeAlbum->id) {
                $item['isActive'] = true;
            } else {
                $item['title'] = $this->stringUtil->specialchars($album->name);
                $params = '/'.$album->alias;
                $item['href'] = $this->stringUtil->ampersand($this->pageModel->getFrontendUrl($params));
            }
            $items[] = $item;

            $album = $this->galleryCreatorAlbumsModel->getParentAlbum($album);
        }

        // Add root
        $item = [];
        $item['class'] = 'gc-breadcrumb-item gc-breadcrumb-root-item';
        $item['name'] = $this->htmlDecoder->inputEncodedToPlainText($this->pageModel->title);
        $item['link'] = $this->htmlDecoder->inputEncodedToPlainText($this->pageModel->title);
        $item['title'] = null;
        $item['href'] = null;
        $item['isActive'] = false;

        if ($this->activeAlbum) {
            $item['title'] = $this->stringUtil->specialchars($this->pageModel->title);
            $item['href'] = $this->stringUtil->ampersand($this->pageModel->getFrontendUrl());
        } else {
            $item['isActive'] = true;
        }

        $items[] = $item;

        $items = array_reverse($items);

        $this->galleryCreatorAlbumsModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);

        $htmlDecoder = $this->htmlDecoder;

        $template->getSchemaOrgData = static function () use ($items, $htmlDecoder): array {
            $jsonLd = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [],
            ];

            $position = 0;

            foreach ($items as $item) {
                $jsonLd['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => ++$position,
                    'item' => [
                        '@id' => isset($item['href']) ?: './',
                        'name' => $htmlDecoder->inputEncodedToPlainText($item['link']),
                    ],
                ];
            }

            return $jsonLd;
        };

        $template->items = $items;

        return $template->getResponse()->getContent();
    }
}
