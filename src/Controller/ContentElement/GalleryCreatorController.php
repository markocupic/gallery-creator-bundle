<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Markocupic\GalleryCreatorBundle\Breadcrumb\BreadcrumbGenerator;
use Markocupic\GalleryCreatorBundle\Dto\PaginationResultDto;
use Markocupic\GalleryCreatorBundle\Event\GenerateFrontendTemplateEvent;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Pagination\AlbumListPaginator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

#[AsContentElement(category: 'gallery_creator_elements')]
class GalleryCreatorController extends AbstractGalleryCreatorController
{
    public const string TYPE = 'gallery_creator';

    protected string|null $viewMode = null;

    protected GalleryCreatorAlbumsModel|null $activeAlbum = null;

    protected array $arrAlbumListing = [];

    protected ContentModel|null $model = null;

    protected PageModel|null $pageModel = null;

    private bool $showAlbumDetail = false;

    private bool $showAlbumListing = false;

    /**
     * @throws DoctrineDBALException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array|null $classes = null, PageModel|null $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($this->container->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            return new Response(
                $this->container->get('twig')->render('@MarkocupicGalleryCreator/Backend/backend_element_view.html.twig', []),
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        $listing = $this->albumListingService;
        $isAuthorized = fn (GalleryCreatorAlbumsModel $album): bool => $this->securityUtil->isAuthorized($album);

        // It's important to call Input::get('auto_item') at least once,
        // otherwise Contao throws a Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        if (!$this->framework->getAdapter(Input::class)->get('auto_item')) {
            if (!$model->gcShowAlbumSelection) {
                $this->arrAlbumListing = $listing->getVisibleAlbumIds($model, 0, $isAuthorized);
            } else {
                // Find the pid of the root album
                $arrIds = $this->framework->getAdapter(StringUtil::class)->deserialize($model->gcAlbumSelection, true);

                if (!empty($arrIds)) {
                    $pid = $this->connection->fetchOne(
                        'SELECT pid FROM tl_gallery_creator_albums WHERE id IN(?) ORDER BY pid',
                        [
                            array_map('intval', $arrIds),
                        ],
                        [
                            ArrayParameterType::INTEGER,
                        ],
                    );
                    $this->arrAlbumListing = $listing->getVisibleAlbumIds($model, (int) $pid, $isAuthorized);
                } else {
                    return new Response('', Response::HTTP_NO_CONTENT);
                }
            }

            $this->showAlbumListing = true;
        } else {
            $albumAlias = $this->framework->getAdapter(Input::class)->get('auto_item');
            $this->activeAlbum = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findOneBy(
                ['tl_gallery_creator_albums.alias = ? AND tl_gallery_creator_albums.published = ?'],
                [$albumAlias, 1],
            );

            if (null !== $this->activeAlbum && $this->securityUtil->isAuthorized($this->activeAlbum) && $listing->isInSelection($model, $this->activeAlbum)) {
                $this->showAlbumDetail = true;

                // Show album listing if active album contains child albums
                $this->showAlbumListing = $this->activeAlbum->hasChildAlbums((int) $this->activeAlbum->id);
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->arrAlbumListing = $listing->getVisibleAlbumIds($model, (int) $this->activeAlbum->id, $isAuthorized);
        }

        // Tag the albums
        if ($this->getResponseTagger()) {
            $arrIds = $this->arrAlbumListing;

            if ($this->activeAlbum) {
                $arrIds[] = $this->activeAlbum->id;
                $arrIds = array_unique($arrIds);
            }

            $this->getResponseTagger()->addTags(array_map(static fn ($id) => 'contao.db.tl_gallery_creator_albums.'.$id, $arrIds));
        }

        return parent::__invoke($request, $this->model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        return [
            AlbumListPaginator::class => AlbumListPaginator::class,
            BreadcrumbGenerator::class => BreadcrumbGenerator::class,
            'contao.routing.scope_matcher' => ScopeMatcher::class,
            '?fos_http_cache.http.symfony_response_tagger' => SymfonyResponseTagger::class,
            'twig' => TwigEnvironment::class,
        ] + parent::getSubscribedServices();
    }

    /**
     * If an album contains child albums, we have both,
     * "$this->showAlbumListing" and "$this->showAlbumDetail".
     *
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        // Set defaults
        $template->set('showAlbumDetail', false);
        $template->set('showAlbumListing', false);
        $template->set('hasBreadcrumb', false);
        $template->set('items_per_page', $model->gcThumbsPerPage);

        if ($this->model->gcAddBreadcrumb) {
            $template->set('hasBreadcrumb', true);
            $template->set('breadcrumb', $this->container->get(BreadcrumbGenerator::class)->generate(
                $this->activeAlbum,
                $this->pageModel,
                fn (GalleryCreatorAlbumsModel $album): bool => $this->albumListingService->isInSelection($this->model, $album) && $this->securityUtil->isAuthorized($album),
            ));
        }

        if ($this->showAlbumListing) {
            $template->set('showAlbumListing', true);

            // Add a CSS class to the body tag
            $this->pageModel->loadDetails()->cssClass = $this->pageModel->loadDetails()->cssClass.' gc-listing-view';

            /** @var PaginationResultDto $pagination */
            $pagination = $this->container->get(AlbumListPaginator::class)->paginate(
                $this->arrAlbumListing,
                (int) $this->model->gcAlbumsPerPage,
                'page_g'.$this->model->id,
            );

