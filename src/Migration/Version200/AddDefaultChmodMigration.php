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

namespace Markocupic\GalleryCreatorBundle\Migration\Version200;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class AddDefaultChmodMigration extends AbstractMigration
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Add default chmod to tl_gallery_creator_albums';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $doMigration = false;
        $schemaManager = $this->connection->createSchemaManager();

        // Rename content type
        if ($schemaManager->tablesExist(['tl_gallery_creator_albums'])) {
            $columns = $schemaManager->listTableColumns('tl_gallery_creator_albums');

            if (isset($columns['chmod'])) {
                if ($this->connection->fetchOne('SELECT id FROM tl_gallery_creator_albums WHERE chmod = ?', [''])) {
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
        $this->framework->initialize();

        $config = $this->framework->getAdapter(Config::class);

        $defaultChmod = serialize($config->get('gcDefaultChmod'));

        $set = [
            'chmod' => $defaultChmod,
        ];

        $this->connection->update('tl_gallery_creator_albums', $set, ['chmod' => '']);

        return $this->createResult(true);
    }
}
