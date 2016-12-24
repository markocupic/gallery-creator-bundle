<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;

use Contao\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreator\GcHelpers;


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
