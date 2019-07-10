<?php
/**
 * Gallery Creator Bundle
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2019
 * @license MIT
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */


use Markocupic\GalleryCreatorBundle\GcHelpers;

/**
 * Class ce_gallery_creator
 */
class ce_gallery_creator extends Backend
{

    public function __construct()
    {

        parent::__construct();
    }


    /**
     * Return all gallery templates as array
     *
     * @return array
     */
    public function getGalleryCreatorTemplates()
    {

        return $this->getTemplateGroup('ce_gc');
    }


    /**
     * options_callback optionsCallbackListAlbums
     *
     * @return string
     */
    public function optionsCallbackListAlbums()
    {

        $objContent = $this->Database->prepare('SELECT gc_sorting, gc_sorting_direction FROM tl_content WHERE id=?')->execute(Input::get('id'));

        $str_sorting = $objContent->gc_sorting == '' || $objContent->gc_sorting_direction == '' ? 'date DESC' : $objContent->gc_sorting . ' ' . $objContent->gc_sorting_direction;

        $db = $this->Database->prepare('SELECT id, name FROM tl_gallery_creator_albums WHERE published=? ORDER BY ' . $str_sorting)->execute('1');

        $arrOpt = [];
        while ($db->next())
        {
            $arrOpt[$db->id] = '[ID ' . $db->id . '] ' . $db->name;
        }

        return $arrOpt;
    }


    /**
     * input_field_callback inputFieldCallbackListAlbums
     *
     * @return string
     */
    public function inputFieldCallbackListAlbums()
    {
        if (\Input::post('FORM_SUBMIT') == 'tl_content')
        {

            if (!\Input::post('gc_publish_all_albums'))
            {
                $albums = [];
                if (\Input::post('gc_publish_albums'))
                {
                    foreach (deserialize(\Input::post('gc_publish_albums'), true) as $album)
                    {
                        $albums[] = $album;
                    }
                }
                $set = array('gc_publish_albums' => serialize($albums));
                $this->Database->prepare('UPDATE tl_content %s WHERE id=? ')->set($set)->execute(\Input::get('id'));
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

    /**
     * @param int $pid
     * @return string
     */
    private function getSubalbumsAsUnorderedList($pid = 0)
    {
        $objContent = $this->Database->prepare('SELECT * FROM tl_content WHERE id=?')->execute($this->Input->get('id'));
        $str_sorting = $objContent->gc_sorting == '' || $objContent->gc_sorting_direction == '' ? 'date DESC' : $objContent->gc_sorting . ' ' . $objContent->gc_sorting_direction;


        $selectedAlbums = $objContent->gc_publish_albums != '' ? deserialize($objContent->gc_publish_albums) : array();
        $level = GcHelpers::getAlbumLevel($pid);
        $db = $this->Database->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY ' . $str_sorting)->execute($pid, 1);
        while ($db->next())
        {
            $checked = in_array($db->id, $selectedAlbums) ? ' checked' : '';
            $list .= '<li class="album-list-item"><input type="checkbox" name="gc_publish_albums[]" class="album-control-field" id="albumControlField-' . $db->id . '" value="' . $db->id . '"' . $checked . '>' . $db->name;
            $list .= $this->getSubalbumsAsUnorderedList($db->id);
            $list .= '</li>';
        }
        if ($list != '')
        {
            $paddingLeft = $level == 0 ? '0' : '10px';
            $list = '<ul style="padding-left:' . $paddingLeft . '" class="level_' . $level . '">' . $list . '</ul>';
        }

        return $list;

    }


    /**
     * onload_callback onloadCbSetUpPalettes
     *
     * @return string
     */
    public function onloadCbSetUpPalettes()
    {

        $objContent = $this->Database->prepare('SELECT gc_publish_all_albums FROM tl_content WHERE id=?')->execute(Input::get('id'));
        if ($objContent->gc_publish_all_albums)
        {
            $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce'] = str_replace('gc_publish_albums,', '', $GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce']);
        }
    }
}
