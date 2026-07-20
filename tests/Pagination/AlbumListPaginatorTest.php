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

namespace Markocupic\GalleryCreatorBundle\Tests\Util;

use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Input;
use Contao\Pagination;
use Contao\TestCase\ContaoTestCase;
use Markocupic\GalleryCreatorBundle\Pagination\AlbumListPaginator;

class AlbumListPaginatorTest extends ContaoTestCase
{
    public function testReturnsAllItemsWhenPaginationDisabled(): void
    {
        $items = ['a', 'b', 'c'];
        $paginator = new AlbumListPaginator($this->mockContaoFramework());

        $result = $paginator->paginate($items, 0, 'page_g1');

        $this->assertSame($items, $result->items);
        $this->assertSame('', $result->markup);
        $this->assertSame(1, $result->page);
        $this->assertSame(3, $result->total);
    }

    public function testPaginatesFirstPage(): void
    {
        $paginator = new AlbumListPaginator($this->mockFramework(page: 1, pagination: $this->mockPagination('PAGER')));

        $result = $paginator->paginate(range(1, 25), 10, 'page_g1');

        $this->assertSame(range(1, 10), $result->items);
        $this->assertSame('PAGER', $result->markup);
        $this->assertSame(1, $result->page);
        $this->assertSame(25, $result->total);
    }

    public function testPaginatesSecondPage(): void
    {
        $paginator = new AlbumListPaginator($this->mockFramework(page: 2, pagination: $this->mockPagination('PAGER')));

        $result = $paginator->paginate(range(1, 25), 10, 'page_g1');

        $this->assertSame(range(11, 20), $result->items);
        $this->assertSame(2, $result->page);
    }

    public function testReturnsOnlyRemainingItemsOnLastPage(): void
    {
        $paginator = new AlbumListPaginator($this->mockFramework(page: 3, pagination: $this->mockPagination('PAGER')));

        $result = $paginator->paginate(range(1, 25), 10, 'page_g1');

        $this->assertSame(range(21, 25), $result->items);
        $this->assertCount(5, $result->items);
    }

    public function testThrowsWhenPageAboveRange(): void
    {
        $this->expectException(PageNotFoundException::class);

        $paginator = new AlbumListPaginator($this->mockFramework(page: 4, pagination: $this->mockPagination('PAGER')));
        $paginator->paginate(range(1, 25), 10, 'page_g1');
    }

    public function testThrowsWhenPageBelowOne(): void
    {
        $this->expectException(PageNotFoundException::class);

        $paginator = new AlbumListPaginator($this->mockFramework(page: 0, pagination: $this->mockPagination('PAGER')));
        $paginator->paginate(range(1, 25), 10, 'page_g1');
    }

    private function mockPagination(string $markup): Pagination
    {
        $pagination = $this->createMock(Pagination::class);
        $pagination
            ->method('generate')
            ->willReturn($markup)
        ;

        return $pagination;
    }

    private function mockFramework(int $page, Pagination $pagination): ContaoFramework
    {
        $input = $this->mockAdapter(['get']);
        $input
            ->method('get')
            ->willReturn($page)
        ;

        $environment = $this->mockAdapter(['get']);
        $environment
            ->method('get')
            ->willReturn('https://example.com/list')
        ;

        $config = $this->mockAdapter(['get']);
        $config
            ->method('get')
            ->willReturn(7)
        ;

        $framework = $this->mockContaoFramework([
            Input::class => $input,
            Environment::class => $environment,
            Config::class => $config,
        ]);

        $framework
            ->method('createInstance')
            ->willReturn($pagination)
        ;

        return $framework;
    }
}
