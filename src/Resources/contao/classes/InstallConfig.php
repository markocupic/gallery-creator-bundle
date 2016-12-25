<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 25.12.2016
 * Time: 20:17
 */

namespace Markocupic\GalleryCreatorBundle;


class InstallConfig
{
    /**
     * post install routine
     */
    public static function postInstall()
    {
        mail('m.cupic@gmx.ch', 'Plugin installed successfully', '');
    }
}