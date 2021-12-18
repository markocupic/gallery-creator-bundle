<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * sdfdfsdfsdfsdf
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageResolution'] = [
    'sql' => "varchar(12) NOT NULL default 'no_scaling'",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageQuality'] = [
    'sql' => "smallint(3) unsigned NOT NULL default '100'",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['gcBeUploaderTemplate'] = [
    'sql' => "varchar(64) NOT NULL default 'be_gc_html5_uploader'",
];
