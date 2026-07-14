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

final readonly class DependencyAggregate
{
    public function __construct(
        public AlbumUtil $albumUtil,
        public Connection $connection,
        public HtmlDecoder $htmlDecoder,
        public InsertTagParser $insertTagParser,
        public MarkdownUtil $markdownUtil,
        public PictureUtil $pictureUtil,
        public RequestStack $requestStack,
        public ResponseContextAccessor $responseContextAccessor,
        public ScopeMatcher $scopeMatcher,
        public SecurityUtil $securityUtil,
        public Studio $studio,
        public string $projectDir,
    ) {
    }
}
