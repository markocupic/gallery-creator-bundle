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
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;

abstract class AbstractGalleryCreatorController extends AbstractContentElementController
{
    protected AlbumUtil $albumUtil;
    protected MarkdownUtil $markdownUtil;
    protected Connection $connection;
    protected PictureUtil $pictureUtil;
    protected SecurityUtil $securityUtil;
    protected ScopeMatcher $scopeMatcher;
    protected ResponseContextAccessor $responseContextAccessor;
    protected InsertTagParser $insertTagParser;
    protected HtmlDecoder $htmlDecoder;

    public function __construct(DependencyAggregate $dependencyAggregate)
    {
        $this->albumUtil = $dependencyAggregate->albumUtil;
        $this->markdownUtil = $dependencyAggregate->markdownUtil;
        $this->connection = $dependencyAggregate->connection;
        $this->pictureUtil = $dependencyAggregate->pictureUtil;
        $this->securityUtil = $dependencyAggregate->securityUtil;
        $this->scopeMatcher = $dependencyAggregate->scopeMatcher;
        $this->responseContextAccessor = $dependencyAggregate->responseContextAccessor;
        $this->insertTagParser = $dependencyAggregate->insertTagParser;
        $this->htmlDecoder = $dependencyAggregate->htmlDecoder;
    }

    public function overridePageMetaData(GalleryCreatorAlbumsModel $objAlbum): void
    {
        // Overwrite the page metadata (see #2853, #4955 and #87)
        $responseContext = $this->responseContextAccessor->getResponseContext();

        if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
            /** @var HtmlHeadBag $htmlHeadBag */
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);

            if ($objAlbum->pageTitle) {
                $htmlHeadBag->setTitle($objAlbum->pageTitle); // Already stored decoded
            } elseif ($objAlbum->title) {
                $htmlHeadBag->setTitle($this->htmlDecoder->inputEncodedToPlainText($objAlbum->title));
            }

