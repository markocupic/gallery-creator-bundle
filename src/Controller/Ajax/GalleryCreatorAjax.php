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

namespace Markocupic\GalleryCreatorBundle\Controller\Ajax;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

readonly class GalleryCreatorAjax
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private SecurityUtil $securityUtil,
        private AlbumUtil $albumUtil,
        private PictureUtil $pictureUtil,
    ) {
    }

    #[Route('/_gallery_creator/get_image/{pictureId}/{contentId}', name: self::class.'\getImage', defaults: ['scope' => 'frontend'])]
    public function getImage(int $pictureId, int $contentId): Response
    {
        $this->framework->initialize();

        $arrPicture = [];
        $pictureModel = $this->framework->getAdapter(GalleryCreatorPicturesModel::class)->findById($pictureId);
        $contentModel = $this->framework->getAdapter(ContentModel::class)->findById($contentId);

        if (null !== $pictureModel && null !== $contentModel) {
            $arrPicture = $this->pictureUtil->getPictureData($pictureModel, $contentModel);
        }

        return new JsonResponse($arrPicture);
    }

    #[Route('/_gallery_creator/get_images_by_pid/{pid}/{contentId}', name: self::class.'\getImagesByPid', defaults: ['scope' => 'frontend'])]
    public function getImagesByPid(int $pid, int $contentId): Response
    {
        $this->framework->initialize();

        // Do not send data if album is protected and the user has no access
        $albumModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findById($pid);
        $contentModel = $this->framework->getAdapter(ContentModel::class)->findById($contentId);
        $json = [
            'data' => [],
            'status' => '',
        ];

        if (null === $contentModel || null === $albumModel) {
            $json['status'] = 'Bad argument!';

            return new JsonResponse($json, Response::HTTP_BAD_REQUEST);
        }

        if (!$this->securityUtil->isAuthorized($albumModel)) {
            $json['status'] = 'forbidden';

            return new JsonResponse($json, Response::HTTP_FORBIDDEN);
        }

        // Init visit counter
        $this->albumUtil->countAlbumViews($albumModel);

        // Prevent SQL injection
        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns(GalleryCreatorPicturesModel::getTable());
        $sortColumn = \array_key_exists(strtolower($contentModel->gcPictureSorting), $columns) ? $contentModel->gcPictureSorting : 'id';
        $sortDirection = 'ASC' === $contentModel->gcPictureSortingDirection ? 'ASC' : 'DESC';
        $strSorting = $sortColumn.' '.$sortDirection;

        $pictures = $this->connection->fetchAllAssociative(
            "SELECT * FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ? ORDER BY $strSorting",
            [
                1,
                $pid,
            ],
        );

        $filesAdapter = $this->framework->getAdapter(FilesModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        foreach ($pictures as $picture) {
            if (null === ($filesModel = $filesAdapter->findByUuid($picture['uuid']))) {
                continue;
            }

            $localMediaModel = null;

            if (!empty($picture['localMediaSRC'])) {
                $localMediaModel = $filesAdapter->findByUuid($picture['localMediaSRC']);
            }

            $href = $filesModel->path;
            $href = !empty($picture['socialMediaSRC']) ? $picture['socialMediaSRC'] : $href;
            $href = $localMediaModel ? $localMediaModel->path : $href;

            $picture['href'] = $href;
            $picture['caption'] = $stringUtilAdapter->specialchars($picture['caption']);
            $picture['uuid'] = $stringUtilAdapter->binToUuid($filesModel->uuid);

            $json['data'][] = $picture;
        }
        $json['status'] = 'success';

        return new JsonResponse($json);
    }
}
