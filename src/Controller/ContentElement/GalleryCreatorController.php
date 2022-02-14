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
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Haste\Util\Pagination;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\HttpFoundation\Request;
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

    protected TwigEnvironment $twig;
    protected ?string $viewMode = null;
    protected ?GalleryCreatorAlbumsModel $activeAlbum = null;
    protected array $arrAlbumListing = [];
    protected ?ContentModel $model;
    protected ?PageModel $pageModel;

    private bool $showAlbumDetail = false;
    private bool $showAlbumListing = false;

    public function __construct(DependencyAggregate $dependencyAggregate, TwigEnvironment $twig)
    {
        parent::__construct($dependencyAggregate);
        $this->twig = $twig;
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

        if (!Input::get('items')) {
            if (!$model->gcShowAlbumSelection) {
                $this->arrAlbumListing = $this->getAlbumsByPid(0);
            } else {
                // Find root album
                $pid = $this->connection->fetchOne('SELECT pid FROM tl_gallery_creator_albums WHERE id IN('.implode(',', StringUtil::deserialize($model->gcAlbumSelection)).') ORDER BY pid ASC');
                $this->arrAlbumListing = $this->getAlbumsByPid($pid);
            }
            $this->showAlbumListing = true;
        } else {
            $this->activeAlbum = GalleryCreatorAlbumsModel::findByAlias(Input::get('items'));

            if (
                null !== $this->activeAlbum &&
                $this->activeAlbum->published &&
                $this->securityUtil->isAuthorized($this->activeAlbum) &&
                $this->isInSelection($this->activeAlbum)
            ) {
                $this->showAlbumDetail = true;

                // Show album listing if active album contains child albums
                $this->showAlbumListing = $this->activeAlbum->hasChildAlbums((int) $this->activeAlbum->id);
            } else {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            $this->arrAlbumListing = $this->getAlbumsByPid($this->activeAlbum->id);
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
        if ($this->model->gcAddBreadcrumb) {
            $template->hasBreadcrumb = true;
            $template->breadcrumb = $this->generateBreadcrumb();
        }

        if ($this->showAlbumListing) {
            $template->showAlbumListing = true;

            $itemsTotal = \count($this->arrAlbumListing);
            $perPage = (int) $this->model->gcAlbumsPerPage;

            $pagination = new Pagination($itemsTotal, $perPage, 'page_g'.$this->model->id);

            if ($pagination->isOutOfRange()) {
                throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
            }

            // Paginate the result
            $arrItems = $this->arrAlbumListing;
            $arrItems = \array_slice($arrItems, $pagination->getOffset(), $pagination->getLimit());

            $template->listPagination = $pagination->generate();

            $template->albums = array_map(
                function ($id) {
                    $albumModel = GalleryCreatorAlbumsModel::findByPk($id);

                    return null !== $albumModel ? $this->getAlbumData($albumModel, $this->model) : [];
                },
                $arrItems
            );

            $template->content = $this->model->row();
        }

        if ($this->showAlbumDetail) {
            $template->showAlbumDetail = true;

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
        }

        // Trigger gcGenerateFrontendTemplateHook
        $this->triggerGenerateFrontendTemplateHook($template, $this->activeAlbum);

        // End switch
        return $template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function generateBackLink(GalleryCreatorAlbumsModel $albumModel): string
    {
        // Generates the link to the parent album
        if (null !== ($objParentAlbum = GalleryCreatorAlbumsModel::getParentAlbum($albumModel))) {
            if ($this->model->gcShowAlbumSelection) {
                if ($this->isInSelection($objParentAlbum)) {
                    return StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$objParentAlbum->alias));
                }
            } else {
                return StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$objParentAlbum->alias));
            }
        }

        // Generate the link to the startup overview taking into account the pagination
        $url = $this->pageModel->getFrontendUrl();

        return StringUtil::ampersand($url);
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
            $albumModel = GalleryCreatorAlbumsModel::findByPk($arrAlbum['id']);

            // If selection has been activated then do only show selected albums
            if (!$this->isInSelection($albumModel)) {
                continue;
            }

            // Do not show protected albums to unauthorized users.
            if (!$this->securityUtil->isAuthorized($albumModel)) {
                continue;
            }

            $arrIDS[] = (int) $arrAlbum['id'];
        }

        return $arrIDS;
    }

    protected function isInSelection(GalleryCreatorAlbumsModel $albumModel): bool
    {
        // If selection has been activated then do only show selected albums
        if ($this->model->gcShowAlbumSelection) {
            $arrSelection = StringUtil::deserialize($this->model->gcAlbumSelection, true);

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
        $template->backLink = $this->generateBackLink($albumModel);

        // In the detail view, an article can optionally be added in front of the album
        $template->insertArticlePre = $albumModel->insertArticlePre ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePre) : null;

        // In the detail view, an article can optionally be added right after the album
        $template->insertArticlePost = $albumModel->insertArticlePost ? sprintf('{{insert_article::%s}}', $albumModel->insertArticlePost) : null;
    }

    protected function generateBreadcrumb()
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
            $item['link'] = $album->name;

            if ($album->id === $this->activeAlbum->id) {
                $item['isActive'] = true;
            } else {
                $item['title'] = StringUtil::specialchars($album->name);
                $item['href'] = StringUtil::ampersand($this->pageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').$album->alias));
            }
            $items[] = $item;

            $album = GalleryCreatorAlbumsModel::getParentAlbum($album);
        }

        // Add root
        $item = [];
        $item['class'] = 'gc-breadcrumb-item gc-breadcrumb-root-item';
        $item['name'] = $this->pageModel->title;
        $item['link'] = $this->pageModel->title;

        if ($this->activeAlbum) {
            $item['title'] = StringUtil::specialchars($this->pageModel->title);
            $item['href'] = StringUtil::ampersand($this->pageModel->getFrontendUrl());
        } else {
            $item['isActive'] = true;
        }

        $items[] = $item;

        $items = array_reverse($items);

        $template->getSchemaOrgData = static function () use ($items): array {
            $jsonLd = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [],
            ];

            $position = 0;

            $htmlDecoder = false;

            // Contao >= 4.13
            if (System::getContainer()->has('contao.string.html_decoder')) {
                $htmlDecoder = System::getContainer()->get('contao.string.html_decoder');
            }

            foreach ($items as $item) {
                $jsonLd['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => ++$position,
                    'item' => [
                        '@id' => isset($item['href']) ?: './',
                        'name' => $htmlDecoder ? $htmlDecoder->inputEncodedToPlainText($item['link']) : StringUtil::inputEncodedToPlainText($item['link']),
                    ],
                ];
            }

            return $jsonLd;
        };

        $template->items = $items;

        return $template->parse();
    }
}
