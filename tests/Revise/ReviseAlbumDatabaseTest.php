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

namespace Markocupic\GalleryCreatorBundle\Tests\Revise;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Revise\Exception\ReviseAlbumException;
use Markocupic\GalleryCreatorBundle\Revise\ReviseAlbumDatabase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviseAlbumDatabaseTest extends ContaoTestCase
{
    public function testCreatesUploadDirectory(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('mkdir')
            ->with(Path::makeAbsolute('files/gallery_creator', '/project'))
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter(null),
            FilesModel::class => $this->mockAdapter(['findByUuid']),
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        $service = $this->createService($framework, $this->emptyConnection(), $filesystem, $this->createMock(TranslatorInterface::class));

        $service->run($this->mockAlbum());

        $this->addToAssertionCount(1);
    }

    public function testResetsInvalidParentAlbum(): void
    {
        $album = $this->mockAlbum(['pid' => 5]);
        $album
            ->method('getRelated')
            ->with('pid')
            ->willReturn(null)
        ;

        $album
            ->expects($this->once())
            ->method('save')
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter(null),
            FilesModel::class => $this->mockAdapter(['findByUuid']),
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $this->createMock(TranslatorInterface::class));

        $service->run($album);

        $this->assertNull($album->pid);
    }

    public function testThrowsWhenLinkedFileIsMissing(): void
    {
        $collection = new Collection([$this->mockPicture(7, 'missing-uuid')], GalleryCreatorPicturesModel::getTable());

        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter($collection),
            FilesModel::class => $filesAdapter,
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('LINK_ERROR')
        ;

        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $translator);

        try {
            $service->run($this->mockAlbum(), false);
            $this->fail(ReviseAlbumException::class.' was not thrown.');
        } catch (ReviseAlbumException $e) {
            $this->assertSame(['LINK_ERROR'], json_decode($e->getMessage(), true));
        }
    }

    public function testDeletesRecordWhenCleaningDb(): void
    {
        $picture = $this->mockPicture(7, 'missing-uuid');
        $picture
            ->expects($this->once())
            ->method('delete')
        ;

        $collection = new Collection([$picture], GalleryCreatorPicturesModel::getTable());

        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter($collection),
            FilesModel::class => $filesAdapter,
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans')
        ;

        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $translator);

        try {
            $service->run($this->mockAlbum(), true);
            $this->fail(ReviseAlbumException::class.' was not thrown.');
        } catch (ReviseAlbumException $e) {
            $errors = json_decode($e->getMessage(), true);
            $this->assertCount(1, $errors);
            $this->assertStringContainsString('Deleted data record with ID 7', $errors[0]);
        }
    }

    public function testThrowsWhenFileVanishedFromFilesystem(): void
    {
        $collection = new Collection([$this->mockPicture(7, 'present-uuid')], GalleryCreatorPicturesModel::getTable());

        $filesModel = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/gone.jpg']);
        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn($filesModel)
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter($collection),
            FilesModel::class => $filesAdapter,
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        // The database record exists, but the file is gone from disk.
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(false)
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('LINK_ERROR')
        ;

        $service = $this->createService($framework, $this->emptyConnection(), $filesystem, $translator);

        $this->expectException(ReviseAlbumException::class);
        $this->expectExceptionMessage(json_encode(['LINK_ERROR']));

        $service->run($this->mockAlbum(), false);
    }

    public function testRemovesOrphanedAlbumIdsFromContentElements(): void
    {
        // No pictures to process.
        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter(null),
            FilesModel::class => $this->mockAdapter(['findByUuid']),
            StringUtil::class => $this->stringUtilAdapter([5, 99]),
        ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 3, 'gcAlbumSelection' => 'serialized'],
            ])
        ;

        // Album 5 still exists, album 99 does not.
        $connection
            ->method('fetchOne')
            ->willReturnCallback(
                static fn (string $sql, array $params) => 5 === (int) $params[0] ? '5' : false,
            )
        ;

        $captured = null;
        $connection
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$captured): int {
                    $captured = $data;

                    return 1;
                },
            )
        ;

        $service = $this->createService($framework, $connection, $this->createMock(Filesystem::class), $this->createMock(TranslatorInterface::class));

        $service->run($this->mockAlbum(), false);

        $this->assertSame(serialize([5]), $captured['tl_content.gcAlbumSelection']);
    }

    public function testDoesNotThrowWhenEverythingIsValid(): void
    {
        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $this->picturesAdapter(null),
            FilesModel::class => $this->mockAdapter(['findByUuid']),
            StringUtil::class => $this->stringUtilAdapter([]),
        ]);

        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $this->createMock(TranslatorInterface::class));

        $service->run($this->mockAlbum());

        $this->addToAssertionCount(1);
    }

    private function createService(ContaoFramework $framework, Connection $connection, Filesystem $filesystem, TranslatorInterface $translator): ReviseAlbumDatabase
    {
        return new ReviseAlbumDatabase(
            $framework,
            $connection,
            $filesystem,
            $translator,
            '/project',
            'files/gallery_creator',
        );
    }

    private function picturesAdapter(Collection|null $collection): object
    {
        $adapter = $this->mockAdapter(['findByPid']);
        $adapter
            ->method('findByPid')
            ->willReturn($collection)
        ;

        return $adapter;
    }

    /**
     * @param array<int, int> $deserializeReturn
     */
    private function stringUtilAdapter(array $deserializeReturn): object
    {
        $adapter = $this->mockAdapter(['deserialize', 'binToUuid']);
        $adapter
            ->method('deserialize')
            ->willReturn($deserializeReturn)
        ;

        $adapter
            ->method('binToUuid')
            ->willReturn('uuid-string')
        ;

        return $adapter;
    }

    private function emptyConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        return $connection;
    }

    private function mockPicture(int $id, string $uuid): GalleryCreatorPicturesModel
    {
        return $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, [
            'id' => $id,
            'uuid' => $uuid,
        ]);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockAlbum(array $properties = []): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, [
            'id' => 10,
            'pid' => 0,
            'alias' => 'my-album',
            'name' => 'My Album',
            ...$properties,
        ]);
    }
}
