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
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class AlbumUtil
{
    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(ScopeMatcher $scopeMatcher, RequestStack $requestStack)
    {
        $this->scopeMatcher = $scopeMatcher;
        $this->requestStack = $requestStack;
    }

    public function getAlbumData(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        global $objPage;
        $request = $this->requestStack->getCurrentRequest();

        $subAlbumCount = \count(GalleryCreatorAlbumsModel::getChildAlbums($albumModel->id));

        $objPics = Database::getInstance()
            ->prepare('SELECT COUNT(id) as count FROM tl_gallery_creator_pictures WHERE pid=? AND published=?')
            ->execute($albumModel->id, '1')
        ;
        $countPics = $objPics->count;

        $arrSize = StringUtil::deserialize($contentElementModel->gcSizeAlbumListing);

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
        $arrMeta['title'] = $albumModel->name.' ['.($countPics ? $countPics.' '.$GLOBALS['TL_LANG']['gallery_creator']['pictures'] : '').($contentElementModel->gcHierarchicalOutput && $subAlbumCount > 0 ? ' '.$GLOBALS['TL_LANG']['gallery_creator']['contains'].' '.$subAlbumCount.'  '.$GLOBALS['TL_LANG']['gallery_creator']['subalbums'].']' : ']');

        $arrCssClasses = [];
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums($albumModel->id) ? 'has-child-album' : '';
        $arrCssClasses[] = !$countPics ? 'empty-album' : '';

        return [
            'albumModel' => $albumModel,
            'href' => $href,
            'count' => $countPics,
            'caption' => StringUtil::specialchars(StringUtil::toHtml5($arrMeta['caption'])),
            'countSubalbums' => $subAlbumCount,
            'cssClass' => implode(' ', array_filter($arrCssClasses)),
            'figureUuid' => $previewImage ? $previewImage->uuid : null,
            'figureSize' => !empty($arrSize) ? $arrSize : null,
            'figureOptions' => [
                'metadata' => new Metadata($arrMeta),
                'linkHref' => $href,
            ],
        ];
    }

    public function getSubalbumsData(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        $strSorting = $contentElementModel->gcSorting.' '.$contentElementModel->gcSortingDirection;
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($albumModel->id, '1')
        ;
        $arrSubalbums = [];

        while ($objSubAlbums->next()) {
            // If it is a content element only
            if ($contentElementModel->gcPublishAlbums) {
                if (!$contentElementModel->gcPublishAllAlbums) {
                    if (!\in_array($objSubAlbums->id, StringUtil::deserialize($contentElementModel->gcPublishAlbums), false)) {
                        continue;
                    }
                }
            }
            $objSubAlbum = GalleryCreatorAlbumsModel::findByPk($objSubAlbums->id);

            if (null !== $objSubAlbum) {
                $arrSubalbums[] = $this->getAlbumData($objSubAlbum, $contentElementModel);
            }
        }

        return $arrSubalbums;
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
            // slice the last position
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
     * Return the level of an album or subalbum
     * (level_0, level_1, level_2,...).
     */
    public function getAlbumLevelFromPid(int $pid): int
    {
        $level = 0;

        if (0 === $pid) {
            return $level;
        }
        $hasParent = true;

        while ($hasParent) {
            ++$level;
            $parentAlbumModel = GalleryCreatorAlbumsModel::findByPk($pid);

            if ($parentAlbumModel->pid < 1) {
                $hasParent = false;
            }
            $pid = $parentAlbumModel->pid;
        }

        return $level;
    }
}
