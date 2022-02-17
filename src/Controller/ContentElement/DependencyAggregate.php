<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;

final class DependencyAggregate
{
    public AlbumUtil $albumUtil;
    public Connection $connection;
    public MarkdownUtil $markdownUtil;
    public PictureUtil $pictureUtil;
    public SecurityUtil $securityUtil;
    public ScopeMatcher $scopeMatcher;
    public ResponseContextAccessor $responseContextAccessor;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, MarkdownUtil $markdownUtil, PictureUtil $pictureUtil, SecurityUtil $securityUtil, ScopeMatcher $scopeMatcher, ResponseContextAccessor $responseContextAccessor)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->markdownUtil = $markdownUtil;
        $this->pictureUtil = $pictureUtil;
        $this->securityUtil = $securityUtil;
        $this->scopeMatcher = $scopeMatcher;
        $this->responseContextAccessor = $responseContextAccessor;
    }
}
