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

namespace Markocupic\GalleryCreatorBundle\Revise;

use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class ReviseAlbumDatabase
{
    private RequestStack $requestStack;
    private Connection $connection;
    private string $projectDir;
    private string $galleryCreatorUploadPath;

    public function __construct(RequestStack $requestStack, Connection $connection, string $projectDir, string $galleryCreatorUploadPath)
    {
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->projectDir = $projectDir;
        $this->galleryCreatorUploadPath = $galleryCreatorUploadPath;
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

        // Check for a valid album owner
        $objUser = UserModel::findByPk($albumModel->owner);

        if (null !== $objUser) {
            $owner = $objUser->name;
        } else {
            $owner = 'no-name';
        }
        $albumModel->ownersName = $owner;

        // Check for valid pid
        if ((int) $albumModel->pid > 0) {
            $objParentAlb = $albumModel->getRelated('pid');

            if (null === $objParentAlb) {
                $albumModel->pid = null;
            }
        }

        $albumModel->save();

        // Try to identify entries with no uuid via path
        $picturesModel = GalleryCreatorPicturesModel::findByPid($albumModel->id);

        if (null !== $picturesModel) {
            while ($picturesModel->next()) {
                // Get parent album
                $filesModel = FilesModel::findByUuid($picturesModel->uuid);

                if (null === $filesModel) {
                    if ('' !== $picturesModel->path) {
                        if (is_file($this->projectDir.'/'.$picturesModel->path)) {
                            $objModel = Dbafs::addResource($picturesModel->path);

                            if (null !== $objModel) {
                                $picturesModel->uuid = $objModel->uuid;
                                $picturesModel->save();

                                continue;
                            }
                        }
                    }

                    $arrError = $session->get('gc_error');

                    if (false !== $blnCleanDb) {
                        $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        // Show error-message
                        $path = '' !== $picturesModel->path ? $picturesModel->path : 'unknown path';
                        $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $picturesModel->id, $path, $albumModel->alias);
                    }

                    $session->set('gc_error', $arrError);
                } elseif (!is_file($this->projectDir.'/'.$filesModel->path)) {
                    $arrError = $session->get('gc_error');

                    // If there is a data record for the file, but the file doesn't exist in the fs anymore.
                    if (false !== $blnCleanDb) {
                        $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $picturesModel->id, $albumModel->name);
                        $picturesModel->delete();
                    } else {
                        $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $picturesModel->id, $filesModel->path, $albumModel->alias);
                    }

                    $session->set('gc_error', $arrError);
                } else {
                    // Sync tl_gallery_creator_pictures.path with tl_files.path (redundancy)
                    if ($picturesModel->path !== $filesModel->path) {
                        $picturesModel->path = $filesModel->path;
                        $picturesModel->save();
                    }
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
