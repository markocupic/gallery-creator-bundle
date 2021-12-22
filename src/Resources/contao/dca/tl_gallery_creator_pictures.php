<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Contao\BackendUser;
use Contao\System;

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = [
    'config'      => [
        'ptable'           => 'tl_gallery_creator_albums',
        'closed'           => true,
        'notCopyable'      => true,
        'notCreatable'     => true,
        'enableVersioning' => true,
        'dataContainer'    => 'Table',
        'sql'              => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'fields'       => ['sorting'],
            'headerFields' => ['id', 'date', 'ownersName', 'name', 'caption', 'thumb'],
            'mode'         => 4,
            'panelLayout'  => 'filter;search,limit',
        ],
        'global_operations' => [
            'fileupload' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class'      => 'gc-gop-icon gc-gop-upload-img',
                'href'       => 'act=edit&table=tl_gallery_creator_albums&key=fileupload',
            ],
            'all'        => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class'      => 'header_edit_all',
                'href'       => 'act=select',
            ],
        ],
        'operations'        => [
            'edit'        => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete'      => [
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmPicture'].'\')) return false; Backend.getScrollOffset();"',
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
            ],
            'cut'         => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'href'       => 'act=paste&mode=cut',
                'icon'       => 'cut.svg',
            ],
            'imagerotate' => [
                'attributes' => 'data-icon="gc-op-icon" onclick="Backend.getScrollOffset();"',
                'href'       => 'key=imagerotate',
                'icon'       => 'bundles/markocupicgallerycreator/images/rotate.svg',
            ],
            'toggle'      => [
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'icon'       => 'visible.svg',
            ],
            'show'        => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes'    => [
        '__selector__'   => ['addCustomThumb'],
        'default'        => 'published,picture,owner,date,imageInfo,addCustomThumb,title,caption;{media_integration:hide},socialMediaSRC,localMediaSRC',
        'restrictedUser' => 'imageInfo,picture',
    ],
    'subpalettes' => [
        'addCustomThumb' => 'customThumb',
    ],
    'fields'      => [
        'id'             => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'pid'            => [
            'eval'       => ['doNotShow' => true],
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'path'           => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'uuid'           => [
            'sql' => 'binary(16) NULL',
        ],
        'sorting'        => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'tstamp'         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published'      => [
            'eval'      => ['isBoolean' => true, 'submitOnChange' => true, 'tl_class' => 'long'],
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'imageInfo'      => [
            'eval' => ['tl_class' => 'clr'],
        ],
        'title'          => [
            'eval'      => ['allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'externalFile'   => [
            'sql' => "char(1) NOT NULL default ''",
        ],
        'caption'        => [
            'cols'      => 20,
            'eval'      => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'textarea',
            'rows'      => 6,
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'picture'        => [
            'eval' => ['tl_class' => 'clr'],
        ],
        'date'           => [
            'inputType' => 'text',
            // A new uploaded image inherits the date of the parent album.
            'default'   => time(),
            'filter'    => true,
            'search'    => true,
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'clr wizard ', 'submitOnChange' => false],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'addCustomThumb' => [
            'eval'      => ['submitOnChange' => true],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'customThumb'    => [
            'eval'      => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => System::getContainer()->getParameter('markocupic_gallery_creator.valid_extensions')],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'owner'          => [
            'default'    => BackendUser::getInstance()->id,
            'eval'       => ['includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'clr w50'],
            'filter'     => true,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
            'search'     => true,
            'sql'        => "int(10) NOT NULL default '0'",
        ],
        'socialMediaSRC' => [
            'eval'      => ['tl_class' => 'clr'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'search'    => true,
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'localMediaSRC'  => [
            'eval'      => ['files' => true, 'filesOnly' => true, 'fieldType' => 'radio'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'fileTree',
            'search'    => true,
            'sql'       => 'binary(16) NULL',
        ],
    ],
];
