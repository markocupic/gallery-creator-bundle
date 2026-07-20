<?php

declare(strict_types=1);

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\ContentModel;
use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Dto\GalleryPictureDto;
use Markocupic\GalleryCreatorBundle\Filesystem\FilesystemItemResolver;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

readonly class PictureUtil
{
    public function __construct(
        #[Autowire('@contao.assets.files_context')]
        private ContaoContext $filesContext,
        private ContaoFramework $framework,
        private FilesystemItemResolver $filesystemItemResolver,
        private Studio $studio,
        private string $projectDir,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function getPictureData(GalleryCreatorPicturesModel $picturesModel, ContentModel $contentElementModel): GalleryPictureDto|null
    {
        $filesModel = $this->framework->getAdapter(FilesModel::class)
            ->findByUuid($picturesModel->uuid)
        ;

        if (null === $filesModel) {
            return null;
        }

        $socialMediaSrc = $picturesModel->socialMediaSRC ?: '';

        $localMediaModel = $this->framework->getAdapter(FilesModel::class)
            ->findByUuid($picturesModel->localMediaSRC)
        ;

        $customLinkHref = match (true) {
            '' !== $socialMediaSrc => $socialMediaSrc,
            null !== $localMediaModel => Path::join($this->getBasePath(), $localMediaModel->path),
            default => null,
        };

        $metadata = $this->getMetadata($picturesModel);

        $figureBuilder = $this->studio
            ->createFigureBuilder()
            ->setSize($contentElementModel->gcSizeDetailView)
            ->setLightboxGroupIdentifier('lb'.$contentElementModel->id)
            ->enableLightbox((bool) $contentElementModel->gcFullSize)
            ->setOverwriteMetadata($metadata)
            ->setMetadata($metadata)
        ;

        if ($picturesModel->addCustomThumb && $picturesModel->customThumb) {
            $figureBuilder->fromUuid($picturesModel->customThumb);
        } else {
            $figureBuilder->fromUuid($filesModel->uuid);
        }

        if ($customLinkHref) {
            $figureBuilder->setLinkHref(StringUtil::ampersand($customLinkHref));
        }

        return new GalleryPictureDto(
            framework: $this->framework,
            pictureId: $picturesModel->id,
            projectDir: $this->projectDir,
            figure: $figureBuilder->build(),
            metadata: $metadata,
            localMediaModel: $localMediaModel,
            socialMediaSrc: $socialMediaSrc,
        );
    }

    public function pictureExists(GalleryCreatorPicturesModel $picturesModel): bool
    {
        $fileSystemItem = $this->filesystemItemResolver->first($picturesModel->uuid);

        return null !== $fileSystemItem && $fileSystemItem->isFile();
    }

    private function getBasePath(): string
    {
        return $this->filesContext->getStaticUrl();
    }

    private function getMetadata(GalleryCreatorPicturesModel $picturesModel): Metadata|null
    {
        $fileSystemItem = $this->filesystemItemResolver->first($picturesModel->uuid);

        if (null === $fileSystemItem || !$fileSystemItem->isFile()) {
            return null;
        }

        $filesModel = $this->framework->getAdapter(FilesModel::class)
            ->findByUuid($picturesModel->uuid)
        ;

        if (null === $filesModel) {
            return null;
        }

        $meta = $fileSystemItem->getExtraMetadata()
            ->getLocalized()
            ?->getDefault()
        ;

        $arrMeta = null === $meta ? [] : $meta->all();

        $arrMeta['title'] = match (true) {
            '' !== $picturesModel->title => $picturesModel->title,
            '' !== $picturesModel->caption => $picturesModel->caption,
            default => $arrMeta['title'] ?? '',
        };

        $arrMeta['caption'] = match (true) {
            '' !== $picturesModel->caption => $picturesModel->caption,
            default => $arrMeta['caption'] ?? '',
        };

        return new Metadata($arrMeta);
    }
}
