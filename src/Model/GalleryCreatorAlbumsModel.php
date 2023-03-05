<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Model;

use Contao\Database;
use Contao\Model;

/**
 * Reads and writes tl_gallery_creator_albums.
 */
class GalleryCreatorAlbumsModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_gallery_creator_albums';

    /**
     * @param GalleryCreatorAlbumsModel $albumsModel
     *
     * @throws \Exception
     *
     * @return static|null
     */
    public static function getParentAlbum(self $albumsModel): self|null
    {
        return $albumsModel->getRelated('pid');
    }

    public static function getChildAlbumsIds(int $parentId, string $strSorting = '', int $iterationDepth = null): array|null
    {
        $arrChildAlbumsIds = [];

        if ('' === $strSorting) {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid = ? ORDER BY sorting';
        } else {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid = ? ORDER BY '.$strSorting;
        }

        $objAlbums = Database::getInstance()
            ->prepare($strSql)
            ->execute($parentId)
        ;

        $depth = null !== $iterationDepth ? $iterationDepth - 1 : null;

        while ($objAlbums->next()) {
            if ($depth < 0 && null !== $iterationDepth) {
                return $arrChildAlbumsIds;
            }
            $arrChildAlbumsIds[] = $objAlbums->id;

            $arrChildChildAlbumsIds = static::getChildAlbumsIds((int) $objAlbums->id, $strSorting, $depth);

            if ($arrChildChildAlbumsIds) {
                $arrChildAlbumsIds = array_merge($arrChildAlbumsIds, $arrChildChildAlbumsIds);
            }
        }

        return !empty($arrChildAlbumsIds) ? $arrChildAlbumsIds : null;
    }

    public static function hasChildAlbums(int $id): bool
    {
        $arrChildren = static::getChildAlbumsIds($id);

        if (!empty($arrChildren)) {
            return true;
        }

        return false;
    }
}
