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

use Contao\BackendUser;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Revise\Exception\ReviseAlbumException;
use Markocupic\GalleryCreatorBundle\Revise\ReviseAlbumDatabase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class ReviseTableAjax
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private ReviseAlbumDatabase $reviseAlbumDatabase,
        private Security $security,
    ) {
    }

    #[Route(
        '/_gallery_creator/revise_table/get_album_ids',
        name: self::class.'\get_album_ids',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function getAlbumIds(): JsonResponse
    {
        $this->framework->initialize();

        $user = $this->security->getUser();

        // Restrict the route to backend administrators
        if (!$user instanceof BackendUser || !$user->admin) {
            throw new AccessDeniedException('Access denied!');
        }

        $arrIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_gallery_creator_albums ORDER BY id');

        return new JsonResponse(['ids' => $arrIds]);
    }

    #[Route(
        '/_gallery_creator/revise_table/check_album/{albumId}',
        name: self::class.'\check_album',
        requirements: ['albumId' => '\d+'],
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function checkAlbumTable(int $albumId): JsonResponse
    {
        $this->framework->initialize();

        $user = $this->security->getUser();

        // Restrict the route to backend administrators
        if (!$user instanceof BackendUser || !$user->admin) {
            throw new AccessDeniedException('Access denied!');
        }

        $albumsModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findById($albumId);

        if (null === $albumsModel) {
            throw new ResponseException(new JsonResponse(['errors' => "Invalid album ID $albumId detected."]));
        }

        try {
            $this->reviseAlbumDatabase->run($albumsModel, false);
        } catch (ReviseAlbumException $e) {
            throw new ResponseException(new JsonResponse(['errors' => json_decode($e->getMessage())]));
        }

        throw new ResponseException(new JsonResponse(['success' => "Album ID $albumId has no errors."]));
    }

    #[Route(
        '/_gallery_creator/revise_table/revise_album/{albumId}',
        name: self::class.'\revise_album',
        requirements: ['albumId' => '\d+'],
        defaults: ['_scope' => 'backend', '_token_check' => true],
        methods: ['POST'],
    )]
    public function reviseAlbum(int $albumId): JsonResponse
    {
        $this->framework->initialize();

        $user = $this->security->getUser();

        // Restrict the route to backend administrators
        if (!$user instanceof BackendUser || !$user->admin) {
            throw new AccessDeniedException('Access denied!');
        }

        $albumsModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findById($albumId);

        if (null === $albumsModel) {
            throw new ResponseException(new JsonResponse(['errors' => "Invalid album ID $albumId detected."]));
        }

        try {
            $this->reviseAlbumDatabase->run($albumsModel, true);
        } catch (ReviseAlbumException $e) {
            throw new ResponseException(new JsonResponse(['errors' => json_decode($e->getMessage())]));
        }

        throw new ResponseException(new JsonResponse(['success' => "Album ID $albumId has no errors."]));
    }
}
