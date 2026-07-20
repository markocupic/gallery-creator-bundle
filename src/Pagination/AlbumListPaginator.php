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

namespace Markocupic\GalleryCreatorBundle\Pagination;

use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Input;
use Contao\Pagination;
use Markocupic\GalleryCreatorBundle\Dto\PaginationResultDto;

readonly class AlbumListPaginator
{
    public function __construct(private ContaoFramework $framework)
    {
    }

    public function paginate(array $items, int $perPage, string $paginationId): PaginationResultDto
    {
        $total = \count($items);

        // No pagination if there is only one page
        if ($perPage < 1) {
            return new PaginationResultDto($items, '', 1, $total);
        }

        $page = (int) ($this->framework->getAdapter(Input::class)->get($paginationId) ?? 1);
        $maxPage = max((int) ceil($total / $perPage), 1);

        // Do not cache the page if the page is not available
        if ($page < 1 || $page > $maxPage) {
            $uri = $this->framework->getAdapter(Environment::class)->get('uri');

            throw new PageNotFoundException('Page not found: '.$uri);
        }

        $offset = ($page - 1) * $perPage;
        $pagedItems = \array_slice($items, $offset, $perPage);

        $maxLinks = (int) $this->framework->getAdapter(Config::class)->get('maxPaginationLinks');

        /** @var Pagination $pagination */
        $pagination = $this->framework->createInstance(Pagination::class, [$total, $perPage, $maxLinks, $paginationId]);
        $markup = $pagination->generate("\n  ");

        return new PaginationResultDto($pagedItems, $markup, $page, $total);
    }
}
