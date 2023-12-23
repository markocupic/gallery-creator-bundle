<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageResolution'] = [
    'sql' => "varchar(12) NOT NULL default 'no_scaling'",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['gcImageQuality'] = [
    'sql' => "smallint(3) unsigned NOT NULL default '100'",
];
