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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Date;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class AlbumUtil
{
    private ScopeMatcher $scopeMatcher;

    private RequestStack $requestStack;

    private Connection $connection;

    public function __construct(ScopeMatcher $scopeMatcher, RequestStack $requestStack, Connection $connection)
    {
        $this->scopeMatcher = $scopeMatcher;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
    }

    /**
     * @throws Exception
     */
    public function getAlbumData(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        global $objPage;
        $request = $this->requestStack->getCurrentRequest();

        $childAlbumCount = \count(GalleryCreatorAlbumsModel::getChildAlbums($albumModel->id));

        // Count images
        $countPictures = $this->connection
            ->executeStatement(
                'SELECT id FROM tl_gallery_creator_pictures WHERE pid = ? AND published = ?',
                [$albumModel->id, '1'],
            )
        ;

        // Image size
        $size = StringUtil::deserialize($contentElementModel->gcSizeAlbumListing);
        $arrSize = !empty($size) && \is_array($size) ? $size : null;

        $href = null;

        if ($request && $this->scopeMatcher->isFrontendRequest($request)) {
            $href = sprintf(
                StringUtil::ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') ? '/%s' : '/items/%s'), $objPage->language)),
                $albumModel->alias,
            );
        }

        /** @var FilesModel $previewImage */
        $previewImage = $this->getAlbumPreviewThumb($albumModel);

        $arrMeta = [];
        $arrMeta['alt'] = StringUtil::specialchars($albumModel->name);
        $arrMeta['caption'] = StringUtil::toHtml5(nl2br((string) $albumModel->caption));
        $arrMeta['title'] = $albumModel->name.' ['.($countPictures ? $countPictures.' '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['pictures'] : '').($contentElementModel->gcShowChildAlbums && $childAlbumCount > 0 ? ' '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['contains'].' '.$childAlbumCount.'  '.$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbums'].']' : ']');

        $arrCssClasses = [];
        $arrCssClasses[] = 'gc-level-'.$this->getAlbumLevelFromPid((int) $albumModel->pid);
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums((int) $albumModel->id) ? 'gc-has-child-album' : null;
        $arrCssClasses[] = !$countPictures ? 'gc-empty-album' : null;

        // Add formatted date to the album model
        $albumModel->dateFormatted = Date::parse(Config::get('dateFormat'), $albumModel->date);

        return [
            'row' => $albumModel->row(),
            'meta' => new Metadata($arrMeta),
            'href' => $href,
            'countPictures' => $countPictures,
            'hasChildAlbums' => (bool) $childAlbumCount,
            'countChildAlbums' => $childAlbumCount,
            'cssClass' => implode(' ', array_filter($arrCssClasses)),
            'figureUuid' => $previewImage ? $previewImage->uuid : null,
            'figureSize' => !empty($arrSize) ? $arrSize : null,
            'figureOptions' => [
                'metadata' => new Metadata($arrMeta),
                'linkHref' => $href,
            ],
        ];
    }

    /**
     * @throws Exception
     * @throws DoctrineDBALDriverException
     */
    public function getChildAlbums(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        $strSorting = $contentElementModel->gcSorting.' '.$contentElementModel->gcSortingDirection;

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY '.$strSorting,
            [$albumModel->id, '1']
        );

        $arrChildAlbums = [];

        while (false !== ($objChildAlbums = $stmt->fetchAssociative())) {
            // If it is a content element only
            if ($contentElementModel->gcPublishAlbums) {
                if (!$contentElementModel->gcPublishAllAlbums) {
                    if (!\in_array($objChildAlbums['id'], StringUtil::deserialize($contentElementModel->gcPublishAlbums), false)) {
                        continue;
                    }
                }
            }
            $objChildAlbum = GalleryCreatorAlbumsModel::findByPk($objChildAlbums['id']);

            if (null !== $objChildAlbum) {
                $arrChildAlbums[] = $this->getAlbumData($objChildAlbum, $contentElementModel);
            }
        }

        return $arrChildAlbums;
    }

    public function countAlbumViews(GalleryCreatorAlbumsModel $albumModel): void
    {
        $crawlerDetect = new CrawlerDetect();
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->scopeMatcher->isFrontendRequest($request) || $crawlerDetect->isCrawler()) {
            return;
        }

        $arrVisitors = StringUtil::deserialize($albumModel->visitorsDetails, true);

        if (\in_array(md5((string) $_SERVER['REMOTE_ADDR']), $arrVisitors, true)) {
            // Return if the visitor is already registered
            return;
        }

        // Keep visitors data in the db unless 50 other users have visited the album
        if (50 === \count($arrVisitors)) {
            // Slice last item
            $arrVisitors = \array_slice($arrVisitors, 0, \count($arrVisitors) - 1);
        }

        $newVisitor = md5((string) $_SERVER['REMOTE_ADDR']);

        if (!empty($arrVisitors)) {
            // Insert the element to arrays first position
            array_unshift($arrVisitors, $newVisitor);
        } else {
            $arrVisitors[] = $newVisitor;
        }

        // Update database
        $albumModel->visitors = ++$albumModel->visitors;
        $albumModel->visitorsDetails = serialize($arrVisitors);
        $albumModel->save();
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
     * Return the level of an album or child album
     * (level_0, level_1, level_2,...).
     */
    public function getAlbumLevelFromPid(int $pid): int
    {
        $level = 0;

        if (0 === $pid) {
            return 0;
        }

        $hasParent = true;

        while ($hasParent) {
            ++$level;
            $parentAlbumModel = GalleryCreatorAlbumsModel::findByPk($pid);

            if (0 === ($pid = (int) $parentAlbumModel->pid)) {
                $hasParent = false;
            }
        }

        return $level;
    }
}
