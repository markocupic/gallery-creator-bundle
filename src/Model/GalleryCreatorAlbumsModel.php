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
    public static function getParentAlbum(self $albumsModel): ?self
    {
        return $albumsModel->getRelated('pid');
    }

    public static function getChildAlbums(int $parentId, string $strSorting = '', int $iterationDepth = null): array
    {
        $arrChildAlbums = [];

        if ('' === $strSorting) {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid= ? ORDER BY sorting';
        } else {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid= ? ORDER BY '.$strSorting;
        }

        $objAlbums = Database::getInstance()
            ->prepare($strSql)
            ->execute($parentId)
        ;

        $depth = null !== $iterationDepth ? $iterationDepth - 1 : null;

        while ($objAlbums->next()) {
            if ($depth < 0 && null !== $iterationDepth) {
                return $arrChildAlbums;
            }
            $arrChildAlbums[] = $objAlbums->id;
            $arrChildAlbums = array_merge($arrChildAlbums, static::getChildAlbums((int) $objAlbums->id, $strSorting, $depth));
        }

        return $arrChildAlbums;
    }

    public static function hasChildAlbums(int $id): bool
    {
        $arrChildren = static::getChildAlbums($id);

        if (\count($arrChildren) > 0) {
            return true;
        }

        return false;
    }
}
