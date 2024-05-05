<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Revise;

use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class ReviseAlbumDatabase
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly string $projectDir,
        private readonly string $galleryCreatorUploadPath,
    ) {
    }

    /**
     * @throws Exception
     * @throws DoctrineDBALException
     */
    public function run(GalleryCreatorAlbumsModel $albumModel, bool $blnCleanDb = false): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();
        $session->set('gc_error', []);

        // Create the upload directory if it doesn't exist.
        new Folder($this->galleryCreatorUploadPath);

        // Check for valid pid
        if ((int) $albumModel->pid > 0) {
            $objParentAlb = $albumModel->getRelated('pid');

            if (null === $objParentAlb) {
                $albumModel->pid = null;
                $albumModel->save();
            }
        }

        // Try to identify entries with no uuid via path
        $picturesModel = GalleryCreatorPicturesModel::findByPid($albumModel->id);

        if (null !== $picturesModel) {
            while ($picturesModel->next()) {
                // Get parent album
                $filesModel = FilesModel::findByUuid($picturesModel->uuid);

                if (null === $filesModel) {
                    $arrError = $session->get('gc_error');

                    if (false !== $blnCleanDb) {
                        $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        // Show error-message
                        $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $picturesModel->id, $albumModel->alias);
                    }

                    $session->set('gc_error', $arrError);
                } elseif (!is_file($this->projectDir.'/'.$filesModel->path)) {
                    $arrError = $session->get('gc_error');

                    // If there is a data record for the file, but the file doesn't exist in the fs anymore.
                    if (false !== $blnCleanDb) {
                        $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $picturesModel->id, $albumModel->alias);
                    }

                    $session->set('gc_error', $arrError);
                }
            }
        }

        /**
         * Ensures that there are no orphaned AlbumId's in the gcAlbumSelection field in tl_content.
         * Checks whether the albums defined in the content element still exist.
         * If not, these are removed from the array.
         */
        $stmtContent = $this->connection->executeQuery('SELECT * FROM tl_content WHERE type = ?', ['gallery_creator']);

        while (false !== ($arrContent = $stmtContent->fetchAssociative())) {
            $newArr = [];
            $arrAlbums = StringUtil::deserialize($arrContent['gcAlbumSelection'], true);

            foreach ($arrAlbums as $AlbumId) {
                if (0 === (int) $AlbumId) {
                    // "0" means: "show them all"
                    continue;
                }

                $id = $this->connection->fetchOne('SELECT id FROM tl_gallery_creator_albums WHERE id = ?', [$AlbumId]);

                if (false !== $id) {
                    $newArr[] = $id;
                }
            }

            $this->connection->update(
                'tl_content',
                ['tl_content.gcAlbumSelection' => serialize($newArr)],
                ['tl_content.id' => $arrContent['id']],
            );
        }
    }
}
