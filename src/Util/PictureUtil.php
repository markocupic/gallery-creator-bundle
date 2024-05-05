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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\ContentModel;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Filesystem\FilesystemUtil;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\File;
use Contao\FilesModel;
use Contao\System;
use Contao\UserModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class PictureUtil
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Studio $studio,
        private readonly VirtualFilesystem $filesStorage,
        private readonly bool $galleryCreatorReadExifMetaData,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getPictureData(GalleryCreatorPicturesModel $pictureModel, ContentModel $contentElementModel): array|null
    {
        $staticUrl = System::getContainer()->get('contao.assets.files_context')->getStaticUrl();

        $request = $this->requestStack->getCurrentRequest();

        $filesystemIterator = FilesystemUtil::listContentsFromSerialized($this->filesStorage, $pictureModel->uuid);
        $fileSystemItem = $filesystemIterator->first();

        if (null === $fileSystemItem || !$fileSystemItem->isFile()) {
            return null;
        }

        if (null === ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return null;
        }

        // Get file meta data
        $arrMeta = $this->filesStorage->getExtraMetadata($fileSystemItem->getUuid());

        if (!isset($arrMeta['title'])) {
            $arrMeta['title'] = '';
        }

        // Override file meta title with the picture caption
        $arrMeta['title'] = $pictureModel->caption ?? $arrMeta['title'];

        $customHref = '';
        $localMediaSrc = null;
        $socialMediaSrc = null;

        if ($request && $this->scopeMatcher->isFrontendRequest($request)) {
            // e.g. youtube or vimeo
            $customHref = $pictureModel->socialMediaSRC ?: null;
            $socialMediaSrc = $pictureModel->socialMediaSRC ?: null;

            // Local media
            if (null !== ($objMovieFile = FilesModel::findByUuid($pictureModel->localMediaSRC))) {
                $customHref = $objMovieFile->path;
                $localMediaSrc = $staticUrl.$objMovieFile->path;
            }

            $customHref = $customHref ? $staticUrl.$customHref : null;
        }

        $ownerModel = UserModel::findByPk($pictureModel->cuser);

        // Compile list of images
        $figure = $this->studio
            ->createFigureBuilder()
            ->setSize($contentElementModel->gcSizeDetailView)
            ->setLightboxGroupIdentifier('lb'.$contentElementModel->id)
            ->enableLightbox((bool) $contentElementModel->gcFullSize)
            ->setOverwriteMetadata(new Metadata($arrMeta))
            ->fromUuid($filesModel->uuid)
            ->setMetadata(new Metadata($arrMeta))
        ;

        if ($customHref) {
            $figure->setLinkHref($customHref);
        }

        // Build the array
        return [
            'row_owner' => $ownerModel ? $ownerModel->row() : [],
            'row_files' => $filesModel->row(),
            'row_picture' => $pictureModel->row(),
            'row_album' => $pictureModel->getRelated('pid')->row(),
            'local_media_src' => $localMediaSrc,
            'social_media_src' => $socialMediaSrc,
            'exif_data' => $this->galleryCreatorReadExifMetaData ? $this->getExif(new File($filesModel->path)) : [],
            'figure' => [
                'build' => $figure->build(),
                'uuid' => $filesModel->uuid,
                'size' => $contentElementModel->gcSizeDetailView,
                'enable_lightbox' => (bool) $contentElementModel->gcFullSize,
                'meta_data' => new Metadata($arrMeta),
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function getExif(File $file): array
    {
        // Exif
        try {
            $exif = \is_callable('exif_read_data') ? exif_read_data($this->projectDir.'/'.$file->path) : ['info' => "The function 'exif_read_data()' is not available on this server."];
        } catch (\Exception $e) {
            $exif = ['info' => "The function 'exif_read_data()' is not available on this server."];
        }

        return $exif;
    }

    public function pictureExists(GalleryCreatorPicturesModel $picturesModel): bool
    {
        $filesystemIterator = FilesystemUtil::listContentsFromSerialized($this->filesStorage, $picturesModel->uuid);
        $fileSystemItem = $filesystemIterator->first();

        if (null === $fileSystemItem || !$fileSystemItem->isFile()) {
            return false;
        }

        return true;
    }
}
