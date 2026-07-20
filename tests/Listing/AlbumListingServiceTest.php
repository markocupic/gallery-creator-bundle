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

namespace Markocupic\GalleryCreatorBundle\Tests\Listing;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Markocupic\GalleryCreatorBundle\Listing\AlbumListingService;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

class AlbumListingServiceTest extends ContaoTestCase
{
    public function testIsInSelectionReturnsTrueWhenSelectionDisabled(): void
    {
        $service = new AlbumListingService($this->createMock(Connection::class), $this->mockFramework());

        $model = $this->mockContentModel(['gcShowAlbumSelection' => false]);

        $this->assertTrue($service->isInSelection($model, $this->mockAlbum(5)));
    }

    public function testIsInSelectionReturnsTrueWhenAlbumInSelection(): void
    {
        $service = new AlbumListingService($this->createMock(Connection::class), $this->mockFramework(selection: [5, 6, 7]));

        $model = $this->mockContentModel(['gcShowAlbumSelection' => true, 'gcAlbumSelection' => 'serialized']);

        $this->assertTrue($service->isInSelection($model, $this->mockAlbum(6)));
    }

    public function testIsInSelectionReturnsFalseWhenAlbumNotInSelection(): void
    {
        $service = new AlbumListingService($this->createMock(Connection::class), $this->mockFramework(selection: [5, 6, 7]));

        $model = $this->mockContentModel(['gcShowAlbumSelection' => true, 'gcAlbumSelection' => 'serialized']);

        $this->assertFalse($service->isInSelection($model, $this->mockAlbum(99)));
    }

    public function testGetVisibleAlbumIdsFiltersUnauthorizedAndCastsToInt(): void
    {
        $albums = [1 => $this->mockAlbum(1), 2 => $this->mockAlbum(2), 3 => $this->mockAlbum(3)];

        // The database returns the ids as strings.
        $service = new AlbumListingService($this->mockConnection(['1', '2', '3']), $this->mockFramework(albums: $albums));

        $model = $this->mockContentModel(['gcShowAlbumSelection' => false]);

        // Only albums 1 and 3 are authorized.
        $isAuthorized = static fn (GalleryCreatorAlbumsModel $album): bool => \in_array($album->id, [1, 3], true);

        $this->assertSame([1, 3], $service->getVisibleAlbumIds($model, 0, $isAuthorized));
    }

    public function testGetVisibleAlbumIdsSkipsMissingAlbums(): void
    {
        // findById() returns null for id 2.
        $albums = [1 => $this->mockAlbum(1), 3 => $this->mockAlbum(3)];

        $service = new AlbumListingService($this->mockConnection(['1', '2', '3']), $this->mockFramework(albums: $albums));

        $model = $this->mockContentModel(['gcShowAlbumSelection' => false]);

        $this->assertSame([1, 3], $service->getVisibleAlbumIds($model, 0, static fn () => true));
    }

    public function testFallsBackToDateSortForUnknownColumn(): void
    {
        $capturedSql = null;
        $service = new AlbumListingService($this->mockConnection([], $capturedSql), $this->mockFramework());

        $model = $this->mockContentModel(['gcSorting' => 'evil; DROP TABLE', 'gcSortingDirection' => 'ASC']);

        $service->getVisibleAlbumIds($model, 0, static fn () => true);

        $this->assertStringContainsString('ORDER BY date ASC', $capturedSql);
    }

    public function testUsesWhitelistedSortColumnAndDirection(): void
    {
        $capturedSql = null;
        $service = new AlbumListingService($this->mockConnection([], $capturedSql), $this->mockFramework());

        $model = $this->mockContentModel(['gcSorting' => 'name', 'gcSortingDirection' => 'DESC']);

        $service->getVisibleAlbumIds($model, 0, static fn () => true);

        $this->assertStringContainsString('ORDER BY name DESC', $capturedSql);
    }

    /**
     * @param array<int, GalleryCreatorAlbumsModel> $albums    id => album model returned by findById()
     * @param array<int, int>                       $selection value returned by StringUtil::deserialize()
     */
    private function mockFramework(array $albums = [], array $selection = []): ContaoFramework
    {
        $albumsAdapter = $this->mockAdapter(['findById']);
        $albumsAdapter
            ->method('findById')
            ->willReturnCallback(
                static fn ($id) => $albums[(int) $id] ?? null,
            )
        ;

        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturn($selection)
        ;

        return $this->mockContaoFramework([
            GalleryCreatorAlbumsModel::class => $albumsAdapter,
            StringUtil::class => $stringUtil,
        ]);
    }

    /**
     * @param array<int, string> $ids ids returned by fetchFirstColumn()
     */
    private function mockConnection(array $ids, string|null &$capturedSql = null): Connection
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'id' => true,
                'pid' => true,
                'date' => true,
                'name' => true,
                'sorting' => true,
            ])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;
        $connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(
                static function (string $sql) use ($ids, &$capturedSql): array {
                    $capturedSql = $sql;

                    return $ids;
                },
            )
        ;

        return $connection;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function mockContentModel(array $props = []): ContentModel
    {
        return $this->mockClassWithProperties(ContentModel::class, array_merge([
            'gcShowAlbumSelection' => false,
            'gcAlbumSelection' => '',
            'gcSorting' => 'date',
            'gcSortingDirection' => 'ASC',
        ], $props));
    }

    private function mockAlbum(int $id): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, ['id' => $id]);
    }
}
