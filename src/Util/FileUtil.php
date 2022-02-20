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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileUtil
{
    private RequestStack $requestStack;
    private Connection $connection;
    private TranslatorInterface $translator;
    private string $projectDir;
    private bool $galleryCreatorCopyImagesOnImport;
    private array $galleryCreatorValidExtensions;
    private ?LoggerInterface $logger;

    public function __construct(RequestStack $requestStack, Connection $connection, TranslatorInterface $translator, string $projectDir, bool $galleryCreatorCopyImagesOnImport, array $galleryCreatorValidExtensions, LoggerInterface $logger = null)
    {
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->projectDir = $projectDir;
        $this->galleryCreatorCopyImagesOnImport = $galleryCreatorCopyImagesOnImport;
        $this->galleryCreatorValidExtensions = $galleryCreatorValidExtensions;
        $this->logger = $logger;
    }

    /**
     * @param int $angle number of degrees to rotate the image anticlockwise
     */
    public function imageRotate(File $file, int $angle): bool
    {
        if (!is_file($this->projectDir.'/'.$file->path) || !$file->isGdImage) {
            Message::addError($this->translator->trans('ERR.rotateImageError', [$file->path], 'contao_default'));

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

        if (false === ($source = imagecreatefromjpeg($imgSrc))) {
            Message::addError($this->translator->trans('ERR.rotateImageError', [$file->path], 'contao_default'));

            return false;
        }

        // Rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $imgSrc);
        imagedestroy($source);

        return true;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function addImageToAlbum(GalleryCreatorAlbumsModel $albumModel, File $file): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $filesModel = $file->getModel();

        if (null === $filesModel) {
            throw new ResponseException(new JsonResponse('Aborted script, because we found no file model for '.$file->path.'.', 400));
        }

        // Get the folder that is assigned to the album
        $objFolder = FilesModel::findByUuid($albumModel->assignedDir);
        $assignedDir = null;

        if (null !== $objFolder) {
            if (is_dir($this->projectDir.'/'.$objFolder->path)) {
                $assignedDir = $objFolder->path;
            }
        }

        if (null === $assignedDir) {
            throw new ResponseException(new JsonResponse('Aborted script, because there is no upload directory assigned to the Album with ID '.$albumModel->id, 400));
        }

        // Check if the file is stored on the album directory or if it is stored in an external directory
        $blnExternalFile = false;

        if ($request->query->has('importFromFilesystem')) {
            $blnExternalFile = !strstr($file->dirname, $assignedDir);
        }

        // New record
        $pictureModel = new GalleryCreatorPicturesModel();
        $pictureModel->tstamp = time();
        $pictureModel->pid = $albumModel->id;
        $pictureModel->externalFile = $blnExternalFile ? '1' : '';

        // Set the file uuid before the model is saved the first time!!!
        $pictureModel->uuid = $filesModel->uuid;
        $pictureModel->save();
        $insertId = $pictureModel->id;

        // Get the next sorting value
        $sortingVal = $this->connection->fetchOne(
            'SELECT MAX(sorting) + 10 AS sortingVal FROM tl_gallery_creator_pictures WHERE pid = ?',
            [$albumModel->id]
        );

        if (!$albumModel->preserveFilename && false === $blnExternalFile) {
            // Generate a generic file name
            $newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $albumModel->id, $insertId, $file->extension);
            $file->renameTo($newFilepath);
        }

        if (is_file($this->projectDir.'/'.$file->path)) {
            // Finally, save the new image in tl_gallery_creator_pictures
            $pictureModel->owner = BackendUser::getInstance()->id;
            $pictureModel->date = $albumModel->date;
            $pictureModel->sorting = $sortingVal;
            $pictureModel->save();

            // Use this picture as the album preview image, if the album doesn't have one.
            if (!$albumModel->thumb) {
                $albumModel->thumb = $insertId;
                $albumModel->save();
            }

            // Trigger the galleryCreatorImagePostInsert - HOOK
            if (isset($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert'])) {
                foreach ($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert'] as $callback) {
                    System::importStatic($callback[0])->{$callback[1]}($pictureModel);
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
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['fileNotFound'], $file->path));
        } else {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['uploadError'], $file->path));
        }

        if ($this->logger) {
            $this->logger->info(
                sprintf('Unable to create a new image in: %s!', $file->path),
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
        $strPath = $dirname.'/'.$filename;

        if (preg_match('/\.$/', $strPath)) {
            throw new \Exception($GLOBALS['TL_LANG']['ERR']['invalidName']);
        }

        $pathInfo = pathinfo($strPath);
        $extension = $pathInfo['extension'];
        $basename = basename($strPath, '.'.$extension);
        $dirname = \dirname($strPath);

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
     * @throws DoctrineDBALDriverException
     * @throws \Exception
     */
    public function importFromFilesystem(GalleryCreatorAlbumsModel $albumModel, array $arrMultiSRC): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $images = [];

        if (null === ($filesModel = FilesModel::findMultipleByUuids($arrMultiSRC))) {
            return;
        }

        while ($filesModel->next()) {
            // Continue if the file has been processed or does not exist
            if (isset($images[$filesModel->path]) || !file_exists($this->projectDir.'/'.$filesModel->path)) {
                continue;
            }

            // If item is a file,
            if ('file' === $filesModel->type) {
                if (null === ($file = new File($filesModel->path))) {
                    continue;
                }

                if (!$this->isValidFileName($file->name)) {
                    Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $file->extension));
                }

                $images[$file->path] = ['uuid' => $filesModel->uuid, 'basename' => $file->basename, 'path' => $file->path];
            } else {
                // If resource is a directory
                $subFilesModel = FilesModel::findMultipleFilesByFolder($filesModel->path);

                if (null === $subFilesModel) {
                    continue;
                }

                while ($subFilesModel->next()) {
                    // Skip child folders
                    if ('folder' === $subFilesModel->type || !is_file($this->projectDir.'/'.$subFilesModel->path)) {
                        continue;
                    }

                    if (null === ($file = new File($subFilesModel->path))) {
                        continue;
                    }

                    if (!$this->isValidFileName($file->name)) {
                        continue;
                    }

                    $images[$file->path] = ['uuid' => $subFilesModel->uuid, 'basename' => $file->basename, 'path' => $file->path];
                }
            }
        }

        if (empty($images)) {
            return;
        }

        $arrPictures = [
            'uuid' => [],
            'path' => [],
            'basename' => [],
        ];

        $arrPictures['uuid'] = $this->connection
            ->executeQuery('SELECT uuid FROM tl_gallery_creator_pictures WHERE pid = ?', [$albumModel->id])
            ->fetchFirstColumn()
            ;

        $arrPictures['path'] = $this->connection
            ->executeQuery('SELECT path FROM tl_files WHERE uuid = ?', [$arrPictures['uuid']])
            ->fetchFirstColumn()
            ;

        $arrPictures['basename'] = array_map(static fn ($path) => basename($path), $arrPictures['path']);

        foreach ($images as $image) {
            // Prevent duplicate entries
            if (\in_array($image['uuid'], $arrPictures['uuid'], false)) {
                continue;
            }

            // Prevent duplicate entries
            if (\in_array($image['basename'], $arrPictures['basename'], true)) {
                continue;
            }

            $request->query->set('importFromFilesystem', 'true');

            if (!$this->galleryCreatorCopyImagesOnImport) {
                $this->addImageToAlbum($albumModel, new File($image['path']));
            } else {
                $strSource = $image['path'];

                // Get the album upload directory
                $objFolderModel = FilesModel::findByUuid($albumModel->assignedDir);

                if (null === $objFolderModel || 'folder' !== $objFolderModel->type || !is_dir($objFolderModel->getAbsolutePath())) {
                    $errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID '.$albumModel->id.'.';

                    throw new \Exception($errMsg);
                }

                $strDestination = $this->generateSanitizedAndUniqueFilename($objFolderModel->path.'/'.basename($strSource));

                $file = new File($strSource);

                if (!is_file($this->projectDir.'/'.$file->path)) {
                    throw new ResponseException(new Response('Could not find file '.$file->path.'.', 415));
                }

                // Copy the image to the upload folder
                $file->copyTo($strDestination);
                Dbafs::addResource($strDestination);

                $this->addImageToAlbum($albumModel, new File($strDestination));
            }
        }
    }

    /**
     * Move uploaded file to the album directory.
     *
     * @throws \Exception
     */
    public function uploadFile(GalleryCreatorAlbumsModel $albumModel, string $strName = 'file'): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Check for a valid upload directory
        $objUploadDir = FilesModel::findByUuid($albumModel->assignedDir);

        if (null === $objUploadDir || !is_dir($this->projectDir.'/'.$objUploadDir->path)) {
            Message::addError('No upload directory defined in the album settings!');

            return [];
        }

        // Check if there are some files in $_FILES
        if (!isset($_FILES[$strName])) {
            Message::addError('Please select one or more files to be uploaded.');

            return [];
        }

        // Dropzone sends only one file per request
        if (\is_array($_FILES[$strName]) && \is_string($_FILES[$strName]['name']) && \strlen($_FILES[$strName]['name'])) {
            // Generate a unique filename
            $_FILES[$strName]['name'] = basename($this->generateSanitizedAndUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name']));

            if (!$this->isValidFileName($_FILES[$strName]['name'])) {
                $error = $this->translator->trans(
                    'ERR.notAllowedFilenameOrExtension',
                    [
                        $_FILES[$strName]['name'],
                        implode(', ', $this->galleryCreatorValidExtensions),
                    ],
                    'contao_default'
                );

                Message::addError($error);

                // Send error message to Dropzone
                throw new ResponseException(new JsonResponse($error, 415));
            }
        } elseif (isset($_FILES[$strName]['name']) && \is_array($_FILES[$strName]['name'])) {
            $intCount = \count($_FILES[$strName]['name']);

            for ($i = 0; $i < $intCount; ++$i) {
                if (!empty($_FILES[$strName]['name'][$i])) {
                    // Generate a unique filename
                    $_FILES[$strName]['name'][$i] = basename($this->generateSanitizedAndUniqueFilename($objUploadDir->path.'/'.$_FILES[$strName]['name'][$i]));

                    if (!$this->isValidFileName($_FILES[$strName]['name'][$i])) {
                        $error = $this->translator->trans(
                            'ERR.notAllowedFilenameOrExtension',
                            [
                                $_FILES[$strName]['name'][$i],
                                implode(', ', $this->galleryCreatorValidExtensions),
                            ],
                            'contao_default'
                        );

                        // Send error message
                        throw new ResponseException(new JsonResponse($error, 415));
                    }
                }
            }
        }

        // Resize image if feature is enabled
        if ($request->request->get('imageResolution', 0) > 1) {
            Config::set('imageWidth', $request->request->get('imageResolution'));
            Config::set('imageHeight', 999999999);
        } else {
            Config::set('maxImageWidth', 999999999);
        }

        // Call the Contao file upload service
        $objUpload = new FileUpload();
        $objUpload->setName($strName);
        $arrUpload = $objUpload->uploadTo($objUploadDir->path);

        foreach ($arrUpload as $strFileSrc) {
            Dbafs::addResource($strFileSrc);
        }

        return $arrUpload;
    }

    public function isValidFileName(string $strName): bool
    {
        $strName = strtolower($strName);

        if (!Validator::isValidFileName($strName)) {
            return false;
        }

        $pathParts = pathinfo($strName);

        if (!$pathParts['extension']) {
            return false;
        }

        if (!\in_array($pathParts['extension'], $this->galleryCreatorValidExtensions, true)) {
            return false;
        }

        return true;
    }
}
