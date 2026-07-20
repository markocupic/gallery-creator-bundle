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

namespace Markocupic\GalleryCreatorBundle\Dto;

use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\FilesModel;
use Contao\UserModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\Filesystem\Path;

readonly class GalleryPictureDto
{
    public function __construct(
        private ContaoFramework $framework,
        private int $pictureId,
        private string $projectDir,
        public Figure $figure,
        public Metadata $metadata,
        public FilesModel|null $localMediaModel,
        public string|null $socialMediaSrc,
    ) {
    }

    public function getFilesData(): array|null
    {
        $picturesData = $this->getPicturesData();

        $uuid = $picturesData['uuid'] ?? null;

        if (null === $uuid) {
            return null;
        }

        $adapter = $this->framework->getAdapter(FilesModel::class);

        return $adapter->findByUuid($uuid)?->row();
    }

    public function getPicturesData(): array|null
    {
        $adapter = $this->framework->getAdapter(GalleryCreatorPicturesModel::class);

        return $adapter->findById($this->pictureId)?->row();
    }

    public function getAlbumData(): array|null
    {
        $adapter = $this->framework->getAdapter(GalleryCreatorPicturesModel::class);

        return $adapter->findById($this->pictureId)?->getRelated('pid')?->row();
    }

    public function getOwnerData(): array|null
    {
        $picturesData = $this->getPicturesData();

        if (null === $picturesData) {
            return null;
        }

        $picturesModel = $this->framework->getAdapter(GalleryCreatorPicturesModel::class)->findById($picturesData['id']);

        if (null === $picturesModel || !$picturesModel->cuser) {
            return null;
        }

        $userAdapter = $this->framework->getAdapter(UserModel::class);

        return $userAdapter->findById($picturesModel->cuser)?->row();
    }

    public function getExif(): array|null
    {
        $filesData = $this->getFilesData();

        $relPath = $filesData['path'] ?? null;

        if (null === $relPath) {
            return null;
        }

        $absPath = Path::makeAbsolute($relPath, $this->projectDir);

        try {
            $exif = \is_callable('exif_read_data') ? exif_read_data($absPath) : ['info' => "The function 'exif_read_data()' is not available on this server."];
        } catch (\Exception $e) {
            $exif = ['info' => "The function 'exif_read_data()' is not available on this server."];
        }

        return $exif;
    }
}
