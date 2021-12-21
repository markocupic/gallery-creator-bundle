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

namespace Markocupic\GalleryCreatorBundle\Revise;

use Contao\Database;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\Folder;
use Contao\System;
use Contao\UserModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class ReviseAlbumDatabase
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(RequestStack $requestStack, string $projectDir)
    {
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws \Exception
     */
    public function run(GalleryCreatorAlbumsModel $albumModel, bool $blnCleanDb = false): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();
        $session->set('gc_error', []);

        // Create the upload directory
        new Folder(System::getContainer()->getParameter('markocupic_gallery_creator.upload_path'));

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

        if (Database::getInstance()->fieldExists('path', 'tl_gallery_creator_pictures')) {
            // Try to identify entries with no uuid via path
            $pictureModels = GalleryCreatorPicturesModel::findByPid($albumModel->id);

            if (null !== $pictureModels) {
                while ($pictureModels->next()) {
                    // Get parent album
                    $objFile = FilesModel::findByUuid($pictureModels->uuid);

                    if (null === $objFile) {
                        if ('' !== $pictureModels->path) {
                            if (is_file($this->projectDir.'/'.$pictureModels->path)) {
                                $objModel = Dbafs::addResource($pictureModels->path);

                                if (null !== $objModel) {
                                    $pictureModels->uuid = $objModel->uuid;
                                    $pictureModels->save();
                                    continue;
                                }
                            }
                        }

                        $arrError = $session->get('gc_error');

                        if (false !== $blnCleanDb) {
                            $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $pictureModels->id, $albumModel->name);
                            $pictureModels->delete();
                        } else {
                            // Show error-message
                            $path = '' !== $pictureModels->path ? $pictureModels->path : 'unknown path';
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $pictureModels->id, $path, $albumModel->alias);
                        }

                        $session->set('gc_error', $arrError);
                    } elseif (!is_file($this->projectDir.'/'.$objFile->path)) {
                        $arrError = $session->get('gc_error');

                        // If file has an entry in Dbafs, but doesn't exist on the server anymore
                        if (false !== $blnCleanDb) {
                            $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $pictureModels->id, $albumModel->name);
                            $pictureModels->delete();
                        } else {
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'], $pictureModels->id, $objFile->path, $albumModel->alias);
                        }

                        $session->set('gc_error', $arrError);
                    } else {
                        // Sync tl_gallery_creator_pictures.path with tl_files.path (redundancy)
                        if ($pictureModels->path !== $objFile->path) {
                            $pictureModels->path = $objFile->path;
                            $pictureModels->save();
                        }
                    }
                }
            }
        }

        /**
         * Ensures that there are no orphaned AlbumId's in the gcPublishAlbums field in tl_content.
         * Checks whether the albums defined in the content element still exist.
         * If not, these are removed from the array.
         */
        $objCont = Database::getInstance()
            ->prepare('SELECT * FROM tl_content WHERE type = ?')
            ->execute('gallery_creator')
            ;

        while ($objCont->next()) {
            $newArr = [];
            $arrAlbums = unserialize($objCont->gcPublishAlbums);

            if (\is_array($arrAlbums)) {
                foreach ($arrAlbums as $AlbumID) {
                    $objAlb = Database::getInstance()
                        ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id = ?')
                        ->limit('1')
                        ->execute($AlbumID)
                        ;

                    if ($objAlb->next()) {
                        $newArr[] = $AlbumID;
                    }
                }
            }
            Database::getInstance()
                ->prepare('UPDATE tl_content SET gcPublishAlbums = ? WHERE id = ?')
                ->execute(serialize($newArr), $objCont->id)
                ;
        }
    }
}
