<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * sdfdfsdfsdfsdf
 *
 * @license LGPL-3.0-or-later
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

    public static function getParentAlbum(self $objAlbum): ?self
    {
        return $objAlbum->getRelated('pid');
    }

    /**
     * @param $parentId
     * @param string $strSorting
     * @param null   $iterationDepth
     */
    public static function getChildAlbums($parentId, $strSorting = '', $iterationDepth = null): array
    {
        // get the iteration depth
        $iterationDepth = '' === $iterationDepth ? null : $iterationDepth;

        $arrSubAlbums = [];

        if ('' === $strSorting) {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY sorting';
        } else {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY '.$strSorting;
        }

        $objAlb = Database::getInstance()
            ->prepare($strSql)
            ->execute($parentId)
        ;

        $depth = null !== $iterationDepth ? $iterationDepth - 1 : null;

        while ($objAlb->next()) {
            if ($depth < 0 && null !== $iterationDepth) {
                return $arrSubAlbums;
            }
            $arrSubAlbums[] = $objAlb->id;
            $arrSubAlbums = array_merge($arrSubAlbums, static::getChildAlbums($objAlb->id, $strSorting, $depth));
        }

        return $arrSubAlbums;
    }

    /**
     * @param $id
     */
    public static function hasChildAlbums($id): bool
    {
        $arrChilds = static::getChildAlbums($id);

        if (\count($arrChilds) >= 1) {
            return true;
        }

        return false;
    }
}
