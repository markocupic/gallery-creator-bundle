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
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Folder;
use Contao\Frontend;
use Contao\GalleryCreatorAlbumsModel;
use Contao\GalleryCreatorPicturesModel;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\PageModel;
use Contao\Picture;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Patchwork\Utf8;

class GcHelper
{
    /**
     * @param $intAlbumId
     * @param $strFilepath
     *
     * @throws \Exception
     */
    public static function createNewImage($intAlbumId, string $strFilepath): bool
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

        // Get the album-object
        $objAlbumModel = GalleryCreatorAlbumsModel::findById($intAlbumId);

        if (null === $objAlbumModel) {
            throw new \Exception('Aborted Script, because there is no album model with ID '.$intAlbumId.'.');
        }

        // Get the assigned album directory
        $objFolder = FilesModel::findByUuid($objAlbumModel->assignedDir);
        $assignedDir = null;

        if (null !== $objFolder) {
            if (is_dir($projectDir.'/'.$objFolder->path)) {
                $assignedDir = $objFolder->path;
            }
        }

        if (null === $assignedDir) {
            throw new \Exception('Aborted Script, because there is no upload directory assigned to the Album with ID '.$intAlbumId);
        }

        // Check if the file ist stored in the album-directory or if it is stored in an external directory
        $blnExternalFile = false;

        if (Input::get('importFromFilesystem')) {
            $blnExternalFile = strstr($objFile->dirname, $assignedDir) ? false : true;
        }

        // Db insert
        $objPictureModel = new GalleryCreatorPicturesModel();
        $objPictureModel->tstamp = time();
        $objPictureModel->pid = $objAlbumModel->id;
        $objPictureModel->externalFile = $blnExternalFile ? '1' : '';
        // Set uuid before model is saved the first time!!!
        $objPictureModel->uuid = $objFilesModel->uuid;
        $objPictureModel->save();
        $insertId = $objPictureModel->id;

        // Get the next sorting index
        $objImg = Database::getInstance()
            ->prepare('SELECT MAX(sorting)+10 AS maximum FROM tl_gallery_creator_pictures WHERE pid=?')
            ->execute($objAlbumModel->id)
        ;
        $sorting = $objImg->maximum;

