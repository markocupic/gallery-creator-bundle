<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Database;
use Contao\Input;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Util\GalleryCreatorUtil;

class Content
{

    public function optionsCallbackListAlbums(): array
    {
        $objContent = Database::getInstance()->prepare('SELECT gc_sorting, gc_sorting_direction FROM tl_content WHERE id=?')->execute(Input::get('id'));

        $str_sorting = '' === $objContent->gc_sorting || '' === $objContent->gc_sorting_direction ? 'date DESC' : $objContent->gc_sorting.' '.$objContent->gc_sorting_direction;

        $db = Database::getInstance()->prepare('SELECT id, name FROM tl_gallery_creator_albums WHERE published=? ORDER BY '.$str_sorting)->execute('1');

        $arrOpt = [];

        while ($db->next()) {
            $arrOpt[$db->id] = '[ID '.$db->id.'] '.$db->name;
        }

        return $arrOpt;
    }

    public function inputFieldCallbackListAlbums(): string
    {
        if ('tl_content' === Input::post('FORM_SUBMIT')) {
            if (!Input::post('gc_publish_all_albums')) {
                $albums = [];

                if (Input::post('gc_publish_albums')) {
                    foreach (StringUtil::deserialize(Input::post('gc_publish_albums'), true) as $album) {
                        $albums[] = $album;
                    }
                }
                $set = ['gc_publish_albums' => serialize($albums)];
                Database::getInstance()->prepare('UPDATE tl_content %s WHERE id=? ')->set($set)->execute(Input::get('id'));
            }
        }

        $html = '
<div class="clr widget">
  <fieldset id="ctrl_gc_publish_albums" class="tl_checkbox_container">
        <legend>Folgende Alben im Frontend anzeigen</legend>
        <input type="hidden" name="gc_publish_albums" value="">
        <input type="checkbox" id="check_all_gc_publish_albums" class="tl_checkbox" onclick="Backend.toggleCheckboxGroup(this,\'ctrl_gc_publish_albums\')"> <label for="check_all_gc_publish_albums" style="color:#a6a6a6"><em>Alle ausw&auml;hlen</em></label>
        <br><br>
        %s
        <p class="tl_help tl_tip" title="">Ausgew&auml;hlte Alben werden im Frontend angezeigt.</p>
    </fieldset>
</div>';

        return sprintf($html, $this->getSubalbumsAsUnorderedList(0));
    }

    public function onloadCbSetUpPalettes(): void
    {
        $objContent = Database::getInstance()->prepare('SELECT gc_publish_all_albums FROM tl_content WHERE id=?')->execute(Input::get('id'));

        if ($objContent->gc_publish_all_albums) {
            $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce'] = str_replace('gc_publish_albums,', '', $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce']);
        }
    }

    private function getSubalbumsAsUnorderedList(int $pid = 0): string
    {
        $objContent = Database::getInstance()
            ->prepare('SELECT * FROM tl_content WHERE id=?')
            ->execute(Input::get('id'))
        ;

        $str_sorting = '' === $objContent->gc_sorting || '' === $objContent->gc_sorting_direction ? 'date DESC' : $objContent->gc_sorting.' '.$objContent->gc_sorting_direction;
        $list = '';

        $selectedAlbums = $objContent->gc_publish_albums ? StringUtil::deserialize($objContent->gc_publish_albums, true) : [];
        $level = GalleryCreatorUtil::getAlbumLevel($pid);
        $db = Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY '.$str_sorting)->execute($pid, 1);

        while ($db->next()) {
            $checked = \in_array($db->id, $selectedAlbums, true) ? ' checked' : '';
            $list .= '<li class="album-list-item"><input type="checkbox" class="tl_checkbox" name="gc_publish_albums[]" class="album-control-field" id="albumControlField-'.$db->id.'" value="'.$db->id.'"'.$checked.'>'.$db->name;
            $list .= $this->getSubalbumsAsUnorderedList($db->id);
            $list .= '</li>';
        }

        if ('' !== $list) {
            $paddingLeft = 0 === $level ? '0' : '10px';
            $list = '<ul style="padding-left:'.$paddingLeft.'" class="level_'.$level.'">'.$list.'</ul>';
        }

        return $list;
    }
}
