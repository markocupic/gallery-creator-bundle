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
use Markocupic\GalleryCreatorBundle\Revise\ReviseAlbumDatabase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviseAlbumDatabaseTest extends ContaoTestCase
{
    public function testReturnsEarlyWithoutRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $service = $this->createService(
            $this->mockContaoFramework(),
            $connection,
            $this->createMock(Filesystem::class),
            new RequestStack(),
            $this->createMock(TranslatorInterface::class),
        );

        $service->run($this->mockAlbum());

        $this->addToAssertionCount(1);
    }

    public function testAddsErrorWhenLinkedFileIsMissing(): void
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

        $session = $this->session();
        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $this->requestStack($session), $translator);

        $service->run($this->mockAlbum(), false);

        $this->assertSame(['LINK_ERROR'], $session->get('gc_error'));
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

        $session = $this->session();
        $service = $this->createService($framework, $this->emptyConnection(), $this->createMock(Filesystem::class), $this->requestStack($session), $translator);

        $service->run($this->mockAlbum(), true);

        $errors = $session->get('gc_error');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Deleted data record with ID 7', $errors[0]);
    }

    public function testAddsErrorWhenFileVanishedFromFilesystem(): void
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

        $session = $this->session();
        $service = $this->createService($framework, $this->emptyConnection(), $filesystem, $this->requestStack($session), $translator);

        $service->run($this->mockAlbum(), false);

        $this->assertSame(['LINK_ERROR'], $session->get('gc_error'));
    }

    public function testRemovesOrphanedAlbumIdsFromContentElements(): void
    {
        // No pictures to process.
        $picturesAdapter = $this->mockAdapter(['findByPid']);
        $picturesAdapter
            ->method('findByPid')
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $picturesAdapter,
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

        $service = $this->createService($framework, $connection, $this->createMock(Filesystem::class), $this->requestStack($this->session()), $this->createMock(TranslatorInterface::class));

        $service->run($this->mockAlbum(), false);

        $this->assertSame(serialize([5]), $captured['tl_content.gcAlbumSelection']);
    }

    private function createService(ContaoFramework $framework, Connection $connection, Filesystem $filesystem, RequestStack $requestStack, TranslatorInterface $translator): ReviseAlbumDatabase
    {
        return new ReviseAlbumDatabase(
            $framework,
            $connection,
            $filesystem,
            $requestStack,
            $translator,
            '/project',
            'files/gallery_creator',
        );
    }

    private function picturesAdapter(Collection $collection): object
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
        $adapter = $this->mockAdapter(['deserialize']);
        $adapter
            ->method('deserialize')
            ->willReturn($deserializeReturn)
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

    private function mockAlbum(): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, [
            'id' => 10,
            'pid' => 0,
            'alias' => 'my-album',
            'name' => 'My Album',
        ]);
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function requestStack(Session $session): RequestStack
    {
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }
}
