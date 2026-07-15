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

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class Content
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_content', target: 'fields.gcPublishSingleAlbum.options', priority: 100)]
    public function optionsCbGcPublishSingleAlbum(DataContainer $dc): array
    {
        $arrOpt = [];

        $schemaManager = $this->connection->createSchemaManager();

        $columns = $schemaManager->listTableColumns('tl_gallery_creator_albums');

        $arrContent = $this->connection->fetchAssociative('SELECT * FROM tl_content WHERE id = ?', [$dc->activeRecord->id]);

        // Prevent SQL injection
        if (!\array_key_exists(strtolower($arrContent['gcSorting']), $columns)) {
            $arrContent['gcSorting'] = 'date';
        }

        // Prevent SQL injection
        if (!\in_array($arrContent['gcSortingDirection'], ['ASC', 'DESC'], true)) {
            $arrContent['gcSortingDirection'] = 'DESC';
        }

        $strSorting = $arrContent['gcSorting'].' '.$arrContent['gcSortingDirection'];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_gallery_creator_albums WHERE published = ? ORDER BY '.$strSorting, [1]);

        while (false !== ($album = $stmt->fetchAssociative())) {
            $arrOpt[$album['id']] = '[ID '.$album['id'].'] '.$album['name'];
        }

        return $arrOpt;
    }
}
