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

namespace Markocupic\GalleryCreatorBundle\Tests\Controller\Ajax;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Markocupic\GalleryCreatorBundle\Controller\Ajax\GalleryCreatorAjax;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\Response;

class GalleryCreatorAjaxTest extends ContaoTestCase
{
    public function testGetImageReturnsEmptyPayloadWhenModelsAreMissing(): void
    {
        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->findByIdAdapter(null),
            ContentModel::class => $this->findByIdAdapter(null),
        ]);

        $response = $this->createController($framework)->getImage(1, 2);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
    }

    public function testGetImageDelegatesToPictureUtilWhenModelsExist(): void
    {
        $pictureModel = $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, ['id' => 1]);
        $contentModel = $this->mockContent();

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->findByIdAdapter($pictureModel),
            ContentModel::class => $this->findByIdAdapter($contentModel),
        ]);

        $pictureUtil = $this->createMock(PictureUtil::class);
        $pictureUtil
            ->expects($this->once())
            ->method('getPictureData')
            ->with($pictureModel, $contentModel)
            ->willReturn(null)
        ;

        $response = $this->createController($framework, pictureUtil: $pictureUtil)->getImage(1, 2);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetImagesByPidReturnsBadRequestForUnknownModels(): void
    {
        $framework = $this->mockContaoFramework([
            GalleryCreatorAlbumsModel::class => $this->findByIdAdapter($this->mockAlbum()),
            ContentModel::class => $this->findByIdAdapter(null),
        ]);

        $response = $this->createController($framework)->getImagesByPid(5, 9);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('Bad argument!', $this->decode($response)['status']);
    }

    public function testGetImagesByPidReturnsForbiddenForProtectedAlbum(): void
    {
        $framework = $this->mockContaoFramework([
            GalleryCreatorAlbumsModel::class => $this->findByIdAdapter($this->mockAlbum()),
            ContentModel::class => $this->findByIdAdapter($this->mockContent()),
        ]);

        $securityUtil = $this->createMock(SecurityUtil::class);
        $securityUtil
            ->method('isAuthorized')
            ->willReturn(false)
        ;

        $response = $this->createController($framework, securityUtil: $securityUtil)->getImagesByPid(5, 9);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame('forbidden', $this->decode($response)['status']);
    }

    public function testGetImagesByPidReturnsPreparedPictures(): void
    {
        $filesModel = $this->mockClassWithProperties(FilesModel::class, [
            'path' => 'files/pic.jpg',
            'uuid' => 'bin-uuid',
        ]);

        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn($filesModel)
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorAlbumsModel::class => $this->findByIdAdapter($this->mockAlbum()),
            ContentModel::class => $this->findByIdAdapter($this->mockContent()),
            FilesModel::class => $filesAdapter,
            StringUtil::class => $this->stringUtilAdapter(),
        ]);

        $securityUtil = $this->createMock(SecurityUtil::class);
        $securityUtil
            ->method('isAuthorized')
            ->willReturn(true)
        ;

        $connection = $this->mockConnection([
            ['uuid' => 'bin-uuid', 'caption' => 'Hello', 'localMediaSRC' => '', 'socialMediaSRC' => ''],
        ]);

        $response = $this->createController(
            $framework,
            connection: $connection,
            securityUtil: $securityUtil,
        )->getImagesByPid(5, 9);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $json = $this->decode($response);
        $this->assertSame('success', $json['status']);
        $this->assertCount(1, $json['data']);
        $this->assertSame('files/pic.jpg', $json['data'][0]['href']);
        $this->assertSame('Hello', $json['data'][0]['caption']);
        $this->assertSame('converted-uuid', $json['data'][0]['uuid']);
    }

    private function createController(ContaoFramework $framework, Connection|null $connection = null, SecurityUtil|null $securityUtil = null, AlbumUtil|null $albumUtil = null, PictureUtil|null $pictureUtil = null): GalleryCreatorAjax
    {
        return new GalleryCreatorAjax(
            $framework,
            $connection ?? $this->createMock(Connection::class),
            $securityUtil ?? $this->createMock(SecurityUtil::class),
            $albumUtil ?? $this->createMock(AlbumUtil::class),
            $pictureUtil ?? $this->createMock(PictureUtil::class),
        );
    }

    private function findByIdAdapter(object|null $return): object
    {
        $adapter = $this->mockAdapter(['findById']);
        $adapter
            ->method('findById')
            ->willReturn($return)
        ;

        return $adapter;
    }

    private function stringUtilAdapter(): object
    {
        $adapter = $this->mockAdapter(['specialchars', 'binToUuid']);
        $adapter
            ->method('specialchars')
            ->willReturnArgument(0)
        ;

        $adapter
            ->method('binToUuid')
            ->willReturn('converted-uuid')
        ;

        return $adapter;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function mockConnection(array $rows): Connection
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn(['id' => true, 'sorting' => true])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn($rows)
        ;

        return $connection;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function mockAlbum(array $props = []): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, array_merge(['id' => 5], $props));
    }

    private function mockContent(): ContentModel
    {
        return $this->mockClassWithProperties(ContentModel::class, [
            'gcPictureSorting' => 'id',
            'gcPictureSortingDirection' => 'ASC',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
