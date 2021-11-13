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

namespace Markocupic\GalleryCreatorBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Version2Migration extends AbstractMigration
{
    private const ALTERATION_TYPE_RENAME_COLUMN = 'alteration_type_rename_column';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $doMigration = false;
        $schemaManager = $this->connection->getSchemaManager();
        $arrAlterations = $this->getAlterationData();

        foreach ($arrAlterations as $arrAlteration) {
            $type = $arrAlteration['type'];

            // Version 2 migration: "Rename columns"
            if (self::ALTERATION_TYPE_RENAME_COLUMN === $type) {
                $strTable = $arrAlteration['table'];
                // If the database table itself does not exist we should do nothing
                if ($schemaManager->tablesExist([$strTable])) {
                    $columns = $schemaManager->listTableColumns($strTable);

                    if (isset($columns[strtolower($arrAlteration['old'])]) && !isset($columns[strtolower($arrAlteration['new'])])) {
                        $doMigration = true;
                    }
                }
            }
        }

        return $doMigration;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $arrMessage = [];
        $arrMessage = ['Run Gallery Creator version 2 migration script: '];

        $schemaManager = $this->connection->getSchemaManager();
        $arrAlterations = $this->getAlterationData();

        foreach ($arrAlterations as $arrAlteration) {
            $type = $arrAlteration['type'];

            // Version 2 migration: "Rename columns"
            if (self::ALTERATION_TYPE_RENAME_COLUMN === $type) {
                $strTable = $arrAlteration['table'];

                if ($schemaManager->tablesExist([$strTable])) {
                    $columns = $schemaManager->listTableColumns($strTable);

                    if (isset($columns[strtolower($arrAlteration['old'])]) && !isset($columns[strtolower($arrAlteration['new'])])) {
                        $strQuery = sprintf(
                            'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
                            $strTable,
                            $arrAlteration['old'],
                            $arrAlteration['new'],
                            $arrAlteration['sql'],
                        );
                        $this->connection->query($strQuery);

                        $arrMessage[] = sprintf(
                            'Rename field %s.%s to %s.%s. ',
                            $strTable,
                            $arrAlteration['old'],
                            $strTable,
                            $arrAlteration['new'],
                        );
                    }
                }
            }
        }

        return new MigrationResult(
            true,
            implode(' ', $arrMessage)
        );
    }

    private function getAlterationData(): array
    {
        return [
            // tl_content
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_template',
                'new' => 'gcTemplate',
                'sql' => 'varchar(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_hierarchicalOutput',
                'new' => 'gcHierarchicalOutput',
                'sql' => 'char(1)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_sorting',
                'new' => 'gcSorting',
                'sql' => 'char(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gcSorting_direction',
                'new' => 'gcSortingDirection',
                'sql' => 'char(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_picture_sorting',
                'new' => 'gcPictureSorting',
                'sql' => 'char(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_picture_sorting_direction',
                'new' => 'gcPictureSortingDirection',
                'sql' => 'char(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_redirectSingleAlb',
                'new' => 'gcRedirectSingleAlb',
                'sql' => 'char(1)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_AlbumsPerPage',
                'new' => 'gcAlbumsPerPage',
                'sql' => 'smallint(5)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_paginationNumberOfLinks',
                'new' => 'gcPaginationNumberOfLinks',
                'sql' => 'smallint(5)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_size_detailview',
                'new' => 'gcSizeDetailView',
                'sql' => 'varchar(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_imagemargin_detailview',
                'new' => 'gcImageMarginDetailView',
                'sql' => 'varchar(128)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_size_albumlisting',
                'new' => 'gcSizeAlbumListing',
                'sql' => 'varchar(64)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_imagemargin_albumlisting',
                'new' => 'gcImageMarginAlbumListing',
                'sql' => 'varchar(128)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_fullsize',
                'new' => 'gcFullsize',
                'sql' => 'char(1)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_ThumbsPerPage',
                'new' => 'gcThumbsPerPage',
                'sql' => 'smallint(5)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_publish_albums',
                'new' => 'gcPublishAlbums',
                'sql' => 'blob',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_publish_single_album',
                'new' => 'gcPublishSingleAlbum',
                'sql' => 'blob',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_publish_all_albums',
                'new' => 'gcPublishAllAlbums',
                'sql' => 'char(1)',
            ],
            // tl_user
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_user',
                'old' => 'gc_img_resolution',
                'new' => 'gcImageResolution',
                'sql' => 'varchar(12)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_user',
                'old' => 'gc_img_quality',
                'new' => 'gcImageQuality',
                'sql' => 'smallint(3)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_user',
                'old' => 'gc_be_uploader_template',
                'new' => 'gcBeUploaderTemplate',
                'sql' => 'varchar(64)',
            ],
        ];
    }
}
