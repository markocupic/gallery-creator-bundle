<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\String\HtmlDecoder;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\RequestStack;

final class DependencyAggregate
{
    public function __construct(
        public readonly AlbumUtil $albumUtil,
        public readonly Connection $connection,
        public readonly HtmlDecoder $htmlDecoder,
        public readonly InsertTagParser $insertTagParser,
        public readonly MarkdownUtil $markdownUtil,
        public readonly PictureUtil $pictureUtil,
        public readonly RequestStack $requestStack,
        public readonly ResponseContextAccessor $responseContextAccessor,
        public readonly ScopeMatcher $scopeMatcher,
        public readonly SecurityUtil $securityUtil,
        public readonly Studio $studio,
        public readonly string $projectDir,
    ) {
    }
}
