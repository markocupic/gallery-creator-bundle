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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Patchwork\Utf8;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FileUtil
{
    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $galleryCreatorCopyImagesOnImport;

    public function __construct(ScopeMatcher $scopeMatcher, RequestStack $requestStack, Connection $connection, ?LoggerInterface $logger, string $projectDir, string $galleryCreatorCopyImagesOnImport)
    {
        $this->scopeMatcher = $scopeMatcher;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->galleryCreatorCopyImagesOnImport = $galleryCreatorCopyImagesOnImport;
    }

    /**
     * @param int $angle the rotation angle is interpreted as the number of degrees to rotate the image anticlockwise
     */
    public function imageRotate(File $file, int $angle): bool
    {
        if (!is_file($this->projectDir.'/'.$file->path) || !$file->isGdImage) {
            return false;
        }

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

        $imgSrc = $this->projectDir.'/'.$file->path;

        $source = imagecreatefromjpeg($imgSrc);

        // Rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $imgSrc);
        imagedestroy($source);

        return true;
    }

    /**
     * @throws \Exception
     */
    public function addImageToAlbum(GalleryCreatorAlbumsModel $albumModel, string $strFilepath): bool
    {
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
            if (is_dir($this->projectDir.'/'.$objFolder->path)) {
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

        // New record
        $pictureModel = new GalleryCreatorPicturesModel();
        $pictureModel->tstamp = time();
        $pictureModel->pid = $albumModel->id;
        $pictureModel->externalFile = $blnExternalFile ? '1' : '';

        // Set uuid before model is saved the first time!!!
        $pictureModel->uuid = $objFilesModel->uuid;
        $pictureModel->save();
        $insertId = $pictureModel->id;

        // Get the next sorting index
        $stmt = $this->connection->executeQuery(
            'SELECT MAX(sorting)+10 AS maximum FROM tl_gallery_creator_pictures WHERE pid = ?',
            [$albumModel->id]
        );
        $result = $stmt->fetchAssociative();
        $sorting = $result['maximum'];

        // If we use generic file names
        if (!$albumModel->preserveFilename && false === $blnExternalFile) {
            $newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $albumModel->id, $insertId, $objFile->extension);
            $objFile->renameTo($newFilepath);
        }

        if (is_file($this->projectDir.'/'.$objFile->path)) {
            // Get the user id
            $userId = BackendUser::getInstance()->id;

            // Finaly save the new image in tl_gallery_creator_pictures
            $pictureModel->owner = $userId;
            $pictureModel->date = $albumModel->date;
            $pictureModel->sorting = $sorting;
            $pictureModel->save();

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

            if ($this->logger) {
                $this->logger->info(
                    sprintf('Added a new picture with ID %s to the album "%s".', $insertId, $albumModel->name),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );
            }

            return true;
        }

        if (true === $blnExternalFile) {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['fileNotFound'], $strFilepath));
        } else {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['uploadError'], $strFilepath));
        }

        if ($this->logger) {
            $this->logger->error(
                sprintf('Unable to create a new image in: %s!', $strFilepath),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function generateSanitizedAndUniqueFilename(string $strPath): string
    {
        $dirname = \dirname($strPath);
        $filename = basename($strPath);
        $filename = StringUtil::sanitizeFileName($filename);
        $filename = Utf8::toAscii($filename);
        $strPath = $dirname.'/'.$filename;

        if (preg_match('/\.$/', $strPath)) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['invalidName']);
        }

        $pathinfo = pathinfo($strPath);
        $extension = $pathinfo['extension'];
        $basename = basename($strPath, '.'.$extension);
        $dirname = \dirname($strPath);

        // If file already exists, append an integer with leading zeros -> filename0001.jpg
        for ($i = 1; $i < 1000; ++$i) {
            if (!file_exists($this->projectDir.'/'.$dirname.'/'.$basename.'.'.$extension)) {
                // Exit loop if filename is unique
                return $dirname.'/'.$basename.'.'.$extension;
            }

            if (1 === $i) {
                $filename = $basename;
            } else {
                $filename = substr($basename, 0, -5);
            }

            // Add an integer with a leading zero to the filename -> filename0001.jpg
            $suffix = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $basename = $filename.'_'.$suffix;
        }

        // Generate random path
        return $dirname.'/'.md5($basename.microtime()).'.'.$extension;
    }

    /**
     * Load images from the Contao filesystem into an album.
     *
     * @throws \Exception
     */
    public function importFromFilesystem(GalleryCreatorAlbumsModel $albumModel, array $arrMultiSRC): void
    {
        $images = [];

        if (null === ($objFilesModel = FilesModel::findMultipleByUuids($arrMultiSRC))) {
            return;
        }

        while ($objFilesModel->next()) {
            // Continue if the file has been processed or does not exist
            if (isset($images[$objFilesModel->path]) || !file_exists($this->projectDir.'/'.$objFilesModel->path)) {
                continue;
            }

            // If item is a file, then store it in the array
            if ('file' === $objFilesModel->type) {
                $objFile = new File($objFilesModel->path);

                if ($objFile->isGdImage) {
                    $images[$objFile->path] = ['uuid' => $objFilesModel->uuid, 'basename' => $objFile->basename, 'path' => $objFile->path];
                }
            } else {
                // If resource is a directory, then store its files in the array
                $objSubfilesModel = FilesModel::findMultipleFilesByFolder($objFilesModel->path);

                if (null === $objSubfilesModel) {
                    continue;
                }

                while ($objSubfilesModel->next()) {
                    // Skip sub folders
                    if ('folder' === $objSubfilesModel->type || !is_file($this->projectDir.'/'.$objSubfilesModel->path)) {
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

            $arrPictures['uuid'] = $this->connection
                ->executeQuery('SELECT uuid FROM tl_gallery_creator_pictures WHERE pid = ?',  [$albumModel->id])
                ->fetchFirstColumn()
            ;

            $arrPictures['path'] = $this->connection
                ->executeQuery('SELECT path FROM tl_gallery_creator_pictures WHERE pid = ?',  [$albumModel->id])
                ->fetchFirstColumn()
            ;

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

                if ($this->galleryCreatorCopyImagesOnImport) {
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

                    if (!is_dir($this->projectDir.'/'.$objFolderModel->path)) {
                        throw new \Exception($errMsg);
                    }

                    $strDestination = $this->generateSanitizedAndUniqueFilename($objFolderModel->path.'/'.basename($strSource));

                    if (is_file($this->projectDir.'/'.$strSource)) {
                        // Copy Image to the upload folder
                        $objFile = new File($strSource);
                        $objFile->copyTo($strDestination);
                        Dbafs::addResource($strSource);
                    }

                    $this->addImageToAlbum($albumModel, $strDestination);
                } else {
                    $this->addImageToAlbum($albumModel, $image['path']);
                }
            }
        }
    }

    /**
     * Move uploaded file to the album directory.
     *
     * @throws \Exception
     */
    public function fileupload(GalleryCreatorAlbumsModel $albumModel, string $strName = 'file'): array
    {
        $blnIsError = false;

        // Check for a valid upload directory
        $objUploadDir = FilesModel::findByUuid($albumModel->assignedDir);

        if (null === $objUploadDir || !is_dir($this->projectDir.'/'.$objUploadDir->path)) {
            $blnIsError = true;
            Message::addError('No upload directory defined in the album settings!');
        }

        // Check if there are some files in $_FILES
        if (!\is_array($_FILES[$strName])) {
            $blnIsError = true;
            Message::addError('Please select one or more files.');
        }

        if ($blnIsError) {
            return [];
        }

        if (\is_string($_FILES[$strName]['name']) && \strlen($_FILES[$strName]['name'])) {
            $_FILES[$strName]['name'] = basename($this->generateSanitizedAndUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name']));
        } elseif (\is_array($_FILES[$strName]['name'])) {
            $intCount = \count($_FILES[$strName]['name']);

            for ($i = 0; $i < $intCount; ++$i) {
                if (!empty($_FILES[$strName]['name'][$i])) {
                    // Generate unique filename
                    $_FILES[$strName]['name'][$i] = basename($this->generateSanitizedAndUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name'][$i]));
                }
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
}
