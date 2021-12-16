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

namespace Markocupic\GalleryCreatorBundle\Helper;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Database;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Folder;
use Contao\System;
use Contao\UserModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;

class GcHelper
{
    /**
     * Generate the uploader.
     */
    public static function generateUploader(string $uploader = 'be_gc_html5_uploader'): string
    {
        // Create the template object
        $objTemplate = new BackendTemplate($uploader);

        // Maximum uploaded size
        $objTemplate->maxUploadedSize = FileUpload::getMaxUploadSize();

        // $_FILES['file']
        $objTemplate->strName = 'file';

        // Return the parsed uploader template
        return $objTemplate->parse();
    }

    /**
     * Revise tables.
     *
     * @throws \Exception
     */
    public static function reviseTables(GalleryCreatorAlbumsModel $albumModel, bool $blnCleanDb = false): void
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $session = System::getContainer()->get('session');
        $session->set('gc_error', []);

        // Upload-Verzeichnis erstellen, falls nicht mehr vorhanden
        new Folder(Config::get('galleryCreatorUploadPath'));

        // Check for valid album owner
        $objUser = UserModel::findByPk($albumModel->owner);

        if (null !== $objUser) {
            $owner = $objUser->name;
        } else {
            $owner = 'no-name';
        }
        $albumModel->ownersName = $owner;

        // Check for valid pid
        if ($albumModel->pid > 0) {
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
                            if (is_file($projectDir.'/'.$pictureModels->path)) {
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
                            // Show the error-message
                            $path = '' !== $pictureModels->path ? $pictureModels->path : 'unknown path';
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $pictureModels->id, $path, $albumModel->alias);
                        }

                        $session->set('gc_error', $arrError);
                    } elseif (!is_file($projectDir.'/'.$objFile->path)) {
                        $arrError = $session->get('gc_error');

                        // If file has an entry in Dbafs, but doesn't exist on the server anymore
                        if (false !== $blnCleanDb) {
                            $arrError[] = sprintf('Deleted data record with ID %s in Album "%s".', $pictureModels->id, $albumModel->name);
                            $pictureModels->delete();
                        } else {
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $pictureModels->id, $objFile->path, $albumModel->alias);
                        }

                        $session->set('gc_error', $arrError);
                    } else {
                        // Pfadangaben mit tl_files.path abgleichen (Redundanz)
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
            ->prepare('SELECT * FROM tl_content WHERE type=?')
            ->execute('gallery_creator')
        ;

        while ($objCont->next()) {
            $newArr = [];
            $arrAlbums = unserialize($objCont->gcPublishAlbums);

            if (\is_array($arrAlbums)) {
                foreach ($arrAlbums as $AlbumID) {
                    $objAlb = Database::getInstance()
                        ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
                        ->limit('1')
                        ->execute($AlbumID)
                    ;

                    if ($objAlb->next()) {
                        $newArr[] = $AlbumID;
                    }
                }
            }
            Database::getInstance()
                ->prepare('UPDATE tl_content SET gcPublishAlbums=? WHERE id=?')
                ->execute(serialize($newArr), $objCont->id)
            ;
        }
    }
}
