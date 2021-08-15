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
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\PageModel;
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
    public static function createNewImage(GalleryCreatorAlbumsModel $objAlbum, string $strFilepath): bool
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
        $objFolder = FilesModel::findByUuid($objAlbum->assignedDir);
        $assignedDir = null;

        if (null !== $objFolder) {
            if (is_dir($projectDir.'/'.$objFolder->path)) {
                $assignedDir = $objFolder->path;
            }
        }

        if (null === $assignedDir) {
            throw new \Exception('Aborted Script, because there is no upload directory assigned to the Album with ID '.$objAlbum->id);
        }

        // Check if the file ist stored in the album-directory or if it is stored in an external directory
        $blnExternalFile = false;

        if (Input::get('importFromFilesystem')) {
            $blnExternalFile = strstr($objFile->dirname, $assignedDir) ? false : true;
        }

        // Db insert
        $objPictureModel = new GalleryCreatorPicturesModel();
        $objPictureModel->tstamp = time();
        $objPictureModel->pid = $objAlbum->id;
        $objPictureModel->externalFile = $blnExternalFile ? '1' : '';
        // Set uuid before model is saved the first time!!!
        $objPictureModel->uuid = $objFilesModel->uuid;
        $objPictureModel->save();
        $insertId = $objPictureModel->id;

        // Get the next sorting index
        $objImg = Database::getInstance()
            ->prepare('SELECT MAX(sorting)+10 AS maximum FROM tl_gallery_creator_pictures WHERE pid=?')
            ->execute($objAlbum->id)
        ;
        $sorting = $objImg->maximum;

        // If filename should be generated
        if (!$objAlbum->preserve_filename && false === $blnExternalFile) {
            $newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $objAlbum->id, $insertId, $objFile->extension);
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
                $userId = $objAlbum->owner;
            }

            // Finally save the new image in tl_gallery_creator_pictures
            $objPictureModel->owner = $userId;
            $objPictureModel->date = $objAlbum->date;
            $objPictureModel->sorting = $sorting;
            $objPictureModel->save();
            System::log('A new version of tl_gallery_creator_pictures ID '.$insertId.' has been created', __METHOD__, TL_GENERAL);

            // Check for a valid preview-thumb for the album
            if (!$objAlbum->thumb) {
                $objAlbum->thumb = $insertId;
                $objAlbum->save();
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
    public static function fileupload(GalleryCreatorAlbumsModel $objAlbum, string $strName = 'file'): array
    {
        $blnIsError = false;

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Check for a valid upload directory
        $objUploadDir = FilesModel::findByUuid($objAlbum->assignedDir);

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

        // If file exists, append an integer with leading zeros to it -> filename0001.jpg
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

            // Add an integer with a leading zero to the filename -> filename0001.jpg
            $suffix = str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $basename = $filename.'_'.$suffix;

            // Break after 1000 loops and generate random filename
            if (1000 === $i) {
                return $dirname.'/'.md5($basename.microtime()).'.'.$extension;
            }
        } while (false === $isUnique);
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
    public static function getAlbumInformationArray(GalleryCreatorAlbumsModel $objAlbum, ContentModel $objContentModel): array
    {
        global $objPage;

        // Get the page model
        $objPageModel = PageModel::findByPk($objPage->id);

        // Anzahl Subalben ermitteln
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE published=? AND pid=?')
            ->execute('1', $objAlbum->id)
        ;

        $objPics = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND published=?')
            ->execute($objAlbum->id, '1')
        ;

        $arrSize = unserialize($objContentModel->gc_size_albumlisting);

        $href = null;

        if (TL_MODE === 'FE') {
            // Generate the url as a formatted string
            $href = StringUtil::ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/%s' : '/items/%s'), $objPage->language));

            // Add albumAlias
            $href = sprintf($href, $objAlbum->alias);
        }

        $arrPreviewThumb = static::getAlbumPreviewThumb($objAlbum);
        $strImageSrc = $arrPreviewThumb['path'];

        // Generate the thumbnails and the picture element
        try {
            $thumbSrc = Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
            $picture = Picture::create($strImageSrc, $arrSize)->getTemplateData();

            if ($thumbSrc !== $strImageSrc) {
                new File(rawurldecode($thumbSrc));
            }
        } catch (\Exception $e) {
            System::log('Image "'.$strImageSrc.'" could not be processed: '.$e->getMessage(), __METHOD__, TL_ERROR);
        }

        $picture['alt'] = StringUtil::specialchars($objAlbum->name);
        $picture['title'] = StringUtil::specialchars($objAlbum->name);

        // CSS class
        $arrCssClasses = [];
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) ? 'has-child-album' : '';
        $arrCssClasses[] = !$objPics->numRows ? 'empty-album' : '';

        $arrAlbum = [
            // [string] event date formatted
            'event_date' => Date::parse(Config::get('dateFormat'), $objAlbum->date),
            // [string] Event-Location
            'event_location' => StringUtil::specialchars($objAlbum->event_location),
            // [string] albumname
            'name' => StringUtil::specialchars($objAlbum->name),
            // [string] album caption
            'comment' => StringUtil::toHtml5(nl2br((string) $objAlbum->comment)),
            'caption' => StringUtil::toHtml5(nl2br((string) $objAlbum->comment)),
            // [string] Link zur Detailansicht
            'href' => $href,
            // [string] Inhalt fuer das title Attribut
            'title' => $objAlbum->name.' ['.($objPics->numRows ? $objPics->numRows.' '.$GLOBALS['TL_LANG']['gallery_creator']['pictures'] : '').($objContentModel->gc_hierarchicalOutput && $objSubAlbums->numRows > 0 ? ' '.$GLOBALS['TL_LANG']['gallery_creator']['contains'].' '.$objSubAlbums->numRows.'  '.$GLOBALS['TL_LANG']['gallery_creator']['subalbums'].']' : ']'),
            // [int] Anzahl Bilder im Album
            'count' => (int) $objPics->numRows,
            // [int] Anzahl Unteralben
            'count_subalbums' => \count(GalleryCreatorAlbumsModel::getChildAlbums($objAlbum->id)),
            // [string] alt Attribut fuer das Vorschaubild
            'alt' => $arrPreviewThumb['name'],
            // [string] Pfad zum Originalbild
            'src' => TL_FILES_URL.$arrPreviewThumb['path'],
            // [string] Pfad zum Thumbnail
            'thumb_src' => TL_FILES_URL.Image::get($arrPreviewThumb['path'], $arrSize[0], $arrSize[1], $arrSize[2]),
            // [string] css-Classname
            'class' => 'thumb',
            // [array] thumbnail size
            'size' => $arrSize,
            // [string] javascript
            'thumbMouseover' => $objContentModel->gc_activateThumbSlider ? 'objGalleryCreator.initThumbSlide(this,'.$objAlbum->id.','.$objPics->numRows.');' : '',
            // [array] picture
            'picture' => $picture,
            // [string] cssClass
            'cssClass' => implode(' ', array_filter($arrCssClasses)),
        ];

        $arrAlbum = array_merge($objAlbum->row(), $arrAlbum);

        return $arrAlbum;
    }

    /**
     * Returns the picture information array.
     *
     * @throws \Exception
     */
    public static function getPictureInformationArray(GalleryCreatorPicturesModel $objPicture, ContentModel $objContentModel): ?array
    {
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

        // Get parent album
        $objAlbum = $objPicture->getRelated('pid');

        // Generate the thumbnails and the picture element
        try {
            $thumbSrc = Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
            // Overwrite $thumbSrc if there is a valid custom thumb
            if ($objPicture->addCustomThumb && !empty($objPicture->customThumb)) {
                $customThumbModel = FilesModel::findByUuid($objPicture->customThumb);

                if (null !== $customThumbModel) {
                    if (is_file($projectDir.'/'.$customThumbModel->path)) {
                        $objFileCustomThumb = new File($customThumbModel->path);

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
            //Name des Erstellers
            'owners_name' => $objOwner->name,
            // [string] name (basename/filename of the file)
            'name' => StringUtil::specialchars($arrFile['basename']),
            // [string] filename without extension
            'filename' => $arrFile['filename'],
            // uuid of the image
            'path' => $arrFile['path'],
            // [string] basename similar to name
            'basename' => $arrFile['basename'],
            // [string] dirname
            'dirname' => $arrFile['dirname'],
            // [string] file-extension
            'extension' => $arrFile['extension'],
            // [string] alt-attribut
            'alt' => '' !== $objPicture->title ? StringUtil::specialchars($objPicture->title) : StringUtil::specialchars($arrMeta['title']),
            // [string] title-attribut
            'title' => '' !== $objPicture->title ? StringUtil::specialchars($objPicture->title) : StringUtil::specialchars($arrMeta['title']),
            // [string] Bildkommentar oder Bildbeschreibung
            'comment' => !empty($objPicture->comment) ? StringUtil::specialchars(StringUtil::toHtml5($objPicture->comment)) : StringUtil::specialchars($arrMeta['caption']),
            'caption' => !empty($objPicture->comment) ? StringUtil::specialchars(StringUtil::toHtml5($objPicture->comment)) : StringUtil::specialchars($arrMeta['caption']),
            // [string] path to media (video, picture, sound...)
            'href' => rawurlencode($href),
            // single image url
            'single_image_url' => StringUtil::ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items').'/img/'.$arrFile['filename'], $objPage->language)),
            // [string] path to the other selected media
            'media_src' => rawurlencode($strMediaSrc),
            // [string] Thumbnailquelle
            'thumb_src' => isset($thumbSrc) ? TL_FILES_URL.$thumbSrc : '',
            // [array] Thumbnail-Ausmasse Array $arrSize[Breite, Hoehe, Methode]
            'size' => $arrSize,
            // [int] thumb-width in px
            'thumb_width' => $arrFile['thumb_width'],
            // [int] thumb-height in px
            'thumb_height' => $arrFile['thumb_height'],
            // [int] image-width in px
            'image_width' => $arrFile['image_width'],
            // [int] image-height in px
            'image_height' => $arrFile['image_height'],
            // [int] das rel oder data-lightbox Attribut fuer das Anzeigen der Bilder in der Lightbox
            'lightbox' => 'data-lightbox="lb'.$objPicture->pid.'"',
            // [array] Array mit exif metatags
            'exif' => $exif,
            // [array] Array mit allen Albuminformation (albumname, owners_name...)
            'albuminfo' => $objAlbum ? $objAlbum->row() : null,
            // [array] Array mit Bildinfos aus den meta-Angaben der Datei, gespeichert in tl_files.meta
            'metaData' => $arrMeta,
            // [string] css-ID des Bildcontainers
            'cssID' => '' !== $cssID[0] ? $cssID[0] : '',
            // [string] css-Klasse des Bildcontainers
            'cssClass' => '' !== $cssID[1] ? $cssID[1] : '',
            // [array] picture
            'picture' => $picture,
        ];

        // Add more data
        $arrPicture = array_merge($objPicture->row(), $arrPicture);

        return $arrPicture;
    }

    /**
     * Returns the information-array about all subalbums of a certain parent album.
     */
    public static function getSubalbumsInformationArray(GalleryCreatorAlbumsModel $objAlbum, ContentModel $objContentModel): array
    {
        $strSorting = $objContentModel->gc_sorting.' '.$objContentModel->gc_sorting_direction;
        $objSubAlbums = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$strSorting)
            ->execute($objAlbum->id, '1')
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
            $objSubAlbum = GalleryCreatorAlbumsModel::findByPk($objSubAlbums->id);

            if (null !== $objSubAlbum) {
                $arrSubalbum = self::getAlbumInformationArray($objSubAlbum, $objContentModel);
                array_push($arrSubalbums, $arrSubalbum);
            }
        }

        return $arrSubalbums;
    }

    /**
     * Returns the path to the preview-thumbnail of an album.
     */
    public static function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $objAlbum): array
    {
        $thumbSRC = System::getContainer()->getParameter('markocupic.gallery_creator_bundle.album_fallback_thumb');
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Check for an alternate thumbnail
        if ('' !== Config::get('gc_albumFallbackThumb')) {
            if (Validator::isStringUuid(Config::get('gc_albumFallbackThumb'))) {
                $objFile = FilesModel::findByUuid(StringUtil::uuidToBin(Config::get('gc_albumFallbackThumb')));

                if (null !== $objFile) {
                    if (is_file($projectDir.'/'.$objFile->path)) {
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

        if (null !== $objAlbum->thumb) {
            $objPreviewThumb = GalleryCreatorPicturesModel::findByPk($objAlbum->thumb);
        } else {
            $objPreviewThumb = GalleryCreatorPicturesModel::findOneByPid($objAlbum->id);
        }

        if (null !== $objPreviewThumb) {
            $oFile = FilesModel::findByUuid($objPreviewThumb->uuid);

            if (null !== $oFile) {
                if (is_file($projectDir.'/'.$oFile->path)) {
                    $arrThumb = [
                        'name' => basename($oFile->path),
                        'path' => $oFile->path,
                    ];
                }
            }
        }

        return $arrThumb;
    }

    public static function initCounter(GalleryCreatorAlbumsModel $objAlbum): void
    {
        $crawlerDetect = new CrawlerDetect();

        // Check the user agent of the current 'visitor'
        if (TL_MODE !== 'FE' || $crawlerDetect->isCrawler()) {
            return;
        }

        $arrVisitors = StringUtil::deserialize($objAlbum->visitors_details, true);

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
        $objAlbum->visitors = ++$objAlbum->visitors;
        $objAlbum->visitors_details = serialize($arrVisitors);
        $objAlbum->save();
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
    public static function importFromFilesystem(GalleryCreatorAlbumsModel $objAlbum, array $arrMultiSRC): void
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

            $objPictures = Database::getInstance()
                ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=?')
                ->execute($objAlbum->id)
            ;

            $arrPictures['uuid'] = $objPictures->fetchEach('uuid');
            $arrPictures['path'] = $objPictures->fetchEach('path');

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
                    $objFolderModel = FilesModel::findByUuid($objAlbum->assignedDir);
                    $errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID '.$objAlbum->id.'.';

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

                    self::createNewImage($objAlbum, $strDestination);
                } else {
                    self::createNewImage($objAlbum, $image['path']);
                }
            }
        }
    }

    /**
     * Revise tables.
     *
     * @throws \Exception
     */
    public static function reviseTables(GalleryCreatorAlbumsModel $objAlbum, bool $blnCleanDb = false): void
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $session= System::getContainer()->get('session');
        $session->set('gc_error', []);

        // Upload-Verzeichnis erstellen, falls nicht mehr vorhanden
        new Folder(Config::get('galleryCreatorUploadPath'));

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
            // Try to identify entries with no uuid via path
            $objPictures = GalleryCreatorPicturesModel::findByPidfindByPid($objAlbum->id);

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

                        $arrError = $session->get('gc_error');

                        if (false !== $blnCleanDb) {
                            $arrError[] = ' Deleted data record with ID '.$objPictures->id.'.';
                            $objPictures->delete();
                        } else {
                            // Show the error-message
                            $path = '' !== $objPictures->path ? $objPictures->path : 'unknown path';
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $path, $objAlbum->alias);
                        }

                        $session->set('gc_error', $arrError);

                    } elseif (!is_file($projectDir.'/'.$objFile->path)) {

                        $arrError = $session->get('gc_error');

                        // If file has an entry in Dbafs, but doesn't exist on the server anymore
                        if (false !== $blnCleanDb) {
                            $arrError[] = 'Deleted data record with ID '.$objPictures->id.'.';
                            $objPictures->delete();
                        } else {
                            $arrError[] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $objFile->path, $objAlbum->alias);
                        }

                        $session->set('gc_error', $arrError);

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
         * Ensures that there are no orphaned AlbumId's in the gc_publish_albums field in tl_content.
         * Checks whether the albums defined in the content element still exist.
         * If not, these are removed from the array.
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
            $objAlbum = GalleryCreatorAlbumsModel::findByPk($pid);

            if ($objAlbum->pid < 1) {
                $hasParent = false;
            }
            $pid = $objAlbum->pid;
        }

        return $level;
    }
}
