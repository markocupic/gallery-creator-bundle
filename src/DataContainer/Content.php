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

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as TwigEnvironment;

/**
 * Class Content.
 */
class Content extends Backend
{
    private AlbumUtil $albumUtil;
    private Connection $connection;
    private RequestStack $requestStack;
    private TwigEnvironment $twig;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, RequestStack $requestStack, TwigEnvironment $twig)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    /**
     * Options callback.
     *
     * @Callback(table="tl_content", target="fields.gcPublishSingleAlbum.options")
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function optionsCbGcPublishSingleAlbum(DataContainer $dc): array
    {
        $arrOpt = [];

        $arrContent = $this->connection->fetchAssociative('SELECT * FROM tl_content WHERE id = ?', [$dc->activeRecord->id]);

        $strSorting = !$arrContent['gcSorting'] || !$arrContent['gcSortingDirection'] ? 'date DESC' : $arrContent['gcSorting'].' '.$arrContent['gcSortingDirection'];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_gallery_creator_albums WHERE published = ? ORDER BY '.$strSorting, ['1']);

        while (false !== ($album = $stmt->fetchAssociative())) {
            $arrOpt[$album['id']] = '[ID '.$album['id'].'] '.$album['name'];
        }

        return $arrOpt;
    }
}
