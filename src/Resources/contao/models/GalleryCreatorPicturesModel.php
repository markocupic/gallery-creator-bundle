<?php

/*
 * This file is part of Gallery Creator Bundle (extension for the Contao CMS).
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */



/**
 * Run in a custom namespace, so the class can be replaced
 */

namespace Contao;


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
