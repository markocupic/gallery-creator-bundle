<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Migration\Version200;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Version200Update extends AbstractMigration
{
    private const ALTERATION_TYPE_RENAME_COLUMN = 'alteration_type_rename_column';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'Gallery Creator Bundle version 2.0.0 update';
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

        // Rename content type
        if ($schemaManager->tablesExist(['tl_content'])) {
            $columns = $schemaManager->listTableColumns('tl_content');

            if (isset($columns['type'])) {
                if ($this->connection->fetchOne('SELECT id FROM tl_content WHERE type = ?', ['gallery_creator_ce'])) {
                    $doMigration = true;
                }

                if ($this->connection->fetchOne('SELECT id FROM tl_content WHERE type = ?', ['gallery_creator_ce_news'])) {
                    $doMigration = true;
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
        $resultMessages = [];

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

                        $this->connection->executeQuery($strQuery);

                        $resultMessages[] = sprintf(
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

        // Rename content type
        if ($schemaManager->tablesExist(['tl_content'])) {
            $columns = $schemaManager->listTableColumns('tl_content');

            if (isset($columns['type'])) {
                $set = [
                    'type' => 'gallery_creator',
                ];

                if ($this->connection->update('tl_content', $set, ['tl_content.type' => 'gallery_creator_ce'])) {
                    $resultMessages[] = 'Rename tl_content.type from gallery_creator_ce to gallery_creator WHERE tl_content.type = gallery_creaor_ce.';
                }

                $set = [
                    'type' => 'gallery_creator_news',
                ];

                if ($this->connection->update('tl_content', $set, ['tl_content.type' => 'gallery_creator_ce_news'])) {
                    $resultMessages[] = 'Rename tl_content.type from gallery_creator_ce_news to gallery_creator_news WHERE tl_content.type = gallery_creaor_ce_news.';
                }
            }
        }

        return $this->createResult(true, $resultMessages ? implode("\n", $resultMessages) : null);
    }

    private function getAlterationData(): array
    {
        return [
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
                'old' => 'gc_AlbumsPerPage',
                'new' => 'gcAlbumsPerPage',
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
                'new' => 'gcFullSize',
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
                'new' => 'gcAlbumSelection',
                'sql' => 'blob',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_content',
                'old' => 'gc_publish_single_album',
                'new' => 'gcPublishSingleAlbum',
                'sql' => 'blob',
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
                'old' => 'comment',
                'new' => 'caption',
                'sql' => 'text',
            ],
            [
                'type' => self::ALTERATION_TYPE_RENAME_COLUMN,
                'table' => 'tl_gallery_creator_pictures',
                'old' => 'comment',
                'new' => 'caption',
                'sql' => 'text',
            ],
        ];
    }
}
