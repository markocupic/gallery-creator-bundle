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

namespace Markocupic\GalleryCreatorBundle\Controller\Ajax;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GalleryCreatorAjax
{
    private ContaoFramework $framework;
    private Connection $connection;
    private SecurityUtil $securityUtil;
    private AlbumUtil $albumUtil;
    private PictureUtil $pictureUtil;

    public function __construct(ContaoFramework $framework, Connection $connection, SecurityUtil $securityUtil, AlbumUtil $albumUtil, PictureUtil $pictureUtil)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->securityUtil = $securityUtil;
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
    }

    /**
     * @throws \Exception
     *
     * @Route("/_gallery_creator/get_image/{pictureId}/{contentId}", name="GalleryCreatorAjax::class\getImage", defaults={"_scope" = "frontend"})
     */
    public function getImage(int $pictureId, int $contentId): Response
    {
        $this->framework->initialize(true);

        $arrPicture = [];
        $pictureModel = GalleryCreatorPicturesModel::findByPk($pictureId);
        $contentModel = ContentModel::findByPk($contentId);

        if (null !== $pictureModel && null !== $contentModel) {
            $arrPicture = $this->pictureUtil->getPictureData($pictureModel, $contentModel);
        }

        return new JsonResponse($arrPicture);
    }

    /**
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     *
     * @Route("/_gallery_creator/get_images_by_pid/{pid}/{contentId}", name="GalleryCreatorAjax::class\getImagesByPid", defaults={"_scope" = "frontend"})
     */
    public function getImagesByPid(int $pid, int $contentId): Response
    {
        $this->framework->initialize(true);

        // Do not send data if album is protected and the user has no access
        $albumModel = GalleryCreatorAlbumsModel::findByPk($pid);
        $contentModel = ContentModel::findByPk($contentId);
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

        // Sorting direction
        $sorting = $contentModel->gcPictureSorting.' '.$contentModel->gcPictureSortingDirection;

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ? ORDER BY '.$sorting,
            ['1', $pid],
        );

        while (false !== ($arrPicture = $stmt->fetchAssociative())) {
            if (null === ($filesModel = FilesModel::findByUuid($arrPicture['uuid']))) {
                continue;
            }

            $localMediaModel = null;

            if (!empty($arrPicture['localMediaSRC'])) {
                $localMediaModel = FilesModel::findByUuid($arrPicture['localMediaSRC']);
            }

            $href = $filesModel->path;
            $href = !empty($arrPicture['socialMediaSRC']) ? $arrPicture['socialMediaSRC'] : $href;
            $href = $localMediaModel ? $localMediaModel->path : $href;

            $arrPicture['href'] = $href;
            $arrPicture['caption'] = StringUtil::specialchars($arrPicture['caption']);
            $arrPicture['uuid'] = StringUtil::binToUuid($filesModel->uuid);

            $json['data'][] = $arrPicture;
        }
        $json['status'] = 'success';

        return new JsonResponse($json);
    }

    public function setFramework(ContaoFramework $framework): self
    {
        $this->framework = $framework;

        return $this;
    }
}
