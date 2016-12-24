<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2015 Leo Feyer
 *
 * @package Gallery Creator
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;

use Contao\GalleryCreatorAlbumsModel;
use Markocupic\GcHelpers;


/**
 * Reads and writes tl_gallery_creator_pictures
 */
class GalleryCreatorPicturesModel extends \Model
{

       /**
        * Table name
        * @var string
        */
       protected static $strTable = 'tl_gallery_creator_pictures';

}
