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

namespace Markocupic\GalleryCreatorBundle\Listener\ContaoHook;

use Contao\Input;

/**
 * Class InitializeSystem.
 */
class InitializeSystem
{
    public function setContentElements()
    {
        // Show news ce_element in the news-module only
        if (TL_MODE === 'BE' && 'news' === Input::get('do')) {
            unset($GLOBALS['TL_CTE']['gallery_creator_elements']['gallery_creator']);
        }

        if (TL_MODE === 'BE' && 'news' !== Input::get('do')) {
            unset($GLOBALS['TL_CTE']['gallery_creator_elements']['gallery_creator_news']);
        }
    }
}