            if ($objAlbum->description) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($objAlbum->description));
            } elseif ($objAlbum->teaser) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($objAlbum->teaser));
            }

            if ($objAlbum->robots) {
                $htmlHeadBag->setMetaRobots($objAlbum->robots);
            }
        }
    }

    /**
     * @throws DoctrineDBALDriverException
     * @throws Exception
     */
    public function getAlbumData(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        global $objPage;

        // Count images
        $countPictures = $this->connection
            ->fetchOne(
                'SELECT COUNT(id) AS countPictures FROM tl_gallery_creator_pictures WHERE pid = ? AND published = ?',
                [$albumModel->id, '1'],
            )
        ;

        // Image size
        $size = StringUtil::deserialize($contentElementModel->gcSizeAlbumListing);
        $arrSize = !empty($size) && \is_array($size) ? $size : null;

        $params = '/'.$albumModel->alias;
        $href = StringUtil::ampersand($objPage->getFrontendUrl($params));

        /** @var FilesModel $previewImage */
        $previewImage = $this->getAlbumPreviewThumb($albumModel);

        $arrCssClasses = [];
        $arrCssClasses[] = 'gc-level-'.$this->albumUtil->getAlbumLevelFromPid((int) $albumModel->pid);
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums((int) $albumModel->id) ? 'gc-has-child-album' : null;
        $arrCssClasses[] = !$countPictures ? 'gc-empty-album' : null;

        // Do not show child albums, in news elements
        if (GalleryCreatorNewsController::TYPE === $contentElementModel->type) {
            $childAlbums = null;
        } else {
            $childAlbums = $this->getChildAlbums($albumModel, $contentElementModel, true);
        }

        $childAlbumCount = null !== $childAlbums ? \count($childAlbums) : 0;

        $teaser = $this->insertTagParser->replaceInline(nl2br((string) $albumModel->teaser));
        $caption = $this->insertTagParser->replaceInline(nl2br((string) $albumModel->caption));
        $markdown = 'markdown' === $albumModel->captionType && $albumModel->markdownCaption ? $this->markdownUtil->parse($albumModel->markdownCaption) : null;

        // Meta
        $arrMeta = [];
        $arrMeta['alt'] = StringUtil::specialchars($albumModel->name);
        $arrMeta['caption'] = $teaser;
        $arrMeta['title'] = StringUtil::specialchars($albumModel->name);

        $arrAlbum = $albumModel->row();
        $arrAlbum['teaser'] = $teaser;
        $arrAlbum['caption'] = $caption;
        $arrAlbum['markdownCaption'] = $markdown ?: false;
        $arrAlbum['dateFormatted'] = Date::parse(Config::get('dateFormat'), $albumModel->date);
        $arrAlbum['meta'] = new Metadata($arrMeta);
        $arrAlbum['href'] = $href;
        $arrAlbum['countPictures'] = $countPictures;

        $arrAlbum['cssClass'] = !(empty(implode(' ', array_filter($arrCssClasses)))) ? implode(' ', array_filter($arrCssClasses)) : false;
        $arrAlbum['figureUuid'] = $previewImage ? $previewImage->uuid : null;
        $arrAlbum['figureSize'] = !empty($arrSize) ? $arrSize : null;
        $arrAlbum['figureOptions'] = [
            'metadata' => new Metadata($arrMeta),
            'linkHref' => $href,
        ];

        $arrAlbum['hasChildAlbums'] = (bool) $childAlbumCount;
        $arrAlbum['countChildAlbums'] = $childAlbums ? \count($childAlbums) : 0;
        $arrAlbum['childAlbums'] = $childAlbums;

        return $arrAlbum;
    }

    public function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $albumModel): FilesModel|null
    {
        if (null === ($pictureModel = GalleryCreatorPicturesModel::findByPk($albumModel->thumb))) {
            $pictureModel = GalleryCreatorPicturesModel::findOneByPid($albumModel->id);
        }

        if (null !== $pictureModel && $pictureModel->published && null !== ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return $filesModel;
        }

        return null;
    }

    /**
     * @throws Exception
     * @throws DoctrineDBALDriverException
     */
    public function getChildAlbums(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel, bool $blnOnlyAllowed = false): array|null
    {
        $strSorting = $contentElementModel->gcSorting.' '.$contentElementModel->gcSortingDirection;

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY '.$strSorting,
            [$albumModel->id, '1']
        );

        $arrChildAlbums = [];

        while (false !== ($objChildAlbums = $stmt->fetchAssociative())) {
            $objChildAlbum = GalleryCreatorAlbumsModel::findByPk($objChildAlbums['id']);

            if ($blnOnlyAllowed) {
                if ($contentElementModel->gcShowAlbumSelection) {
                    $arrAllowed = StringUtil::deserialize($contentElementModel->gcAlbumSelection, true);

                    if (!\in_array($objChildAlbum->id, $arrAllowed, false)) {
                        continue;
                    }

                    if (!$this->securityUtil->isAuthorized($objChildAlbum)) {
                        continue;
                    }
                }
            }

            if (null !== $objChildAlbum) {
                $arrChildAlbums[] = $this->getAlbumData($objChildAlbum, $contentElementModel);
            }
        }

        return !empty($arrChildAlbums) ? $arrChildAlbums : null;
    }

    /**
     * Add meta tags to the page header.
     */
    protected function addMetaTagsToPage(PageModel $pageModel, GalleryCreatorAlbumsModel $albumModel): void
    {
        $pageModel->description = '' !== $albumModel->description ? StringUtil::specialchars($albumModel->description) : StringUtil::specialchars($pageModel->description);
    }

    protected function triggerGenerateFrontendTemplateHook(FragmentTemplate $template, GalleryCreatorAlbumsModel $albumModel = null): void
    {
        // Trigger the galleryCreatorGenerateFrontendTemplate - HOOK
        if (isset($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($this, $template, $albumModel);
            }
        }
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        $template->set('album', $this->getAlbumData($albumModel, $contentModel));
    }

    /**
     * @param $template
     *
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function addAlbumPicturesToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        $template->set('detailPagination', '');

        // Picture sorting
        $arrSorting = empty($contentModel->gcPictureSorting) || empty($contentModel->gcPictureSortingDirection) ? ['sorting', 'ASC'] : [$contentModel->gcPictureSorting, $contentModel->gcPictureSortingDirection];

        // Sort by name will be done below.
        $arrSorting[0] = str_replace('name', 'id', $arrSorting[0]);

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tl_gallery_creator_pictures', 't')
            ->where('t.pid = :pid')
            ->andWhere('t.published = :published')
            ->orderBy(...$arrSorting)
            ->setParameter('published', '1')
            ->setParameter('pid', $albumModel->id)
        ;

        $perPage = (int) $contentModel->gcThumbsPerPage;

        if ($perPage > 0) {
            $id = 'page_d'.$contentModel->id;
            $page = Input::get($id) ?? 1;
            $offset = ($page - 1) * $perPage;

            $total = $this->connection->fetchOne(
                'SELECT COUNT(id) AS itemsTotal FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?',
                ['1', $albumModel->id]
            );

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $perPage), 1)) {
                throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
            }

            $pagination = new Pagination($total, $perPage, Config::get('maxPaginationLinks'), $id);
            $template->detailPagination = $pagination->generate("\n  ");

            $qb->setFirstResult($offset);
            $qb->setMaxResults($perPage);
        }

        $arrPictures = [];

        $stmt = $qb->executeQuery();

        while (false !== ($rowPicture = $stmt->fetchAssociative())) {
            $filesModel = FilesModel::findByUuid($rowPicture['uuid']);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }

            if (null !== ($picturesModel = GalleryCreatorPicturesModel::findByPk($rowPicture['id']))) {
                // Prevent overriding items with same basename
                $arrPictures[$basename.'-id-'.$rowPicture['id']] = $this->pictureUtil->getPictureData($picturesModel, $contentModel);
            }
        }

        // Sort by name
        if ('name' === $contentModel->gcPictureSorting) {
            if ('ASC' === $contentModel->gcPictureSortingDirection) {
                uksort($arrPictures, static fn ($a, $b): int => strnatcasecmp(basename($a), basename($b)));
            } else {
                uksort($arrPictures, static fn ($a, $b): int => -strnatcasecmp(basename($a), basename($b)));
            }
        }

        // Add pictures to the template.
        $template->set('arrPictures', array_values($arrPictures));
    }
}