            $pagedAlbumIds = $pagination->items;

            $template->set('listPagination', $pagination->markup);

            $template->set('albums', array_map(
                function ($id) {
                    $albumModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findById($id);

                    return null !== $albumModel ? $this->getAlbumData($albumModel, $this->model) : [];
                },
                $pagedAlbumIds,
            ));
        }

        if ($this->showAlbumDetail) {
            $template->set('showAlbumDetail', true);

            // Add a CSS class to the body tag
            $this->pageModel->loadDetails()->cssClass = $this->pageModel->loadDetails()->cssClass.' gc-detail-view';

            $this->overridePageMetaData($this->activeAlbum);

            // Add the picture collection and the pagination to the template.
            $this->addAlbumPicturesToTemplate($this->activeAlbum, $this->model, $template);

            // Augment template with more properties.
            $this->addAlbumToTemplate($this->activeAlbum, $this->model, $template, $this->pageModel);

            // Count views
            $this->albumUtil->countAlbumViews($this->activeAlbum);

            // Get the album level
            $template->set('level', $this->albumUtil->getAlbumLevelFromPid((int) $this->activeAlbum->pid));

            // Add meta tags to the page header.
            $this->addMetaTagsToPage($this->pageModel, $this->activeAlbum);
        }

        // Dispatch the GenerateFrontendTemplateEvent
        $this->eventDispatcher->dispatch(new GenerateFrontendTemplateEvent($this, $template, $request, $this->activeAlbum));

        return $template->getResponse();
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        parent::addAlbumToTemplate($albumModel, $contentModel, $template, $pageModel);

        // Backlink
        $template->set('backLink', $this->generateBackLink($albumModel, $contentModel, $pageModel) ?: false);

        // In the detail view, an article can optionally be added in front of the album
        $template->set('insertArticlePre', $albumModel->insertArticlePre ? "{{insert_article::$albumModel->insertArticlePre}}" : false);

        // In the detail view, an article can optionally be added right after the album
        $template->set('insertArticlePost', $albumModel->insertArticlePost ? "{{insert_article::$albumModel->insertArticlePost}}" : false);
    }

    private function generateBackLink(GalleryCreatorAlbumsModel $albumModel, ContentModel $model, PageModel $pageModel): string
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Generate the link to the parent album
        if (null !== ($parentAlbum = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->getParentAlbum($albumModel))) {
            if (!$model->gcShowAlbumSelection || $this->albumListingService->isInSelection($model, $parentAlbum)) {
                return $stringUtilAdapter->ampersand($pageModel->getFrontendUrl('/'.$parentAlbum->alias));
            }
        }

        // Generate the link to the startup overview
        return $stringUtilAdapter->ampersand($pageModel->getFrontendUrl());
    }

    private function getResponseTagger(): SymfonyResponseTagger|null
    {
        return $this->container->has('fos_http_cache.http.symfony_response_tagger')
            ? $this->container->get('fos_http_cache.http.symfony_response_tagger')
            : null;
    }
}
