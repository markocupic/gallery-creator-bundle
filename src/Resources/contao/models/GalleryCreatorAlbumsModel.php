<?php

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Contao;

/**
 * Reads and writes tl_gallery_creator_albums
 */
class GalleryCreatorAlbumsModel extends \Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_gallery_creator_albums';

	/**
	 * @param $AlbumId
	 * @return |null
	 */
	public static function getParentAlbum($AlbumId): ?array
	{
		if (null === ($objAlbum = static::findByPk($AlbumId)))
		{
			return null;
		}

		if (null === ($objParentAlbum = $objAlbum->getRelated('pid')))
		{
			return null;
		}

		return $objParentAlbum->row();
	}

	/**
	 * @param $AlbumId
	 * @return array|null
	 */
	public static function getParentAlbums($AlbumId): ?array
	{
		$arrParentAlbums = array();

		if (null === ($objAlb = static::findByPk($AlbumId)))
		{
			return null;
		}

		$pid = $objAlb->pid;

		while ($pid > 0)
		{
			$parentAlb = static::findByPk($pid);

			if ($parentAlb !== null)
			{
				$arrParentAlbums[] = $parentAlb->id;
				$pid = $parentAlb->pid;
			}
		}

		return $arrParentAlbums;
	}

	/**
	 * @param $parentId
	 * @param  string $strSorting
	 * @param  null   $iterationDepth
	 * @return array
	 */
	public static function getChildAlbums($parentId, $strSorting = '', $iterationDepth = null): array
	{
		// get the iteration depth
		$iterationDepth = $iterationDepth === '' ? null : $iterationDepth;

		$arrSubAlbums = array();

		if ($strSorting == '')
		{
			$strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY sorting';
		}
		else
		{
			$strSql = 'SELECT id FROM tl_gallery_creator_albums WHERE pid=? ORDER BY ' . $strSorting;
		}

		$objAlb = Database::getInstance()
			->prepare($strSql)
			->execute($parentId);

		$depth = $iterationDepth !== null ? $iterationDepth - 1 : null;

		while ($objAlb->next())
		{
			if ($depth < 0 && $iterationDepth !== null)
			{
				return $arrSubAlbums;
			}
			$arrSubAlbums[] = $objAlb->id;
			$arrSubAlbums = array_merge($arrSubAlbums, static::getChildAlbums($objAlb->id, $strSorting, $depth));
		}

		return $arrSubAlbums;
	}

	/**
	 * @param $id
	 * @return bool
	 */
	public static function hasChildAlbums($id): bool
	{
		$arrChilds = static::getChildAlbums($id);

		if (\count($arrChilds) >= 1)
		{
			return true;
		}

		return false;
	}
}
