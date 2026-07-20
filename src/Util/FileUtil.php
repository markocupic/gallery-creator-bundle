<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Message;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\DBAL\Types\Types;
use Markocupic\GalleryCreatorBundle\Event\ImagePostInsertEvent;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FileUtil
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private EventDispatcherInterface $eventDispatcher,
        private Filesystem $filesystem,
        private RequestStack $requestStack,
        private Security $security,
        private TranslatorInterface $translator,
        private string $projectDir,
        private bool $galleryCreatorCopyImagesOnImport,
        private array $galleryCreatorValidExtensions,
        private LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * @param int $angle number of degrees to rotate the image anticlockwise
     */
    public function imageRotate(File $file, int $angle): bool
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $imgPath = Path::join($this->projectDir, $file->path);

        if (!$this->filesystem->exists($imgPath) || !$file->isGdImage) {
            $messageAdapter->addError($this->translator->trans('ERR.rotateImageError', [$file->path], 'contao_default'));

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

        $extension = strtolower($file->extension);

        // Load the image with the matching GD reader
        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($imgPath),
            'png' => @imagecreatefrompng($imgPath),
            'gif' => @imagecreatefromgif($imgPath),
            'webp' => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imgPath) : false,
            default => false,
        };

        if (false === $source) {
            $messageAdapter->addError($this->translator->trans('ERR.rotateImageError', [$file->path], 'contao_default'));

            return false;
        }

        // Rotate counter-clockwise (multiples of 90° do not add any background area)
        $rotated = imagerotate($source, $angle, 0);

        // Preserve transparency for formats that support an alpha channel
        if (\in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        }

        // Write the rotated image back in the same format
        $success = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($rotated, $imgPath),
            'png' => imagepng($rotated, $imgPath),
            'gif' => imagegif($rotated, $imgPath),
            'webp' => imagewebp($rotated, $imgPath),
            default => false,
        };

        imagedestroy($source);
        imagedestroy($rotated);

        return $success;
    }

    /**
     * @throws DoctrineDBALException
     */
    public function addImageToAlbum(GalleryCreatorAlbumsModel $albumModel, File $file): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        $filesModel = $file->getModel();

        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            throw new \Exception('Aborted script, because we could not find a valid BackendUser.');
        }

        if (null === $filesModel) {
            throw new ResponseException(new JsonResponse('Aborted script, because we found no file model for '.$file->path.'.', 400));
        }

        // Get the folder assigned to the album
        $folderModel = $this->framework->getAdapter(FilesModel::class)->findByUuid($albumModel->assignedDir);
        $assignedDir = null;

        if (null !== $folderModel) {
            if ($this->filesystem->exists(Path::join($this->projectDir, $folderModel->path))) {
                $assignedDir = $folderModel->path;
            }
        }

        if (null === $assignedDir) {
            throw new ResponseException(new JsonResponse('Aborted script, because there is no upload directory assigned to the Album with ID '.$albumModel->id, 400));
        }

        // Check if the file is stored in the album directory or if it is stored in an external directory
        $isExternalFile = false;

        if ($request && $request->query->has('importFromFilesystem')) {
            $isExternalFile = !str_starts_with($file->dirname, $assignedDir);
        }

        // New record
        $pictureModel = $this->framework->createInstance(GalleryCreatorPicturesModel::class);
        $pictureModel->tstamp = time();
        $pictureModel->pid = $albumModel->id;
        $pictureModel->externalFile = $isExternalFile ? 1 : 0;

        // Set the file uuid before the model is saved the first time!!!
        $pictureModel->uuid = $filesModel->uuid;
        $pictureModel->save();
        $insertId = $pictureModel->id;

        // Get the next sorting value
        $nextSorting = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(sorting), 0) + 10 AS sortingVal FROM tl_gallery_creator_pictures WHERE pid = ?',
            [$albumModel->id],
        );

        if (!$albumModel->preserveFilename && false === $isExternalFile) {
            // Generate a generic file name
            $newFilepath = \sprintf('%s/alb%s_img%s.%s', $assignedDir, $albumModel->id, $insertId, $file->extension);
            $file->renameTo($newFilepath);
        }

        if ($this->filesystem->exists(Path::join($this->projectDir, $file->path))) {
            // Finally, save the new image in tl_gallery_creator_pictures
            $pictureModel->cuser = $user->id;
            $pictureModel->date = $albumModel->date;
            $pictureModel->sorting = $nextSorting;
            $pictureModel->save();

            // Use this picture as the album preview image if the album doesn't have one.
            if (!$albumModel->thumb) {
                $albumModel->thumb = $insertId;
                $albumModel->save();
            }

            // Dispatch the ImagePostInsertEvent
            $this->eventDispatcher->dispatch(new ImagePostInsertEvent($pictureModel));

            $this->logger?->info(
                \sprintf('Added a new picture with ID %s to the album "%s".', $insertId, $albumModel->name),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)],
            );

            return true;
        }

        // Delete the new picture if the file could not be saved
        $pictureModel->delete();

        $messageAdapter = $this->framework->getAdapter(Message::class);

        if (true === $isExternalFile) {
            $messageAdapter->addError($this->translator->trans('ERR.fileNotFound', [$file->path], 'contao_default'));
        } else {
            $messageAdapter->addError($this->translator->trans('ERR.uploadError', [$file->path], 'contao_default'));
        }

        $this->logger?->info(
            \sprintf('Unable to create a new image in: %s!', $file->path),
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
        );

        return false;
    }

    /**
     * @throws \Exception
     */
    public function generateSanitizedAndUniqueFilename(string $strPath): string
    {
        $dirname = \dirname($strPath);
        $filename = basename($strPath);
        $filename = $this->framework->getAdapter(StringUtil::class)->sanitizeFileName($filename);
        $strPath = $dirname.'/'.$filename;

        if (preg_match('/\.$/', $strPath)) {
            throw new \Exception($this->translator->trans('ERR.invalidName', [], 'contao_default'));
        }

        $pathInfo = pathinfo($strPath);
        $extension = $pathInfo['extension'];
        $basename = basename($strPath, '.'.$extension);
        $dirname = \dirname($strPath);

        for ($i = 1; $i < 1000; ++$i) {
            $path = Path::join($dirname, $basename.'.'.$extension);

            if (!$this->filesystem->exists(Path::join($this->projectDir, $path))) {
                // Exit loop if filename is unique
                return $path;
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
        $randomFilename = md5($basename.microtime()).'.'.$extension;

        return Path::join($dirname, $randomFilename);
    }

    /**
     * Loads images from the Contao filesystem into an album.
     *
     * @throws DoctrineDBALException
     */
    public function importFromFilesystem(GalleryCreatorAlbumsModel $albumModel, array $arrMultiSRC): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $images = [];

        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);

        if (null === ($filesModel = $filesModelAdapter->findMultipleByUuids($arrMultiSRC))) {
            return;
        }

        while ($filesModel->next()) {
            // Continue if the file has been processed or does not exist
            if (isset($images[$filesModel->path]) || !$this->filesystem->exists(Path::join($this->projectDir, $filesModel->path))) {
                continue;
            }

            // If item is a file,
            if ('file' === $filesModel->type) {
                $file = $this->framework->createInstance(File::class, [$filesModel->path]);

                if (!$this->isValidFileName($file->name)) {
                    $messageAdapter->addError($this->translator->trans('ERR.filetype', [$file->extension], 'contao_default'));
                    continue;
                }

                $images[$file->path] = ['uuid' => $filesModel->uuid, 'basename' => $file->basename, 'path' => $file->path];
            } else {
                // If resource is a directory
                $childFilesModel = $filesModelAdapter->findMultipleFilesByFolder($filesModel->path);

                if (null === $childFilesModel) {
                    continue;
                }

                while ($childFilesModel->next()) {
                    // Skip child folders
                    if ('folder' === $childFilesModel->type || !$this->filesystem->exists(Path::join($this->projectDir, $childFilesModel->path))) {
                        continue;
                    }

                    $file = $this->framework->createInstance(File::class, [$childFilesModel->path]);

                    if (!$this->isValidFileName($file->name)) {
                        continue;
                    }

                    $images[$file->path] = ['uuid' => $childFilesModel->uuid, 'basename' => $file->basename, 'path' => $file->path];
                }
            }
        }

        if (empty($images)) {
            return;
        }

        $pictures = [
            'uuids' => [],
            'paths' => [],
            'basenames' => [],
        ];

        $pictures['uuids'] = $this->connection
            ->fetchFirstColumn(
                'SELECT uuid FROM tl_gallery_creator_pictures WHERE pid = ?',
                [
                    $albumModel->id,
                ],
                [
                    Types::INTEGER,
                ],
            )
        ;

        $pictures['paths'] = $this->connection
            ->fetchFirstColumn(
                'SELECT path FROM tl_files WHERE uuid IN(?)',
                [
                    $pictures['uuids'],
                ],
                [
                    ArrayParameterType::STRING,
                ],
            )
        ;

        $pictures['basenames'] = array_map(static fn ($path) => basename($path), $pictures['paths']);

        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);

        foreach ($images as $image) {
            // Prevent duplicate entries
            if (\in_array($image['uuid'], $pictures['uuids'], false)) {
                continue;
            }

            // Prevent duplicate entries
            if (\in_array($image['basename'], $pictures['basenames'], true)) {
                continue;
            }

            $request->query->set('importFromFilesystem', 'true');

            if (!$this->galleryCreatorCopyImagesOnImport) {
                $this->addImageToAlbum($albumModel, $this->framework->createInstance(File::class, [$image['path']]));
            } else {
                $sourcePath = $image['path'];

                // Get the album upload directory
                $folderModel = $filesModelAdapter->findByUuid($albumModel->assignedDir);

                if (null === $folderModel || 'folder' !== $folderModel->type || !is_dir($folderModel->getAbsolutePath())) {
                    $errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID '.$albumModel->id.'.';

                    throw new \Exception($errMsg);
                }

                $targetPath = $this->generateSanitizedAndUniqueFilename(Path::join($folderModel->path, basename($sourcePath)));

                $file = $this->framework->createInstance(File::class, [$sourcePath]);

                if (!$this->filesystem->exists(Path::join($this->projectDir, $file->path))) {
                    throw new ResponseException(new Response('Could not find file '.$file->path.'.', 415));
                }

                // Copy the image to the upload folder
                $file->copyTo($targetPath);
                $dbafsAdapter->addResource($targetPath);

                $this->addImageToAlbum($albumModel, $this->framework->createInstance(File::class, [$targetPath]));
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

        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);

        // Check for a valid upload directory
        $objUploadDir = $filesModelAdapter->findByUuid($albumModel->assignedDir);

        if (null === $objUploadDir || !is_dir(Path::join($this->projectDir, $objUploadDir->path))) {
            $messageAdapter->addError('No upload directory defined in the album settings!');

            return [];
        }

        // Check if there are some files in $_FILES
        if (!isset($_FILES[$strName])) {
            $messageAdapter->addError('Please select one or more files to be uploaded.');

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
                    'contao_default',
                );

                $messageAdapter->addError($error);

                // Send error message to Dropzone
                throw new ResponseException(new JsonResponse($error, 415));
            }
        } elseif (isset($_FILES[$strName]['name']) && \is_array($_FILES[$strName]['name'])) {
            $intCount = \count($_FILES[$strName]['name']);

            for ($i = 0; $i < $intCount; ++$i) {
                if (!empty($_FILES[$strName]['name'][$i])) {
                    // Generate a unique filename
                    $_FILES[$strName]['name'][$i] = basename($this->generateSanitizedAndUniqueFilename(Path::join($objUploadDir->path, $_FILES[$strName]['name'][$i])));

                    if (!$this->isValidFileName($_FILES[$strName]['name'][$i])) {
                        $error = $this->translator->trans(
                            'ERR.notAllowedFilenameOrExtension',
                            [
                                $_FILES[$strName]['name'][$i],
                                implode(', ', $this->galleryCreatorValidExtensions),
                            ],
                            'contao_default',
                        );

                        // Send error message
                        throw new ResponseException(new JsonResponse($error, 415));
                    }
                }
            }
        }

        $configAdapter = $this->framework->getAdapter(Config::class);

        // Resize image if feature is enabled
        if ($request && $request->request->get('imageResolution', 0) > 1) {
            $configAdapter->set('imageWidth', $request->request->get('imageResolution'));
            $configAdapter->set('imageHeight', 999999999);
        } else {
            $configAdapter->set('maxImageWidth', 999999999);
        }

        // Call the Contao file upload service
        $objUpload = $this->framework->createInstance(FileUpload::class);
        $objUpload->setName($strName);
        $arrUpload = $objUpload->uploadTo($objUploadDir->path);

        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);

        foreach ($arrUpload as $strFileSrc) {
            $dbafsAdapter->addResource($strFileSrc);
        }

        return $arrUpload;
    }

    public function isValidFileName(string $strName): bool
    {
        $strName = strtolower($strName);

        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (!$validatorAdapter->isValidFileName($strName)) {
            return false;
        }

        $pathParts = pathinfo($strName);

        if (empty($pathParts['extension'])) {
            return false;
        }

        if (!\in_array($pathParts['extension'], $this->galleryCreatorValidExtensions, true)) {
            return false;
        }

        return true;
    }
}
