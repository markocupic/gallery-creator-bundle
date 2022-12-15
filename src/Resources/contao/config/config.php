<?php
/**
 * Gallery Creator Bundle
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2019
 * @license MIT
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */

use Contao\Config;
use Contao\Input;

/**
 * Define Constants
 */
define('GALLERY_CREATOR_UPLOAD_PATH', Config::get('uploadPath') . '/gallery_creator_albums');

/**
 * Front end content element
 */
// Display a single album within the news module
array_insert($GLOBALS['TL_CTE'], 2, array('ce_type_gallery_creator' => array('gallery_creator_ce_news' => 'Markocupic\GalleryCreatorBundle\ContentGalleryCreatorNews')));
array_insert($GLOBALS['TL_CTE'], 2, array('ce_type_gallery_creator' => array('gallery_creator_ce' => 'Markocupic\GalleryCreatorBundle\ContentGalleryCreator')));

// Show news ce_element in the news-module only
if (TL_MODE === 'BE' && Input::get('do') == 'news')
{
    unset($GLOBALS['TL_CTE']['ce_type_gallery_creator']['gallery_creator_ce']);
}
if (TL_MODE === 'BE' && Input::get('do') != 'news')
{
    unset($GLOBALS['TL_CTE']['ce_type_gallery_creator']['gallery_creator_ce_news']);
}


/**
 * Back end module
 */
if (TL_MODE === 'BE')
{

    $GLOBALS['BE_MOD']['content']['gallery_creator'] = array(
        'icon'   => 'bundles/markocupicgallerycreator/images/picture.png',
        'tables' => array(
            'tl_gallery_creator_albums',
            'tl_gallery_creator_pictures'
        )
    );


    // check tables script
    if (count($_GET) <= 2 && isset($_GET['do']) && $_GET['do'] === 'gallery_creator' && isset($_GET['mode']) && $_GET['mode'] !== 'revise_tables')
    {
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_check_tables.js';
    }

    // revise table script
    if (isset($_GET['do']) && $_GET['do'] === 'gallery_creator' && isset($_GET['mode']) && $_GET['mode'] === 'revise_tables')
    {
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_revise_tables.js';
    }

    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be.js';
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicgallerycreator/css/gallery_creator_be.css';
}
