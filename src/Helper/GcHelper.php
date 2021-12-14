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
use Contao\BackendUser;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\File\Metadata;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Folder;
use Contao\Frontend;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\Picture;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Patchwork\Utf8;

class GcHelper
{
    /**
     * @throws \Exception
     */
    public static function createNewImage(GalleryCreatorAlbumsModel $albumModel, string $strFilepath): bool
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        //get the file-object
        $objFile = new File($strFilepath);

        if (!$objFile->isGdImage) {
            return false;
        }

        // Get files model
        $objFilesModel = $objFile->getModel();

        if (null === $objFilesModel) {
            throw new \Exception('Aborted Script, because there is no file model.');
        }

        // Get the assigned album directory
        $objFolder = FilesModel::findByUuid($albumModel->assignedDir);
        $assignedDir = null;

        if (null !== $objFolder) {
            if (is_dir($projectDir.'/'.$objFolder->path)) {
                $assignedDir = $objFolder->path;
            }
        }

        if (null === $assignedDir) {
            throw new \Exception('Aborted Script, because there is no upload directory assigned to the Album with ID '.$albumModel->id);
        }

        // Check if the file ist stored in the album-directory or if it is stored in an external directory
        $blnExternalFile = false;

        if (Input::get('importFromFilesystem')) {
            $blnExternalFile = strstr($objFile->dirname, $assignedDir) ? false : true;
        }

        // Db insert
        $pictureModelModel = new GalleryCreatorPicturesModel();
        $pictureModelModel->tstamp = time();
        $pictureModelModel->pid = $albumModel->id;
        $pictureModelModel->externalFile = $blnExternalFile ? '1' : '';
        // Set uuid before model is saved the first time!!!
        $pictureModelModel->uuid = $objFilesModel->uuid;
        $pictureModelModel->save();
        $insertId = $pictureModelModel->id;

        // Get the next sorting index
        $objImg = Database::getInstance()
            ->prepare('SELECT MAX(sorting)+10 AS maximum FROM tl_gallery_creator_pictures WHERE pid=?')
            ->execute($albumModel->id)
        ;
        $sorting = $objImg->maximum;

