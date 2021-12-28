<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;

final class DependencyAggregate
{
    public AlbumUtil $albumUtil;
    public Connection $connection;
    public PictureUtil $pictureUtil;
    public SecurityUtil $securityUtil;
    public ScopeMatcher $scopeMatcher;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, PictureUtil $pictureUtil, SecurityUtil $securityUtil, ScopeMatcher $scopeMatcher)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->pictureUtil = $pictureUtil;
        $this->securityUtil = $securityUtil;
        $this->scopeMatcher = $scopeMatcher;
    }
}
