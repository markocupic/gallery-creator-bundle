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

namespace Markocupic\GalleryCreatorBundle\Listing;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

class AlbumListingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * Return the ids of all published albums below $pid that are within the
     * content element's album selection and pass the given authorization check.
     *
     * Authorization is passed in as a predicate so this service stays free of
     * the (readonly) SecurityUtil and remains easy to unit test.
     *
     * @param callable(GalleryCreatorAlbumsModel):bool $isAuthorized
     *
     * @return array<int, int>
     *
     * @throws DoctrineDBALException
     */
    public function getVisibleAlbumIds(ContentModel $model, int $pid, callable $isAuthorized): array
    {
        $albumsAdapter = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);

        $ids = [];

        foreach ($this->getSortedAlbumIds($model, $pid) as $id) {
            $albumModel = $albumsAdapter->findById($id);

            if (null === $albumModel) {
                continue;
            }

            // #1 Only show selected albums if the album selector is active.
            // #2 Do not show protected albums to unauthorized users.
            if (!$this->isInSelection($model, $albumModel) || !$isAuthorized($albumModel)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    public function isInSelection(ContentModel $model, GalleryCreatorAlbumsModel $albumModel): bool
    {
        // Only restrict if the album selection has been activated in the CE settings
        if (!$model->gcShowAlbumSelection) {
            return true;
        }

        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $selection = $stringUtil->deserialize($model->gcAlbumSelection, true);
        $selection = array_map('intval', $selection);

        return \in_array($albumModel->id, $selection, true);
    }

    /**
     * Return the ids of all published albums below $pid, sorted by the content
     * element's (whitelisted) sort column and direction. No selection or
     * authorization filtering is applied.
     *
     * @return array<int, int>
     *
     * @throws DoctrineDBALException
     */
    public function getSortedAlbumIds(ContentModel $model, int $pid): array
    {
        // Prevent SQL injection: only allow existing columns for sorting
        $columns = $this->connection->createSchemaManager()->listTableColumns(GalleryCreatorAlbumsModel::getTable());
        $sortColumn = \array_key_exists(strtolower((string) $model->gcSorting), $columns) ? $model->gcSorting : 'date';
        $sortDirection = 'ASC' === $model->gcSortingDirection ? 'ASC' : 'DESC';

        return array_map(
            static fn ($id): int => (int) $id,
            $this->connection->fetchFirstColumn(
                "SELECT id FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY $sortColumn $sortDirection",
                [$pid, 1],
            ),
        );
    }
}
