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

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Content.
 */
class Content extends Backend
{
    private AlbumUtil $albumUtil;

    private Connection $connection;

    private RequestStack $requestStack;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, RequestStack $requestStack)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
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

    /**
     * Input field callback.
     *
     * @Callback(table="tl_content", target="fields.gcPublishAlbums.input_field")
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function inputFieldCbGcPublishAlbums(DataContainer $dc): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('tl_content' === $request->request->get('FORM_SUBMIT')) {
            if (!$request->request->get('gcPublishAllAlbums')) {
                $albumIDS = [];

                if ($request->request->has('gcPublishAlbums')) {
                    foreach (StringUtil::deserialize($request->request->get('gcPublishAlbums'), true) as $albumId) {
                        $albumIDS[] = $albumId;
                    }
                }

                $set = ['gcPublishAlbums' => serialize($albumIDS)];

                $this->connection->update('tl_content', $set, ['id' => $dc->activeRecord->id]);
            }
        }

        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        Controller::loadLanguageFile('tl_content');

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/album_selector_form_field.html.twig',
                [
                    'list' => $this->getChildAlbumsAsUnorderedList((int) $dc->activeRecord->id, 0),
                    'trans' => [
                        'gcPublishAlbums' => [
                            $translator->trans('tl_content.gcPublishAlbums.0', [], 'contao_default'),
                            $translator->trans('tl_content.gcPublishAlbums.1', [], 'contao_default'),
                        ],
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_content", target="config.onload", priority=100)
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function onloadCbSetUpPalettes(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->query->has('id')) {
            return;
        }

        $id = $request->query->get('id');

        $gcPublishAllAlbums = $this->connection->fetchOne(
            'SELECT gcPublishAllAlbums FROM tl_content WHERE id = ?',
            [$id]
        );

        if ($gcPublishAllAlbums) {
            PaletteManipulator::create()
                ->removeField('gcPublishAlbums')
                ->applyToPalette('gallery_creator', 'tl_content')
            ;
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getChildAlbumsAsUnorderedList(int $contentId, int $pid = 0): string
    {
        $list = '';

        $arrContent = $this->connection->fetchAssociative('SELECT * FROM tl_content WHERE id = ?', [$contentId]);

        if (!$arrContent) {
            return $list;
        }

        $strSorting = !$arrContent['gcSorting'] || !$arrContent['gcSortingDirection'] ? 'date DESC' : $arrContent['gcSorting'].' '.$arrContent['gcSortingDirection'];

        $selectedAlbums = StringUtil::deserialize($arrContent['gcPublishAlbums'], true);

        $level = $this->albumUtil->getAlbumLevelFromPid($pid);

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY '.$strSorting,
            [$pid, '1']
        );

        while (false !== ($album = $stmt->fetchAssociative())) {
            $checked = \in_array($album['id'], $selectedAlbums, false) ? ' checked' : '';
            $list .= sprintf(
                '<li class="album-list-item"><input type="checkbox" name="gcPublishAlbums[]" class="album-control-field" id="albumControlField-%s" value="%s"%s>%s%s</li>',
                $album['id'],
                $album['id'],
                $checked,
                $album['name'],
                $this->getChildAlbumsAsUnorderedList($contentId, (int) $album['id'])
            );
        }

        if (\strlen($list)) {
            $paddingLeft = 0 === $level ? '0' : '10px';
            $list = sprintf(
                '<ul style="padding-left:%s" class="level_%s">%s</ul>',
                $paddingLeft,
                $level,
                $list,
            );
        }

        return $list;
    }
}