        // If filename should be generated
        if (!$objAlbumModel->preserve_filename && false === $blnExternalFile) {
            $newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $objAlbumModel->id, $insertId, $objFile->extension);
            $objFile->renameTo($newFilepath);
        }

        if (is_file($projectDir.'/'.$objFile->path)) {
            // Get the userId
            $userId = '0';

            if (TL_MODE === 'BE') {
                $userId = BackendUser::getInstance()->id;
            }

            // The album-owner is automaticaly the image owner, if the image was uploaded by a frontend user
            if (TL_MODE === 'FE') {
                $userId = $objAlbumModel->owner;
            }

            // Finally save the new image in tl_gallery_creator_pictures
            $objPictureModel->owner = $userId;
            $objPictureModel->date = $objAlbumModel->date;
            $objPictureModel->sorting = $sorting;
            $objPictureModel->save();
            System::log('A new version of tl_gallery_creator_pictures ID '.$insertId.' has been created', __METHOD__, TL_GENERAL);

            // Check for a valid preview-thumb for the album
            if (!$objAlbumModel->thumb) {
                $objAlbumModel->thumb = $insertId;
                $objAlbumModel->save();
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
            $_SESSION['TL_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file'], $strFilepath);
        } else {
            $_SESSION['TL_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['uploadError'], $strFilepath);
        }
        System::log('Unable to create the new image in: '.$strFilepath.'!', __METHOD__, TL_ERROR);

        return false;
    }

    /**
     * Move uploaded file to the album directory.
     *
     * @param $intAlbumId
     */
    public static function fileupload($intAlbumId, string $strName = 'file'): array
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $blnIsError = false;

        // Get the album object
        $objAlb = GalleryCreatorAlbumsModel::findById($intAlbumId);

        if (null === $objAlb) {
            $blnIsError = true;
            Message::addError('Album with ID '.$intAlbumId.' does not exist.');
        }

        // Check for a valid upload directory
        $objUploadDir = FilesModel::findByUuid($objAlb->assignedDir);

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

        // Adapt $_FILES if files are loaded up by jumploader (java applet)
        if (!\is_array($_FILES[$strName]['name'])) {
            $arrFile = [
                'name' => $_FILES[$strName]['name'],
                'type' => $_FILES[$strName]['type'],
                'tmp_name' => $_FILES[$strName]['tmp_name'],
                'error' => $_FILES[$strName]['error'],
                'size' => $_FILES[$strName]['size'],
            ];

            unset($_FILES);

            // Rebuild $_FILES for the Contao FileUpload class
            $_FILES[$strName]['name'][0] = $arrFile['name'];
            $_FILES[$strName]['type'][0] = $arrFile['type'];
            $_FILES[$strName]['tmp_name'][0] = $arrFile['tmp_name'];
            $_FILES[$strName]['error'][0] = $arrFile['error'];
            $_FILES[$strName]['size'][0] = $arrFile['size'];
        }

        // Do not overwrite files of the same filename
        $intCount = \count($_FILES[$strName]['name']);

        for ($i = 0; $i < $intCount; ++$i) {
            if (!empty($_FILES[$strName]['name'][$i])) {
                // Generate unique filename
                $_FILES[$strName]['name'][$i] = basename(self::generateUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name'][$i]));
            }
        }

        // Resize image if feature is enabled
        if (Input::post('img_resolution') > 1) {
            Config::set('imageWidth', Input::post('img_resolution'));
            Config::set('imageHeight', 999999999);
            Config::set('jpgQuality', Input::post('img_quality'));
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
     * Generate a unique filepath for a new picture.
     *
     * @param $strFilename
     *
     * @throws \Exception
     *
     * @return bool|string
     */
    public static function generateUniqueFilename(string $strFilename)
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

        // Falls Datei schon existiert, wird hinten eine Zahl mit fuehrenden Nullen angehaengt -> filename0001.jpg
        $i = 0;
        $isUnique = false;

        do {
            ++$i;

            if (!file_exists($projectDir.'/'.$dirname.'/'.$basename.'.'.$extension)) {
                // Exit loop when filename is unique
                return $dirname.'/'.$basename.'.'.$extension;
            }

            if (1 !== $i) {
                $filename = substr($basename, 0, -5);
            } else {
                $filename = $basename;
            }
            $suffix = str_pad($i, 4, '0', STR_PAD_LEFT);
            // Integer mit fuehrenden Nullen an den Dateinamen anhaengen ->filename0001.jpg
            $basename = $filename.'_'.$suffix;

            // Break after 100 loops and generate random filename
            if (100 === $i) {
                return $dirname.'/'.md5($basename.microtime()).'.'.$extension;
            }
        } while (false === $isUnique);

        return false;
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

        // Parse the jumloader view and return it
        return $objTemplate->parse();
    }

    /**
     * Returns the album information array.
     *
     * @param $intAlbumId
     * @param $objContentModel
     */
    public static function getAlbumInformationArray($intAlbumId, ContentModel $objContentModel): array
    {
        global $objPage;

        // Get the page model
        $objPageModel = PageModel::findByPk($objPage->id);

        $objAlbum = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
            ->execute($intAlbumId)
        ;

        // Anzahl Subalben ermitteln
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE published=? AND pid=?')
            ->execute('1', $intAlbumId)
        ;

        $objPics = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND published=?')
            ->execute($objAlbum->id, '1')
        ;

        // Array Thumbnailbreite
        $arrSize = unserialize($objContentModel->gc_size_albumlisting);

        $href = null;

        if (TL_MODE === 'FE') {
            // Generate the url as a formated string
            $href = ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/%s' : '/items/%s'), $objPage->language));
            // Add albumAlias
            $href = sprintf($href, $objAlbum->alias);
        }

        $arrPreviewThumb = static::getAlbumPreviewThumb($objAlbum->id);
        $strImageSrc = $arrPreviewThumb['path'];

        // Generate the thumbnails and the picture element
        try {
            $thumbSrc = Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
            $picture = Picture::create($strImageSrc, $arrSize)->getTemplateData();

            if ($thumbSrc !== $strImageSrc) {
                new File(rawurldecode($thumbSrc), true);
            }
        } catch (\Exception $e) {
            System::log('Image "'.$strImageSrc.'" could not be processed: '.$e->getMessage(), __METHOD__, TL_ERROR);
        }

        $picture['alt'] = StringUtil::specialchars($objAlbum->name);
        $picture['title'] = StringUtil::specialchars($objAlbum->name);

        // CSS class
        $strCSSClass = GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) ? 'has-child-album' : '';
        $strCSSClass .= $objPics->numRows < 1 ? ' empty-album' : '';

        $arrAlbum = [
            'id' => $objAlbum->id,
            //[int] pid parent Album-Id
            'pid' => $objAlbum->pid,
            //[int] Sortierindex
            'sorting' => $objAlbum->sorting,
            //[boolean] veroeffentlicht (true/false)
            'published' => $objAlbum->published,
            //[int] id des Albumbesitzers
            'owner' => $objAlbum->owner,
            //[string] Benutzername des Albumbesitzers
            'owners_name' => $objAlbum->owners_name,
            //[string] Photographers names
            'photographer' => $objAlbum->photographer,
            //[int] Zeitstempel der letzten Aenderung
            'tstamp' => $objAlbum->tstamp,
            //[int] Event-Unix-timestamp (unformatiert)
            'event_tstamp' => $objAlbum->date,
            'date' => $objAlbum->date,
            //[string] Event-Datum (formatiert)
            'event_date' => Date::parse(Config::get('dateFormat'), $objAlbum->date),
            //[string] Event-Location
            'event_location' => StringUtil::specialchars($objAlbum->event_location),
            //[string] Albumname
            'name' => StringUtil::specialchars($objAlbum->name),
            //[string] Albumalias (=Verzeichnisname)
            'alias' => $objAlbum->alias,
            //[string] Albumkommentar
            'comment' => StringUtil::toHtml5(nl2br((string) $objAlbum->comment)),
            'caption' => StringUtil::toHtml5(nl2br((string) $objAlbum->comment)),
            'caption' => StringUtil::toHtml5(nl2br((string) $objAlbum->comment)),
            //[int] Albumbesucher (Anzahl Klicks)
            'visitors' => $objAlbum->visitors,
            //[string] Link zur Detailansicht
            'href' => $href,
            //[string] Inhalt fuer das title Attribut
            'title' => $objAlbum->name.' ['.($objPics->numRows ? $objPics->numRows.' '.$GLOBALS['TL_LANG']['gallery_creator']['pictures'] : '').($objContentModel->gc_hierarchicalOutput && $objSubAlbums->numRows > 0 ? ' '.$GLOBALS['TL_LANG']['gallery_creator']['contains'].' '.$objSubAlbums->numRows.'  '.$GLOBALS['TL_LANG']['gallery_creator']['subalbums'].']' : ']'),
            //[int] Anzahl Bilder im Album
            'count' => $objPics->numRows,
            //[int] Anzahl Unteralben
            'count_subalbums' => \count(GalleryCreatorAlbumsModel::getChildAlbums($objAlbum->id)),
            //[string] alt Attribut fuer das Vorschaubild
            'alt' => $arrPreviewThumb['name'],
            //[string] Pfad zum Originalbild
            'src' => TL_FILES_URL.$arrPreviewThumb['path'],
            //[string] Pfad zum Thumbnail
            'thumb_src' => TL_FILES_URL.Image::get($arrPreviewThumb['path'], $arrSize[0], $arrSize[1], $arrSize[2]),
            //[int] article id
            'insert_article_pre' => $objAlbum->insert_article_pre ?: null,
            //[int] article id
            'insert_article_post' => $objAlbum->insert_article_post ?: null,
            //[string] css-Classname
            'class' => 'thumb',
            //[int] Thumbnailgrösse
            'size' => $arrSize,
            //[string] javascript-Aufruf
            'thumbMouseover' => $objContentModel->gc_activateThumbSlider ? 'objGalleryCreator.initThumbSlide(this,'.$objAlbum->id.','.$objPics->numRows.');' : '',
            //[array] picture
            'picture' => $picture,
            //[string] cssClass
            'cssClass' => trim($strCSSClass),
        ];

        // Fuegt dem Array weitere Eintraege hinzu, falls tl_gallery_creator_albums erweitert wurde
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null !== $objAlbum) {
            $arrAlbum = array_merge($objAlbum->row(), $arrAlbum);
        }

        return $arrAlbum;
    }

    /**
     * Returns the picture information array.
     *
     * @param null $intPictureId
     * @param $objContentModel
     */
    public static function getPictureInformationArray($intPictureId, ContentModel $objContentModel): ?array
    {
        if ($intPictureId < 1) {
            return null;
        }

        global $objPage;

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $hasCustomThumb = false;

        $defaultThumbSRC = $objContentModel->defaultThumb;

        if (!empty(Config::get('gc_albumFallbackThumb'))) {
            $objFile = FilesModel::findByUuid(Config::get('gc_albumFallbackThumb'));

            if (null !== $objFile) {
                if (Validator::isStringUuid(Config::get('gc_albumFallbackThumb'))) {
                    if (is_file($projectDir.'/'.$objFile->path)) {
                        $defaultThumbSRC = $objFile->path;
                    }
                }
            }
        }

        // Get the page model
        $objPageModel = PageModel::findByPk($objPage->id);

        $objPicture = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE id=?')
            ->execute($intPictureId)
        ;

        // Alle Informationen zum Album in ein array packen
        $objAlbum = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
            ->execute($objPicture->pid)
        ;
        $arrAlbumInfo = $objAlbum->fetchAssoc();

        // Bild-Besitzer
        $objOwner = Database::getInstance()
            ->prepare('SELECT * FROM tl_user WHERE id=?')
            ->execute($objPicture->owner)
        ;
        $arrMeta = [];
        $objFileModel = FilesModel::findByUuid($objPicture->uuid);

        if (null === $objFileModel) {
            $strImageSrc = $defaultThumbSRC;
        } else {
            $strImageSrc = $objFileModel->path;

            if (!is_file($projectDir.'/'.$strImageSrc)) {
                // Fallback to the default thumb
                $strImageSrc = $defaultThumbSRC;
            }

            // Meta
            $arrMeta = Frontend::getMetaData($objFileModel->meta, $objPage->language);
            // Use the file name as title if none is given
            if (empty($arrMeta['title'])) {
                $arrMeta['title'] = StringUtil::specialchars($objFileModel->name);
            }
        }

        // Get thumb dimensions
        $arrSize = unserialize($objContentModel->gc_size_detailview);

        // Generate the thumbnails and the picture element
        try {
            $thumbSrc = Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
            // Overwrite $thumbSrc if there is a valid custom thumb
            if ($objPicture->addCustomThumb && !empty($objPicture->customThumb)) {
                $customThumbModel = FilesModel::findByUuid($objPicture->customThumb);

                if (null !== $customThumbModel) {
                    if (is_file($projectDir.'/'.$customThumbModel->path)) {
                        $objFileCustomThumb = new File($customThumbModel->path, true);

                        if ($objFileCustomThumb->isGdImage) {
                            $thumbSrc = Image::create($objFileCustomThumb->path, $arrSize)->executeResize()->getResizedPath();
                            $hasCustomThumb = true;
                        }
                    }
                }
            }
            $thumbPath = $hasCustomThumb ? $objFileCustomThumb->path : $strImageSrc;
            $picture = Picture::create($thumbPath, $arrSize)->getTemplateData();
        } catch (\Exception $e) {
            System::log('Image "'.$strImageSrc.'" could not be processed: '.$e->getMessage(), __METHOD__, TL_ERROR);
            $thumbSrc = '';
            $picture = ['img' => ['src' => '', 'srcset' => ''], 'sources' => []];
        }

        $picture['alt'] = '' !== $objPicture->title ? StringUtil::specialchars($objPicture->title) : StringUtil::specialchars($arrMeta['title']);
        $picture['title'] = '' !== $objPicture->comment ? StringUtil::specialchars(StringUtil::toHtml5($objPicture->comment)) : StringUtil::specialchars($arrMeta['caption']);

        $objFileThumb = new File(rawurldecode($thumbSrc));
        $arrSize[0] = $objFileThumb->width;
        $arrSize[1] = $objFileThumb->height;
        $arrFile['thumb_width'] = $objFileThumb->width;
        $arrFile['thumb_height'] = $objFileThumb->height;

        // Get some image params
        if (is_file($projectDir.'/'.$strImageSrc)) {
            $objFileImage = new File($strImageSrc);

            if (!$objFileImage->isGdImage) {
                return null;
            }
            $arrFile['path'] = $objFileImage->path;
            $arrFile['basename'] = $objFileImage->basename;
            // Filename without extension
            $arrFile['filename'] = $objFileImage->filename;
            $arrFile['extension'] = $objFileImage->extension;
            $arrFile['dirname'] = $objFileImage->dirname;
            $arrFile['image_width'] = $objFileImage->width;
            $arrFile['image_height'] = $objFileImage->height;
        } else {
            return null;
        }

        // Exif
        if (Config::get('gc_read_exif')) {
            try {
                $exif = \is_callable('exif_read_data') && TL_MODE === 'FE' ? exif_read_data($strImageSrc) : ['info' => "The function 'exif_read_data()' is not available on this server."];
            } catch (\Exception $e) {
                $exif = ['info' => "The function 'exif_read_data()' is not available on this server."];
            }
        } else {
            $exif = ['info' => "The function 'exif_read_data()' has not been activated in the Contao backend settings."];
        }

        // Video-integration
        $strMediaSrc = !empty(trim((string) $objPicture->socialMediaSRC)) ? trim((string) $objPicture->socialMediaSRC) : '';

        if (Validator::isBinaryUuid($objPicture->localMediaSRC)) {
            // Get path of a local Media
            $objMovieFile = FilesModel::findById($objPicture->localMediaSRC);
            $strMediaSrc = null !== $objMovieFile ? $objMovieFile->path : $strMediaSrc;
        }
        $href = null;

        if (TL_MODE === 'FE' && $objContentModel->gc_fullsize) {
            $href = !empty($strMediaSrc) ? $strMediaSrc : TL_FILES_URL.System::urlEncode($strImageSrc);
        }

        // CssID
        $cssID = StringUtil::deserialize($objPicture->cssID, true);

        // Build the array
        $arrPicture = [
            'id' => $objPicture->id,
            //[int] pid parent Album-Id
            'pid' => $objPicture->pid,
            //[int] das Datum, welches fuer das Bild gesetzt werden soll (= in der Regel das Upload-Datum)
            'date' => $objPicture->date,
            //[int] id des Albumbesitzers
            'owner' => $objPicture->owner,
            //Name des Erstellers
            'owners_name' => $objOwner->name,
            //[int] album_id oder pid
            'album_id' => $objPicture->pid,
            //[string] name (basename/filename of the file)
            'name' => StringUtil::specialchars($arrFile['basename']),
            //[string] filename without extension
            'filename' => $arrFile['filename'],
            //[string] Pfad zur Datei
            'uuid' => $objPicture->uuid,
            // uuid of the image
            'path' => $arrFile['path'],
            //[string] basename similar to name
            'basename' => $arrFile['basename'],
            //[string] dirname
            'dirname' => $arrFile['dirname'],
            //[string] file-extension
            'extension' => $arrFile['extension'],
            //[string] alt-attribut
            'alt' => '' !== $objPicture->title ? StringUtil::specialchars($objPicture->title) : StringUtil::specialchars($arrMeta['title']),
            //[string] title-attribut
            'title' => '' !== $objPicture->title ? StringUtil::specialchars($objPicture->title) : StringUtil::specialchars($arrMeta['title']),
            //[string] Bildkommentar oder Bildbeschreibung
            'comment' => !empty($objPicture->comment) ? StringUtil::specialchars(StringUtil::toHtml5($objPicture->comment)) : StringUtil::specialchars($arrMeta['caption']),
            'caption' => !empty($objPicture->comment) ? StringUtil::specialchars(StringUtil::toHtml5($objPicture->comment)) : StringUtil::specialchars($arrMeta['caption']),
            //[string] path to media (video, picture, sound...)
            'href' => $href,
            // single image url
            'single_image_url' => ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items').'/img/'.$arrFile['filename'], $objPage->language)),
            //[string] path to the image,
            'image_src' => $arrFile['path'],
            //[string] path to the other selected media
            'media_src' => $strMediaSrc,
            //[string] path to a media on a social-media-plattform
            'socialMediaSRC' => $objPicture->socialMediaSRC,
            //[string] path to a media stored on the webserver
            'localMediaSRC' => $objPicture->localMediaSRC,
            //[string] Pfad zu einem benutzerdefinierten Thumbnail
            'addCustomThumb' => $objPicture->addCustomThumb,
            //[string] Thumbnailquelle
            'thumb_src' => isset($thumbSrc) ? TL_FILES_URL.$thumbSrc : '',
            //[array] Thumbnail-Ausmasse Array $arrSize[Breite, Hoehe, Methode]
            'size' => $arrSize,
            //[int] thumb-width in px
            'thumb_width' => $arrFile['thumb_width'],
            //[int] thumb-height in px
            'thumb_height' => $arrFile['thumb_height'],
            //[int] image-width in px
            'image_width' => $arrFile['image_width'],
            //[int] image-height in px
            'image_height' => $arrFile['image_height'],
            //[int] das rel oder data-lightbox Attribut fuer das Anzeigen der Bilder in der Lightbox
            'lightbox' => 'data-lightbox="lb'.$objPicture->pid.'"',
            //[int] Zeitstempel der letzten Aenderung
            'tstamp' => $objPicture->tstamp,
            //[int] Sortierindex
            'sorting' => $objPicture->sorting,
            //[boolean] veroeffentlicht (true/false)
            'published' => $objPicture->published,
            //[array] Array mit exif metatags
            'exif' => $exif,
            //[array] Array mit allen Albuminformation (albumname, owners_name...)
            'albuminfo' => $arrAlbumInfo,
            //[array] Array mit Bildinfos aus den meta-Angaben der Datei, gespeichert in tl_files.meta
            'metaData' => $arrMeta,
            //[string] css-ID des Bildcontainers
            'cssID' => '' !== $cssID[0] ? $cssID[0] : '',
            //[string] css-Klasse des Bildcontainers
            'cssClass' => '' !== $cssID[1] ? $cssID[1] : '',
            //[bool] true, wenn es sich um ein Bild handelt, das nicht in files/gallery_creator_albums/albumname gespeichert ist
            'externalFile' => $objPicture->externalFile,
            // [array] picture
            'picture' => $picture,
        ];

        // Fuegt dem Array weitere Eintraege hinzu, falls tl_gallery_creator_pictures erweitert wurde
        $objPicture = GalleryCreatorPicturesModel::findByPk($intPictureId);

        if (null !== $objPicture) {
            $arrPicture = array_merge($objPicture->row(), $arrPicture);
        }

        return $arrPicture;
    }

    /**
     * Returns the information-array about all subalbums ofd a certain parent album.
     *
     * @param $intAlbumId
     * @param $objContentModel
     */
    public static function getSubalbumsInformationArray($intAlbumId, ContentModel $objContentModel): array
    {
        $strSorting = $objContentModel->gc_sorting.' '.$objContentModel->gc_sorting_direction;
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($intAlbumId, '1')
        ;
        $arrSubalbums = [];

        while ($objSubAlbums->next()) {
            // If it is a content element only
            if ('' !== $objContentModel->gc_publish_albums) {
                if (!$objContentModel->gc_publish_all_albums) {
                    if (!\in_array($objSubAlbums->id, StringUtil::deserialize($objContentModel->gc_publish_albums), false)) {
                        continue;
                    }
                }
            }

            $arrSubalbum = self::getAlbumInformationArray($objSubAlbums->id, $objContentModel);
            array_push($arrSubalbums, $arrSubalbum);
        }

        return $arrSubalbums;
    }

    /**
     * Returns the path to the preview-thumbnail of an album.
     *
     * @param $intAlbumId
     *
     * @return array
     */
    public static function getAlbumPreviewThumb($intAlbumId)
    {
        $thumbSRC = System::getContainer()
            ->getParameter('markocupic.gallery_creator_bundle.album_fallback_thumb');

        // Check for an alternate thumbnail
        if ('' !== Config::get('gc_albumFallbackThumb')) {
            if (Validator::isStringUuid(Config::get('gc_albumFallbackThumb'))) {
                $objFile = FilesModel::findByUuid(StringUtil::uuidToBin(Config::get('gc_albumFallbackThumb')));

                if (null !== $objFile) {
                    if (is_file(TL_ROOT.'/'.$objFile->path)) {
                        $thumbSRC = $objFile->path;
                    }
                }
            }
        }

        // Predefine thumb
        $arrThumb = [
            'name' => basename($thumbSRC),
            'path' => $thumbSRC,
        ];

        $objAlb = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null !== $objAlb->thumb) {
            $objPreviewThumb = GalleryCreatorPicturesModel::findByPk($objAlb->thumb);
        } else {
            $objPreviewThumb = GalleryCreatorPicturesModel::findOneByPid($intAlbumId);
        }

        if (null !== $objPreviewThumb) {
            $oFile = FilesModel::findByUuid($objPreviewThumb->uuid);

            if (null !== $oFile) {
                if (is_file(TL_ROOT.'/'.$oFile->path)) {
                    $arrThumb = [
                        'name' => basename($oFile->path),
                        'path' => $oFile->path,
                    ];
                }
            }
        }

        return $arrThumb;
    }

    /**
     * $imgPath - relative path to the filesource
     * angle - the rotation angle is interpreted as the number of degrees to rotate the image anticlockwise.
     * angle shall be 0,90,180,270.
     *
     * @param string $angle
     * @param int
     */
    public static function imageRotate($imgPath, $angle): bool
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
     * @param int $intAlbumId
     */
    public static function importFromFilesystem($intAlbumId, string $strMultiSRC): void
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $images = [];

        $objFilesModel = FilesModel::findMultipleByUuids(explode(',', $strMultiSRC));

        if (null === $objFilesModel) {
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

            $objPictures = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=?')
                ->execute($intAlbumId)
            ;

            $arrPictures['uuid'] = $objPictures->fetchEach('uuid');
            $arrPictures['path'] = $objPictures->fetchEach('path');

            foreach ($arrPictures['path'] as $path) {
                $arrPictures['basename'][] = basename($path);
            }

            $objAlb = GalleryCreatorAlbumsModel::findById($intAlbumId);

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
                    $objFolderModel = FilesModel::findByUuid($objAlb->assignedDir);
                    $errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID '.$objAlb->id.'.';

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

                    self::createNewImage($objAlb->id, $strDestination);
                } else {
                    self::createNewImage($objAlb->id, $image['path']);
                }
            }
        }
    }

    /**
     * Revise tables.
     *
     * @param $albumId
     */
    public static function reviseTables($albumId, bool $blnCleanDb = false): void
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        $_SESSION['GC_ERROR'] = [];

        // Upload-Verzeichnis erstellen, falls nicht mehr vorhanden
        new Folder(Config::get('galleryCreatorUploadPath'));

        // Get album model
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);

        if (null === $objAlbum) {
            return;
        }

        // Check for valid album owner
        $objUser = UserModel::findByPk($objAlbum->owner);

        if (null !== $objUser) {
            $owner = $objUser->name;
        } else {
            $owner = 'no-name';
        }
        $objAlbum->owners_name = $owner;

        // Check for valid pid
        if ($objAlbum->pid > 0) {
            $objParentAlb = $objAlbum->getRelated('pid');

            if (null === $objParentAlb) {
                $objAlbum->pid = null;
            }
        }

        $objAlbum->save();

        if (Database::getInstance()->fieldExists('path', 'tl_gallery_creator_pictures')) {
            // Datensaetzen ohne gültige uuid über den Feldinhalt path versuchen zu "retten"
            $objPictures = GalleryCreatorPicturesModel::findByPid($albumId);

            if (null !== $objPictures) {
                while ($objPictures->next()) {
                    // Get parent album
                    $objFile = FilesModel::findByUuid($objPictures->uuid);

                    if (null === $objFile) {
                        if ('' !== $objPictures->path) {
                            if (is_file($projectDir.'/'.$objPictures->path)) {
                                $objModel = Dbafs::addResource($objPictures->path);

                                if (null !== $objModel) {
                                    $objPictures->uuid = $objModel->uuid;
                                    $objPictures->save();
                                    continue;
                                }
                            }
                        }

                        if (false !== $blnCleanDb) {
                            $msg = ' Deleted Datarecord with ID '.$objPictures->id.'.';
                            $_SESSION['GC_ERROR'][] = $msg;
                            $objPictures->delete();
                        } else {
                            // Show the error-message
                            $path = '' !== $objPictures->path ? $objPictures->path : 'unknown path';
                            $_SESSION['GC_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $path, $objAlbum->alias);
                        }
                    } elseif (!is_file($projectDir.'/'.$objFile->path)) {
                        // If file has an entry in Dbafs, but doesn't exist on the server anymore
                        if (false !== $blnCleanDb) {
                            $msg = 'Deleted Datarecord with ID '.$objPictures->id.'.';
                            $_SESSION['GC_ERROR'][] = $msg;
                            $objPictures->delete();
                        } else {
                            $_SESSION['GC_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $objFile->path, $objAlbum->alias);
                        }
                    } else {
                        // Pfadangaben mit tl_files.path abgleichen (Redundanz)
                        if ($objPictures->path !== $objFile->path) {
                            $objPictures->path = $objFile->path;
                            $objPictures->save();
                        }
                    }
                }
            }
        }

        /**
         * Sorgt dafuer, dass in tl_content im Feld gc_publish_albums keine verwaisten AlbumId's vorhanden sind
         * Prueft, ob die im Inhaltselement definiertern Alben auch noch existieren.
         * Wenn nein, werden diese aus dem Array entfernt.
         */
        $objCont = Database::getInstance()
            ->prepare('SELECT * FROM tl_content WHERE type=?')
            ->execute('gallery_creator')
        ;

        while ($objCont->next()) {
            $newArr = [];
            $arrAlbums = unserialize($objCont->gc_publish_albums);

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
                ->prepare('UPDATE tl_content SET gc_publish_albums=? WHERE id=?')
                ->execute(serialize($newArr), $objCont->id)
            ;
        }
    }

    /**
     * Return the level of an album or subalbum (level_0, level_1, level_2,...).
     *
     * @param int $pid
     */
    public static function getAlbumLevel($pid): int
    {
        $level = 0;

        if (0 === (int) $pid) {
            return $level;
        }
        $hasParent = true;

        while ($hasParent) {
            ++$level;
            $objAlbum = GalleryCreatorAlbumsModel::findByPk($pid);

            if ($objAlbum->pid < 1) {
                $hasParent = false;
            }
            $pid = $objAlbum->pid;
        }

        return $level;
    }
}
