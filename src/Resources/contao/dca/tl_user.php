<?php

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

/**
 * Add fields to tl_user
 */
$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageResolution'] = array(
	'sql' => "varchar(12) NOT NULL default 'no_scaling'",
);

$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageQuality'] = array(
	'sql' => "smallint(3) unsigned NOT NULL default '100'",
);

$GLOBALS['TL_DCA']['tl_user']['fields']['gcBeUploaderTemplate'] = array(
	'sql' => "varchar(64) NOT NULL default 'be_gc_html5_uploader'",
);
