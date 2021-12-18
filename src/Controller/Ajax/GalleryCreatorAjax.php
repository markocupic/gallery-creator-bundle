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

namespace Markocupic\GalleryCreatorBundle\Controller\Ajax;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
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
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SecurityUtil
     */
    private $securityUtil;

    /**
     * @var AlbumUtil
     */
    private $albumUtil;

    /**
     * @var PictureUtil
     */
    private $pictureUtil;

    public function __construct(ContaoFramework $framework, SecurityUtil $securityUtil, AlbumUtil $albumUtil, PictureUtil $pictureUtil)
    {
        $this->framework = $framework;
        $this->securityUtil = $securityUtil;
        $this->albumUtil = $albumUtil;
        $this->pictureUtil = $pictureUtil;
    }

    /**
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
            $json['status'] = 'Forbidden!';

            return new JsonResponse($json, Response::HTTP_FORBIDDEN);
        }

        // Init visit counter
        $this->albumUtil->countAlbumViews($albumModel);

        // Sorting direction
        $sorting = $contentModel->gcPictureSorting.' '.$contentModel->gcPictureSortingDirection;

        $objPicture = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE published=? AND pid=? ORDER BY '.$sorting)
            ->execute('1', $pid)
            ;

        while ($objPicture->next()) {
            if (null === ($objFile = FilesModel::findByUuid($objPicture->uuid))) {
                continue;
            }

            $href = $objFile->path;
            $href = !empty($objPicture->socialMediaSRC) ? $objPicture->socialMediaSRC : $href;
            $href = !empty($objPicture->localMediaSRC) ? $objPicture->localMediaSRC : $href;

            $arrPicture = $objPicture->row();

            $arrPicture['href'] = $href;
            $arrPicture['pid'] = $objPicture->pid;
            $arrPicture['caption'] = StringUtil::specialchars($objPicture->caption);
            $arrPicture['id'] = $objPicture->id;
            $arrPicture['uuid'] = StringUtil::binToUuid($objFile->uuid);

            $json['data'][] = $arrPicture;
        }
        $json['status'] = 'success';

        return new JsonResponse($json);
    }
}
