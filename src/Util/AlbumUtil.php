<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\StringUtil;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\HttpFoundation\RequestStack;

class AlbumUtil
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly RequestStack $requestStack,
    ) {
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

    /**
     * Return the level of an album or child album
     * (level_1, level_2, level_3,...).
     */
    public function getAlbumLevelFromPid(int $pid): int
    {
        $level = 1;

        if (0 === $pid) {
            return $level;
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
