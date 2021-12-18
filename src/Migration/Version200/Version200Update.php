<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * sdfdfsdfsdfsdf
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\GalleryCreatorBundle\Migration\Version200;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Version200Update extends AbstractMigration
{
    private const ALTERATION_TYPE_RENAME_COLUMN = 'alteration_type_rename_column';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array<string>
     */
    private $resultMessages = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'Gallery Creator Bundle 2.0.0 Update';
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
        $this->resultMessages = [];

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

                        $this->resultMessages[] = sprintf(
                            'Rename column %s.%s to %s.%s. ',
                            $strTable,
                            $arrAlteration['old'],
                            $strTable,
                            $arrAlteration['new'],
                        );
                    }
                }
            }
        }

        return $this->createResult(true, $this->resultMessages ? implode("\n", $this->resultMessages) : null);
    }

    private function getAlterationData(): array
    {
        return [
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
                'old' => 'gc_size_albumlisting',
                'new' => 'gcSizeAlbumListing',
                'sql' => 'varchar(64)',
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
            // tl_gallery_creator_albums
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'owners_name',
                'new' => 'ownersName',
                'sql' => 'TEXT',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'event_location',
                'new' => 'eventLocation',
                'sql' => 'varchar(255)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'img_resolution',
                'new' => 'imageResolution',
                'sql' => 'smallint(5)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'img_quality',
                'new' => 'imageQuality',
                'sql' => 'smallint(3)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'preserve_filename',
                'new' => 'preserveFilename',
                'sql' => 'char(1)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'insert_article_pre',
                'new' => 'insertArticlePre',
                'sql' => 'int(10)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'insert_article_post',
                'new' => 'insertArticlePost',
                'sql' => 'int(10)',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'visitors_details',
                'new' => 'visitorsDetails',
                'sql' => 'blob',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_albums',
                'old' => 'caption',
                'new' => 'caption',
                'sql' => 'text',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_pictures',
                'old' => 'caption',
                'new' => 'caption',
                'sql' => 'text',
            ],
        ];
    }
}