        // If filename should be generated
        if (!$albumModel->preserveFilename && false === $blnExternalFile) {
            $newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $albumModel->id, $insertId, $objFile->extension);
            $objFile->renameTo($newFilepath);
        }

        if (is_file($projectDir.'/'.$objFile->path)) {
            // Get the userId
            $userId = '0';

            if (TL_MODE === 'BE') {
                $userId = BackendUser::getInstance()->id;
            }

            // The album-owner is automatically the image owner, if the image was uploaded by a frontend user
            if (TL_MODE === 'FE') {
                $userId = $albumModel->owner;
            }

            // Finally save the new image in tl_gallery_creator_pictures
            $pictureModelModel->owner = $userId;
            $pictureModelModel->date = $albumModel->date;
            $pictureModelModel->sorting = $sorting;
            $pictureModelModel->save();
            System::log('A new version of tl_gallery_creator_pictures ID '.$insertId.' has been created', __METHOD__, TL_GENERAL);

            // Check for a valid preview-thumb for the album
            if (!$albumModel->thumb) {
                $albumModel->thumb = $insertId;
                $albumModel->save();
            }

            // GalleryCreatorImagePostInsert - HOOK
            if (isset($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert'])) {
                foreach ($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert'] as $callback) {
                    $objClass = self::importStatic($callback[0]);
                    $objClass->$callback[1]($insertId);
                }
            }

            return true;
        }

        if (true === $blnExternalFile) {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file'], $strFilepath));
        } else {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['uploadError'], $strFilepath));
        }
        System::log('Unable to create the new image in: '.$strFilepath.'!', __METHOD__, TL_ERROR);

        return false;
    }

    /**
     * Move uploaded file to the album directory.
     *
     * @throws \Exception
     */
    public static function fileupload(GalleryCreatorAlbumsModel $albumModel, string $strName = 'file'): array
    {
        $blnIsError = false;

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Check for a valid upload directory
        $objUploadDir = FilesModel::findByUuid($albumModel->assignedDir);

        if (null === $objUploadDir || !is_dir($projectDir.'/'.$objUploadDir->path)) {
            $blnIsError = true;
            Message::addError('No upload directory defined in the album settings!');
        }

        // Check if there are some files in $_FILES
        if (!\is_array($_FILES[$strName])) {
            $blnIsError = true;
            Message::addError('No Files selected for the uploader.');
        }

        if ($blnIsError) {
            return [];
        }

        // Do not overwrite files
        $intCount = \count($_FILES[$strName]['name']);

        for ($i = 0; $i < $intCount; ++$i) {
            if (!empty($_FILES[$strName]['name'][$i])) {
                // Generate unique filename
                $_FILES[$strName]['name'][$i] = basename(self::generateUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name'][$i]));
            }
        }

        // Resize image if feature is enabled
        if (Input::post('imageResolution') > 1) {
            Config::set('imageWidth', Input::post('imageResolution'));
            Config::set('imageHeight', 999999999);
            Config::set('jpgQuality', Input::post('imageQuality'));
        } else {
            Config::set('maxImageWidth', 999999999);
        }

        // Call the Contao FileUpload class
        $objUpload = new FileUpload();
        $objUpload->setName($strName);
        $arrUpload = $objUpload->uploadTo($objUploadDir->path);

        foreach ($arrUpload as $strFileSrc) {
            // Store file in tl_files
            Dbafs::addResource($strFileSrc);
        }

        return $arrUpload;
    }

    /**
     * Generate a unique filepath
     * for a new picture.
     *
     * @throws \Exception
     *
     * @return false|string
     */
    public static function generateUniqueFilename(string $strFilename): string
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $strFilename = strip_tags($strFilename);
        $strFilename = Utf8::toAscii($strFilename);
        $strFilename = str_replace('"', '', $strFilename);
        $strFilename = str_replace(' ', '_', $strFilename);

        if (preg_match('/\.$/', $strFilename)) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['invalidName']);
        }
        $pathinfo = pathinfo($strFilename);
        $extension = $pathinfo['extension'];
        $basename = basename($strFilename, '.'.$extension);
        $dirname = \dirname($strFilename);

        // If file already exists, append an integer with leading zeros to it -> filename0001.jpg
        for ($i = 0; $i < 1000; ++$i) {
            if (!file_exists($projectDir.'/'.$dirname.'/'.$basename.'.'.$extension)) {
                // Exit loop when filename is unique
                return $dirname.'/'.$basename.'.'.$extension;
            }

            if (1 !== $i) {
                $filename = substr($basename, 0, -5);
            } else {
                $filename = $basename;
            }

            // Add an integer with a leading zero to the filename -> filename0001.jpg
            $suffix = str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $basename = $filename.'_'.$suffix;
        }

        // Generate random path
        return $dirname.'/'.md5($basename.microtime()).'.'.$extension;
    }

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
     * Returns the album information array.
     */
    public static function getAlbumInformationArray(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        global $objPage;

        // Anzahl Subalben ermitteln
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE published=? AND pid=?')
            ->execute('1', $albumModel->id)
        ;

        $objPics = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND published=?')
            ->execute($albumModel->id, '1')
        ;

        $arrSize = unserialize($contentElementModel->gcSizeAlbumListing);

        $href = null;

        if (TL_MODE === 'FE') {
            // Generate the url as a formatted string
            $href = StringUtil::ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') ? '/%s' : '/items/%s'), $objPage->language));

            // Add albumAlias
            $href = sprintf($href, $albumModel->alias);
        }

        $albumModelPic = static::getAlbumPreviewThumb($albumModel);

        if (null !== $albumModelPic) {
            // Generate the thumbnails and the picture element
            try {
                $thumbSrc = Image::create($albumModelPic->path, $arrSize)->executeResize()->getResizedPath();
                $picture = Picture::create($albumModelPic->path, $arrSize)->getTemplateData();
                $picture['alt'] = StringUtil::specialchars($albumModel->name);
                $picture['title'] = StringUtil::specialchars($albumModel->name);

                if ($thumbSrc !== $albumModelPic->path) {
                    new File($thumbSrc);
                }
            } catch (\Exception $e) {
                System::log('Image "'.$albumModelPic->path.'" could not be processed: '.$e->getMessage(), __METHOD__, TL_ERROR);
            }
        }

        // CSS class
        $arrCssClasses = [];
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums($albumModel->id) ? 'has-child-album' : '';
        $arrCssClasses[] = !$objPics->numRows ? 'empty-album' : '';

        $arrAlbum = [
            // [string] event date formatted
            'eventDate' => Date::parse(Config::get('dateFormat'), $albumModel->date),
            // [string] Event-Location
            'eventLocation' => StringUtil::specialchars($albumModel->eventLocation),
            // [string] albumname
            'name' => StringUtil::specialchars($albumModel->name),
            // [string] album caption
            'caption' => StringUtil::toHtml5(nl2br((string) $albumModel->caption)),
            // [string] Link zur Detailansicht
            'href' => $href,
            // [string] Inhalt fuer das title Attribut
            'title' => $albumModel->name.' ['.($objPics->numRows ? $objPics->numRows.' '.$GLOBALS['TL_LANG']['gallery_creator']['pictures'] : '').($contentElementModel->gcHierarchicalOutput && $objSubAlbums->numRows > 0 ? ' '.$GLOBALS['TL_LANG']['gallery_creator']['contains'].' '.$objSubAlbums->numRows.'  '.$GLOBALS['TL_LANG']['gallery_creator']['subalbums'].']' : ']'),
            // [int] Anzahl Bilder im Album
            'count' => (int) $objPics->numRows,
            // [int] Anzahl Unteralben
            'countSubalbums' => \count(GalleryCreatorAlbumsModel::getChildAlbums($albumModel->id)),
            // [string] alt Attribut fuer das Vorschaubild
            'alt' => StringUtil::specialchars($albumModel->name),
            // [string] Pfad zum Originalbild
            'src' => $albumModelPic ? TL_FILES_URL.$albumModelPic->path : null,
            // [string] Pfad zum Thumbnail
            'thumbSrc' => $albumModelPic ? TL_FILES_URL.Image::get($albumModelPic->path, $arrSize[0], $arrSize[1], $arrSize[2]) : null,
            // [string] css-Classname
            'class' => 'thumb',
            // [array] thumbnail size
            'size' => $arrSize,
            // [array] picture
            'picture' => $picture,
            // [string] cssClass
            'cssClass' => implode(' ', array_filter($arrCssClasses)),
        ];

        $arrAlbum = array_merge($albumModel->row(), $arrAlbum);

        return $arrAlbum;
    }

    /**
     * Returns the picture information array.
     *
     * @throws \Exception
     */
    public static function getPictureInformationArray(GalleryCreatorPicturesModel $pictureModel, ContentModel $contentElementModel): ?array
    {
        global $objPage;

        if (null === ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return null;
        }

        if (!is_file($filesModel->getAbsolutePath())) {
            return null;
        }

        $objFile = new File($filesModel->path);

        // Meta
        $arrMeta = Frontend::getMetaData($filesModel->meta, $objPage->language);

        if (empty($arrMeta['title'])) {
            $arrMeta['title'] = $filesModel->name;
        }

        $arrMeta['title'] = $pictureModel->caption ?? $arrMeta['title'];

        // Get thumb dimensions
        $arrSize = StringUtil::deserialize($contentElementModel->gcSizeDetailView);

        // Video-integration
        $strMediaSrc = !empty(trim((string) $pictureModel->socialMediaSRC)) ? trim((string) $pictureModel->socialMediaSRC) : null;

        // Local media
        if (null !== ($objMovieFile = FilesModel::findByUuid($pictureModel->localMediaSRC))) {
            $strMediaSrc =  $objMovieFile->path;
        }

        $href = null;

        if (TL_MODE === 'FE' && $contentElementModel->gcFullsize) {
            $href = $strMediaSrc ?? $filesModel->path;
            $href = TL_FILES_URL.$href;
        }

        // CssID
        $cssID = StringUtil::deserialize($pictureModel->cssID, true);

        // Build the array
        return [
            'ownerModel' => UserModel::findByPk($pictureModel->owner),
            'filesModel' => $filesModel,
            'pictureModel' => $pictureModel,
            'albumModel' => $pictureModel->getRelated('pid'),
            'meta' => $arrMeta,
            'href' => $href,
            'singleImageUrl' => StringUtil::ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items').'/img/'.$filesModel->name, $objPage->language)),
            'mediaSrc' => $strMediaSrc ?? null,
            'size' => !empty($arrSize) ? $arrSize : null,
            'exif' => static::getExif($objFile),
            'cssID' => '' !== $cssID[0] ? $cssID[0] : '',
            'cssClass' => '' !== $cssID[1] ? $cssID[1] : '',
            'figureOptions' => [
                'metadata' => new Metadata($arrMeta),
                'enableLightbox' => (bool) $contentElementModel->gcFullsize,
                'lightboxGroupIdentifier' => sprintf('data-lightbox="lb%s"', $pictureModel->pid),
                //'lightboxSize' => '_big_size',
                'linkHref' => $href,
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function getExif(File $file): array
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Exif
        if (Config::get('gc_read_exif')) {
            try {
                $exif = \is_callable('exif_read_data') && TL_MODE === 'FE' ? exif_read_data($projectDir.'/'.$file->path) : ['info' => "The function 'exif_read_data()' is not available on this server."];
            } catch (\Exception $e) {
                $exif = ['info' => "The function 'exif_read_data()' is not available on this server."];
            }
        } else {
            $exif = ['info' => "The function 'exif_read_data()' has not been activated in the Contao backend settings."];
        }

        return $exif;
    }

    /**
     * Returns the information-array about all subalbums of a certain parent album.
     */
    public static function getSubalbumsInformationArray(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentElementModel): array
    {
        $strSorting = $contentElementModel->gcSorting.' '.$contentElementModel->gcSortingDirection;
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($albumModel->id, '1')
        ;
        $arrSubalbums = [];

        while ($objSubAlbums->next()) {
            // If it is a content element only
            if ('' !== $contentElementModel->gcPublishAlbums) {
                if (!$contentElementModel->gcPublishAllAlbums) {
                    if (!\in_array($objSubAlbums->id, StringUtil::deserialize($contentElementModel->gcPublishAlbums), false)) {
                        continue;
                    }
                }
            }
            $objSubAlbum = GalleryCreatorAlbumsModel::findByPk($objSubAlbums->id);

            if (null !== $objSubAlbum) {
                $arrSubalbum = self::getAlbumInformationArray($objSubAlbum, $contentElementModel);
                array_push($arrSubalbums, $arrSubalbum);
            }
        }

        return $arrSubalbums;
    }

    /**
     * Returns the album preview thumbnail.
     */
    public static function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $albumModel): ?FilesModel
    {
        if (null === ($pictureModel = GalleryCreatorPicturesModel::findByPk($albumModel->thumb))) {
            $pictureModel = GalleryCreatorPicturesModel::findOneByPid($albumModel->id);
        }

        if (null !== $pictureModel && null !== ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return $filesModel;
        }

        return null;
    }

    public static function initCounter(GalleryCreatorAlbumsModel $albumModel): void
    {
        $crawlerDetect = new CrawlerDetect();

        // Check the user agent of the current 'visitor'
        if (TL_MODE !== 'FE' || $crawlerDetect->isCrawler()) {
            return;
        }

        $arrVisitors = StringUtil::deserialize($albumModel->visitorsDetails, true);

        if (\in_array(md5((string) $_SERVER['REMOTE_ADDR']), $arrVisitors, true)) {
            // Return if the visitor is already registered
            return;
        }

        // Keep visitors data in the db unless 50 other users have visited the album
        if (50 === \count($arrVisitors)) {
            // slice the last position
            $arrVisitors = \array_slice($arrVisitors, 0, \count($arrVisitors) - 1);
        }

        // Build the array
        $newVisitor = md5((string) $_SERVER['REMOTE_ADDR']);

        if (!empty($arrVisitors)) {
            // Insert the element to the beginning of the array
            array_unshift($arrVisitors, $newVisitor);
        } else {
            $arrVisitors[] = $newVisitor;
        }

        // Update database
        $albumModel->visitors = ++$albumModel->visitors;
        $albumModel->visitorsDetails = serialize($arrVisitors);
        $albumModel->save();
    }

    /**
     * $imgPath - relative path to the filesource
     * angle - the rotation angle is interpreted as the number of degrees to rotate the image anticlockwise.
     * angle shall be 0,90,180,270.
     */
    public static function imageRotate(string $imgPath, int $angle): bool
    {
        if (0 === $angle) {
            return false;
        }

        if (0 !== $angle % 90) {
            return false;
        }

        if ($angle < 90 || $angle > 360) {
            return false;
        }

        if (!\function_exists('imagerotate')) {
            return false;
        }

        // Chmod
        Files::getInstance()->chmod($imgPath, 0777);

        // Load
        if (TL_MODE === 'BE') {
            $imgSrc = '../'.$imgPath;
        } else {
            $imgSrc = $imgPath;
        }
        $source = imagecreatefromjpeg($imgSrc);

        // Rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $imgSrc);
        imagedestroy($source);

        // Chmod
        Files::getInstance()->chmod($imgPath, 0644);

        return true;
    }

    /**
     * Bilder aus Verzeichnis auf dem Server in Album einlesen.
     *
     * @throws \Exception
     */
    public static function importFromFilesystem(GalleryCreatorAlbumsModel $albumModel, array $arrMultiSRC): void
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $images = [];

        if (null === ($objFilesModel = FilesModel::findMultipleByUuids($arrMultiSRC))) {
            return;
        }

        while ($objFilesModel->next()) {
            // Continue if the file has been processed or does not exist
            if (isset($images[$objFilesModel->path]) || !file_exists($projectDir.'/'.$objFilesModel->path)) {
                continue;
            }

            // If item is a file, then store it in the array
            if ('file' === $objFilesModel->type) {
                $objFile = new File($objFilesModel->path);

                if ($objFile->isGdImage) {
                    $images[$objFile->path] = ['uuid' => $objFilesModel->uuid, 'basename' => $objFile->basename, 'path' => $objFile->path];
                }
            } else {
                // If it is a directory, then store its files in the array
                $objSubfilesModel = FilesModel::findMultipleFilesByFolder($objFilesModel->path);

                if (null === $objSubfilesModel) {
                    continue;
                }

                while ($objSubfilesModel->next()) {
                    // Skip subfolders
                    if ('folder' === $objSubfilesModel->type || !is_file($projectDir.'/'.$objSubfilesModel->path)) {
                        continue;
                    }

                    $objFile = new File($objSubfilesModel->path);

                    if ($objFile->isGdImage) {
                        $images[$objFile->path] = ['uuid' => $objSubfilesModel->uuid, 'basename' => $objFile->basename, 'path' => $objFile->path];
                    }
                }
            }
        }

        if (\count($images)) {
            $arrPictures = [
                'uuid' => [],
                'path' => [],
                'basename' => [],
            ];

            $pictureModels = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=?')
                ->execute($albumModel->id)
            ;

            $arrPictures['uuid'] = $pictureModels->fetchEach('uuid');
            $arrPictures['path'] = $pictureModels->fetchEach('path');

            foreach ($arrPictures['path'] as $path) {
                $arrPictures['basename'][] = basename($path);
            }

            foreach ($images as $image) {
                // Prevent duplicate entries
                if (\in_array($image['uuid'], $arrPictures['uuid'], false)) {
                    continue;
                }

                // Prevent duplicate entries
                if (\in_array($image['basename'], $arrPictures['basename'], true)) {
                    continue;
                }

                Input::setGet('importFromFilesystem', 'true');

                if (Config::get('gc_album_import_copy_files')) {
                    $strSource = $image['path'];

                    // Get the album upload directory
                    $objFolderModel = FilesModel::findByUuid($albumModel->assignedDir);
                    $errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID '.$albumModel->id.'.';

                    if (null === $objFolderModel) {
                        throw new \Exception($errMsg);
                    }

                    if ('folder' !== $objFolderModel->type) {
                        throw new \Exception($errMsg);
                    }

                    if (!is_dir($projectDir.'/'.$objFolderModel->path)) {
                        throw new \Exception($errMsg);
                    }

                    $strDestination = self::generateUniqueFilename($objFolderModel->path.'/'.basename($strSource));

                    if (is_file($projectDir.'/'.$strSource)) {
                        // Copy Image to the upload folder
                        $objFile = new File($strSource);
                        $objFile->copyTo($strDestination);
                        Dbafs::addResource($strSource);
                    }

                    self::createNewImage($albumModel, $strDestination);
                } else {
                    self::createNewImage($albumModel, $image['path']);
                }
            }
        }
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

    /**
     * Return the level of an album or subalbum
     * (level_0, level_1, level_2,...).
     */
    public static function getAlbumLevel(int $pid): int
    {
        $level = 0;

        if (0 === (int) $pid) {
            return $level;
        }
        $hasParent = true;

        while ($hasParent) {
            ++$level;
            $albumModel = GalleryCreatorAlbumsModel::findByPk($pid);

            if ($albumModel->pid < 1) {
                $hasParent = false;
            }
            $pid = $albumModel->pid;
        }

        return $level;
    }
}
