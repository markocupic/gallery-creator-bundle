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

namespace Markocupic\GalleryCreatorBundle\Dca;

use Contao\Backend;
use Contao\Database;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TlContent.
 */
class TlContent extends Backend
{
    /**
     * Return all gallery templates as array.
     */
    public function getGalleryCreatorTemplates(): array
    {
        // Show news ce_element in the news-module only
        if ('news' === Input::get('do')) {
            return $this->getTemplateGroup('ce_gallery_creator_news');
        }

        return $this->getTemplateGroup('ce_gallery_creator');
    }

    /**
     * Return array containing album ids.
     */
    public function optionsCallbackListAlbums(): array
    {
        $objContent = Database::getInstance()
            ->prepare('SELECT gc_sorting, gcSortingDirection FROM tl_content WHERE id=?')
            ->execute(Input::get('id'))
        ;

        $str_sorting = empty($objContent->gc_sorting) || empty($objContent->gcSortingDirection) ? 'date DESC' : $objContent->gc_sorting.' '.$objContent->gcSortingDirection;

        $db = Database::getInstance()
            ->prepare('SELECT id, name FROM tl_gallery_creator_albums WHERE published=? ORDER BY '.$str_sorting)
            ->execute('1')
        ;

        $arrOpt = [];

        while ($db->next()) {
            $arrOpt[$db->id] = '[ID '.$db->id.'] '.$db->name;
        }

        return $arrOpt;
    }

    /**
     * Return the album selector form field.
     */
    public function inputFieldCallbackListAlbums(): string
    {
        if ('tl_content' === Input::post('FORM_SUBMIT')) {
            if (!Input::post('gcPublishAllAlbums')) {
                $albums = [];

                if (Input::post('gcPublishAlbums')) {
                    foreach (StringUtil::deserialize(Input::post('gcPublishAlbums'), true) as $album) {
                        $albums[] = $album;
                    }
                }
                $set = ['gcPublishAlbums' => serialize($albums)];
                Database::getInstance()
                    ->prepare('UPDATE tl_content %s WHERE id=? ')
                    ->set($set)
                    ->execute(Input::get('id'))
                ;
            }
        }

        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/album_selector_form_field.html.twig',
                [
                    'list' => $this->getSubalbumsAsUnorderedList(0),
                    'trans' => [
                        'trans.gcPublishAlbums.0' => $translator->trans('tl_content.gcPublishAlbums.0', [], 'contao_default'),
                        'trans.gcPublishAlbums.1' => $translator->trans('tl_content.gcPublishAlbums.1', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Set up palette.
     */
    public function onloadCbSetUpPalettes(): void
    {
        $objContent = Database::getInstance()
            ->prepare('SELECT gcPublishAllAlbums FROM tl_content WHERE id=?')
            ->execute(Input::get('id'))
        ;

        if ($objContent->gcPublishAllAlbums) {
            $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator'] = str_replace('gcPublishAlbums,', '', $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator']);
        }
    }

    private function getSubalbumsAsUnorderedList(int $pid = 0): string
    {
        $objContent = Database::getInstance()
            ->prepare('SELECT * FROM tl_content WHERE id=?')
            ->execute($this->Input->get('id'))
        ;

        $str_sorting = empty($objContent->gc_sorting) || empty($objContent->gcSortingDirection) ? 'date DESC' : $objContent->gc_sorting.' '.$objContent->gcSortingDirection;

        $selectedAlbums = '' !== $objContent->gcPublishAlbums ? StringUtil::deserialize($objContent->gcPublishAlbums) : [];

        $level = GcHelper::getAlbumLevel((int) $pid);

        $db = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$str_sorting)
            ->execute($pid, 1)
        ;

        $list = '';

        while ($db->next()) {
            $checked = \in_array($db->id, $selectedAlbums, false) ? ' checked' : '';
            $list .= '<li class="album-list-item"><input type="checkbox" name="gcPublishAlbums[]" class="album-control-field" id="albumControlField-'.$db->id.'" value="'.$db->id.'"'.$checked.'>'.$db->name;
            $list .= $this->getSubalbumsAsUnorderedList((int) $db->id);
            $list .= '</li>';
        }

        if ('' !== $list) {
            $paddingLeft = 0 === $level ? '0' : '10px';
            $list = '<ul style="padding-left:'.$paddingLeft.'" class="level_'.$level.'">'.$list.'</ul>';
        }

        return $list;
    }
}
