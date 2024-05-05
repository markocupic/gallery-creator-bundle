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

namespace Markocupic\GalleryCreatorBundle\Widget\Backend;

use Contao\StringUtil;
use Contao\Widget;

class ChmodTable extends Widget
{
    public const NAME = 'gcAlbumChmod';

    /**
     * Submit user input.
     *
     * @var bool
     */
    protected $blnSubmitInput = true;

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     */
    public function generate()
    {
        $arrObjects = ['u' => 'cuser', 'g' => 'cgroup', 'w' => 'cworld'];

        $return = '  <table id="ctrl_defaultChmod" class="tl_chmod gc-chmod-table">
    <tr>
      <th></th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['editalbum'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['addchildalbums'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['deletealbum'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['movealbum'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['addandeditimages'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['deleteimages'].'</th>
      <th scope="col">'.$GLOBALS['TL_LANG']['CHMOD']['moveimages'].'</th>
    </tr>';

        // Build rows for user, group and world
        foreach ($arrObjects as $k => $v) {
            $return .= '
    <tr>
      <th scope="row">'.$GLOBALS['TL_LANG']['CHMOD'][$v].'</th>';

            // Add checkboxes
            for ($j = 1; $j <= 7; ++$j) {
                $return .= '
      <td><input type="checkbox" name="'.$this->strName.'[]" value="'.StringUtil::specialchars($k.$j).'"'.static::optionChecked($k.$j, $this->varValue).' onfocus="Backend.getScrollOffset()"></td>';
            }

            $return .= '
    </tr>';
        }

        return $return.'
  </table>';
    }
}
