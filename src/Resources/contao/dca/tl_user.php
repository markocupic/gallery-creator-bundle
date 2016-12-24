<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
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


