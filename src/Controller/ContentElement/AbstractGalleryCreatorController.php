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
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Haste\Util\Pagination;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;

abstract class AbstractGalleryCreatorController extends AbstractContentElementController
{
    protected AlbumUtil $albumUtil;

    protected Connection $connection;

    protected PictureUtil $pictureUtil;

    protected SecurityUtil $securityUtil;

    protected ScopeMatcher $scopeMatcher;

    public function __construct(DependencyAggregate $dependencyAggregate)
    {
        $this->albumUtil = $dependencyAggregate->albumUtil;
        $this->connection = $dependencyAggregate->connection;
        $this->pictureUtil = $dependencyAggregate->pictureUtil;
        $this->securityUtil = $dependencyAggregate->securityUtil;
        $this->scopeMatcher = $dependencyAggregate->scopeMatcher;
    }

    public function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $albumModel): ?FilesModel
    {
        if (null === ($pictureModel = GalleryCreatorPicturesModel::findByPk($albumModel->thumb))) {
            $pictureModel = GalleryCreatorPicturesModel::findOneByPid($albumModel->id);
        }

        if (null !== $pictureModel && null !== ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return $filesModel;
        }

        return null;
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

        $href = sprintf(
            StringUtil::ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') ? '/%s' : '/items/%s'), $objPage->language)),
            $albumModel->alias,
        );

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

        // Meta
        $arrMeta = [];
        $arrMeta['alt'] = StringUtil::specialchars($albumModel->name);
        $arrMeta['caption'] = StringUtil::toHtml5(nl2br((string) $albumModel->caption));
        $arrMeta['title'] = $albumModel->name.' ['.($countPictures ? $countPictures.' '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['pictures'] : '').($childAlbumCount > 0 ? ' '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['contains'].' '.$childAlbumCount.'  '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbums'].']' : ']');

        $arrAlbum = $albumModel->row();
        $arrAlbum['dateFormatted'] = Date::parse(Config::get('dateFormat'), $albumModel->date);
        $arrAlbum['meta'] = new Metadata($arrMeta);
        $arrAlbum['href'] = $href;
        $arrAlbum['countPictures'] = $countPictures;

        $arrAlbum['cssClass'] = implode(' ', array_filter($arrCssClasses));
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

    /**
     * @throws Exception
     * @throws DoctrineDBALDriverException
     */
    public function getChildAlbums(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel, bool $blnOnlyAllowed = false): ?array
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
        $pageModel->description = '' !== $albumModel->description ? StringUtil::specialchars($albumModel->description) : StringUtil::specialchars($this->pageModel->description);
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($albumModel->keywords), ',');
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
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, Template $template, PageModel $pageModel): void
    {
        $template->album = $this->getAlbumData($albumModel, $contentModel);
    }

    /**
     * @param $template
     *
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function addAlbumPicturesToTemplate(GalleryCreatorAlbumsModel $albumsModel, ContentModel $contentModel, $template, PageModel $pageModel): void
    {

        // Count items
        $itemsTotal = $this->connection->fetchOne(
            'SELECT COUNT(id) AS itemsTotal FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?',
            ['1', $albumsModel->id]
        );

        $perPage = (int) $contentModel->gcThumbsPerPage;

        // Use Haste\Util\Pagination to generate the pagination.
        $pagination = new Pagination($itemsTotal, $perPage, 'page_d'.$contentModel->id);

        if ($pagination->isOutOfRange()) {
            throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
        }

        $template->detailPagination = $pagination->generate();

        // Picture sorting
        $arrSorting = empty($contentModel->gcPictureSorting) || empty($contentModel->gcPictureSortingDirection) ? ['sorting', 'ASC'] : [$contentModel->gcPictureSorting, $contentModel->gcPictureSortingDirection];

        // Sort by name will be done below.
        $arrSorting[0] = str_replace('name', 'id', $arrSorting[0]);

        $stmt = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tl_gallery_creator_pictures', 't')
            ->where('t.pid = :pid')
            ->andWhere('t.published = :published')
            ->orderBy(...$arrSorting)
            ->setParameter('published', '1')
            ->setParameter('pid', $albumsModel->id)
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit())
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
        $template->arrPictures = array_values($arrPictures);
    }
}
