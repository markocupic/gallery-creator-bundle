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
 * Add fields to tl_user
 */

$GLOBALS['TL_DCA']['tl_user']['fields']['gc_img_resolution'] = array(
       'sql' => "varchar(12) NOT NULL default 'no_scaling'"
);

$GLOBALS['TL_DCA']['tl_user']['fields']['gc_img_quality'] = array(
       'sql' => "smallint(3) unsigned NOT NULL default '100'"
);

$GLOBALS['TL_DCA']['tl_user']['fields']['gc_be_uploader_template'] = array(
       'sql' => "varchar(32) NOT NULL default 'be_gc_html5_uploader'"
);


