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

namespace Markocupic\GalleryCreatorBundle\Tests\Breadcrumb;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\GalleryCreatorBundle\Breadcrumb\BreadcrumbGenerator;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

class BreadcrumbGeneratorTest extends ContaoTestCase
{
    public function testRootOnlyWhenNoActiveAlbum(): void
    {
        $items = $this->generator([])->buildItems(null, $this->mockPageModel('Startseite'), static fn () => true);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('root', $items[0]['class']);
        $this->assertTrue($items[0]['isActive']);
        $this->assertSame('Startseite', $items[0]['name']);
        $this->assertNull($items[0]['href']);
    }

    public function testActiveAlbumWithoutParent(): void
    {
        $active = $this->mockAlbum(1, 'Album A', 'album-a');

        $items = $this->generator([])->buildItems($active, $this->mockPageModel('Home'), static fn () => true);

        $this->assertCount(2, $items);
        $this->assertStringContainsString('root', $items[0]['class']);
        $this->assertFalse($items[0]['isActive']);
        $this->assertSame('https://example.com', $items[0]['href']);

        $this->assertSame('Album A', $items[1]['name']);
        $this->assertTrue($items[1]['isActive']);
        $this->assertArrayNotHasKey('href', $items[1]);
    }

    public function testParentChainIsOrderedRootFirst(): void
    {
        $parent = $this->mockAlbum(1, 'Parent', 'parent');
        $active = $this->mockAlbum(2, 'Child', 'child');

        $items = $this->generator([2 => $parent])->buildItems($active, $this->mockPageModel('Home'), static fn () => true);

        $this->assertCount(3, $items);
        $this->assertStringContainsString('root', $items[0]['class']);
        $this->assertSame('Parent', $items[1]['name']);
        $this->assertSame('https://example.com/parent', $items[1]['href']);
        $this->assertSame('Child', $items[2]['name']);
        $this->assertTrue($items[2]['isActive']);
    }

    public function testStopsWhenAlbumNotVisible(): void
    {
        $active = $this->mockAlbum(1, 'Hidden', 'hidden');

        // Prädikat false = weder in Selektion noch autorisiert
        $items = $this->generator([])->buildItems($active, $this->mockPageModel('Home'), static fn () => false);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('root', $items[0]['class']);
    }

    /**
     * @param array<int, GalleryCreatorAlbumsModel> $parents id => Elternalbum
     */
    private function generator(array $parents): BreadcrumbGenerator
    {
        return new BreadcrumbGenerator($this->mockFramework($parents), $this->mockHtmlDecoder());
    }

    /**
     * @param array<int, GalleryCreatorAlbumsModel> $parents
     */
    private function mockFramework(array $parents): ContaoFramework
    {
        $stringUtil = $this->mockAdapter(['specialchars', 'ampersand']);
        $stringUtil
            ->method('specialchars')
            ->willReturnArgument(0)
        ;

        $stringUtil
            ->method('ampersand')
            ->willReturnArgument(0)
        ;

        $albumsAdapter = $this->mockAdapter(['getParentAlbum']);
        $albumsAdapter
            ->method('getParentAlbum')
            ->willReturnCallback(
                static fn ($album) => $parents[$album->id] ?? null,
            )
        ;

        return $this->mockContaoFramework([
            StringUtil::class => $stringUtil,
            GalleryCreatorAlbumsModel::class => $albumsAdapter,
        ]);
    }

    private function mockHtmlDecoder(): HtmlDecoder
    {
        $decoder = $this->createMock(HtmlDecoder::class);
        $decoder
            ->method('inputEncodedToPlainText')
            ->willReturnArgument(0)
        ;

        return $decoder;
    }

    private function mockPageModel(string $title): PageModel
    {
        $pageModel = $this->mockClassWithProperties(PageModel::class, ['title' => $title]);
        $pageModel
            ->method('getFrontendUrl')
            ->willReturnCallback(
                static fn ($params = null) => 'https://example.com'.($params ?? ''),
            )
        ;

        return $pageModel;
    }

    private function mockAlbum(int $id, string $name, string $alias): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, [
            'id' => $id,
            'name' => $name,
            'alias' => $alias,
        ]);
    }
}
