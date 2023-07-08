<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Model;

use Contao\Database;
use Contao\Model;

class GalleryCreatorAlbumsModel extends Model
{

    protected static $strTable = 'tl_gallery_creator_albums';


    public static function getParentAlbum(int $albumId): array|null
    {
        $objAlbPid = Database::getInstance()
            ->prepare('SELECT pid FROM tl_gallery_creator_albums WHERE id=?')
            ->execute($albumId)
        ;
        $parentAlb = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
            ->execute($objAlbPid->pid)
        ;

        if (0 === $parentAlb->numRows) {
            return null;
        }

        return $parentAlb->row();
    }

    /**
     * Get the parent album trail as an array.
     */
    public static function getParentAlbums(int $albumId): array
    {
        $arrParentAlbums = [];
        $objAlb = self::findByPk($albumId);

        if (null !== $objAlb) {
            $pid = $objAlb->pid;

            while ($pid > 0) {
                $parentAlb = self::findByPk($pid);

                if (null !== $parentAlb) {
                    $arrParentAlbums[] = $parentAlb->id;
                    $pid = $parentAlb->pid;
                }
            }
        }

        return $arrParentAlbums;
    }

    public static function getChildAlbums(int $parentId, string $strSorting = '', int $iterationDepth = null): array
    {
        // get the iteration depth
        $arrChildAlbums = [];

        if ('' === $strSorting) {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY sorting';
        } else {
            $strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY '.$strSorting;
        }
        $objAlb = Database::getInstance()->prepare($strSql)->execute($parentId);
        $depth = null !== $iterationDepth ? $iterationDepth - 1 : null;

        while ($objAlb->next()) {
            if ($depth < 0 && null !== $iterationDepth) {
                return $arrChildAlbums;
            }
            $arrChildAlbums[] = $objAlb->id;
            $arrChildAlbums = array_merge($arrChildAlbums, static::getChildAlbums($objAlb->id, $strSorting, $depth));
        }

        return $arrChildAlbums;
    }

    public static function hasChildAlbums(int $id): bool
    {
        $arrChildren = static::getChildAlbums($id);

        if (\count($arrChildren) >= 1) {
            return true;
        }

        return false;
    }
}
