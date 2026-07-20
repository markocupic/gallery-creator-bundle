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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\StringUtil;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AlbumUtil
{
    public function __construct(
        private ContaoFramework $framework,
        private ScopeMatcher $scopeMatcher,
        private RequestStack $requestStack,
        private CrawlerDetect $crawlerDetect = new CrawlerDetect(),
    ) {
    }

    public function countAlbumViews(GalleryCreatorAlbumsModel $albumModel): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->scopeMatcher->isFrontendRequest($request) || $this->crawlerDetect->isCrawler() || empty($request->getClientIp())) {
            return;
        }

        $visitors = $this->framework->getAdapter(StringUtil::class)->deserialize($albumModel->visitorsDetails, true);

        if (\in_array(md5($request->getClientIp()), $visitors, true)) {
            // Return if the visitor is already registered
            return;
        }

        // Keep visitor's data in the db unless 50 other users have visited the album
        if (50 === \count($visitors)) {
            // Slice last item
            $visitors = \array_slice($visitors, 0, \count($visitors) - 1);
        }

        $newVisitor = md5($request->getClientIp());

        if (!empty($visitors)) {
            // Insert the element to arrays first position
            array_unshift($visitors, $newVisitor);
        } else {
            $visitors[] = $newVisitor;
        }

        // Update database
        $albumModel->visitors = ++$albumModel->visitors;
        $albumModel->visitorsDetails = serialize($visitors);
        $albumModel->save();
    }

    /**
     * Return the level of an album or child album
     * (level_1, level_2, level_3, ...).
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
            $parentAlbumModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findById($pid);

            if (0 === ($pid = $parentAlbumModel->pid)) {
                $hasParent = false;
            }
        }

        return $level;
    }
}
