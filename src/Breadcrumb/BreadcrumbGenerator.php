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

namespace Markocupic\GalleryCreatorBundle\Breadcrumb;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\FrontendTemplate;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

class BreadcrumbGenerator
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly HtmlDecoder $htmlDecoder,
    ) {
    }

    /**
     * @param callable(GalleryCreatorAlbumsModel):bool $isVisible
     */
    public function generate(GalleryCreatorAlbumsModel|null $activeAlbum, PageModel $pageModel, callable $isVisible): string
    {
        return $this->render($this->buildItems($activeAlbum, $pageModel, $isVisible));
    }

    /**
     * @param callable(GalleryCreatorAlbumsModel):bool $isVisible
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildItems(GalleryCreatorAlbumsModel|null $activeAlbum, PageModel $pageModel, callable $isVisible): array
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $albumsAdapter = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);

        $items = [];
        $album = $activeAlbum;

        while (null !== $album) {
            if (!$isVisible($album)) {
                break;
            }

            $item = [
                'class' => 'gc-breadcrumb-item',
                'link' => $this->htmlDecoder->inputEncodedToPlainText($album->name),
                'name' => $this->htmlDecoder->inputEncodedToPlainText($album->name),
            ];

            if ($album->id === $activeAlbum->id) {
                $item['isActive'] = true;
            } else {
                $item['title'] = $stringUtil->specialchars($album->name);
                $item['href'] = $stringUtil->ampersand($pageModel->getFrontendUrl('/'.$album->alias));
            }

            $items[] = $item;

            $album = $albumsAdapter->getParentAlbum($album);
        }

        // Root
        $root = [
            'class' => 'gc-breadcrumb-item gc-breadcrumb-root-item',
            'name' => $this->htmlDecoder->inputEncodedToPlainText($pageModel->title),
            'link' => $this->htmlDecoder->inputEncodedToPlainText($pageModel->title),
            'title' => null,
            'href' => null,
            'isActive' => false,
        ];

        if (null !== $activeAlbum) {
            $root['title'] = $stringUtil->specialchars($pageModel->title);
            $root['href'] = $stringUtil->ampersand($pageModel->getFrontendUrl());
        } else {
            $root['isActive'] = true;
        }

        $items[] = $root;

        return array_reverse($items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function render(array $items): string
    {
        $htmlDecoder = $this->htmlDecoder;

        /** @var FrontendTemplate $template */
        $template = $this->framework->createInstance(FrontendTemplate::class, ['mod_breadcrumb']);

        $template->getSchemaOrgData = static function () use ($items, $htmlDecoder): array {
            $jsonLd = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [],
            ];

            $position = 0;

            foreach ($items as $item) {
                $jsonLd['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => ++$position,
                    'item' => [
                        '@id' => $item['href'] ?? './',
                        'name' => $htmlDecoder->inputEncodedToPlainText($item['link']),
                    ],
                ];
            }

            return $jsonLd;
        };

        $template->items = $items;
        $template->cssID = [];
        $template->class = '';
        $template->headline = '';

        return $template->getResponse()->getContent();
    }
}
