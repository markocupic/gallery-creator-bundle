<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Contao\BackendUser;
use Contao\DC_Table;
use Contao\System;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = [
    'config'      => [
        'ptable'           => 'tl_gallery_creator_albums',
        'closed'           => true,
        'notCopyable'      => true,
        'notCreatable'     => true,
        'enableVersioning' => true,
        'dataContainer'    => DC_Table::class,
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
            'headerFields' => ['date', 'name'],
            'mode'         => DataContainer::MODE_PARENT,
            'panelLayout'  => 'filter;sorting,search,limit',
        ],
        'global_operations' => [
            'fileUpload' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class'      => 'gc-gop-icon gc-gop-upload-img',
                'href'       => 'act=edit&table=tl_gallery_creator_albums&key=fileUpload',
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
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
            ],
            'cut'         => [
                'href'       => 'act=paste&amp;mode=cut',
                'icon'       => 'cut.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"',
            ],
            'imagerotate' => [
                'attributes' => 'data-icon="gc-op-icon" onclick="Backend.getScrollOffset();"',
                'href'       => 'key=imagerotate',
                'icon'       => 'bundles/markocupicgallerycreator/images/rotate.svg',
            ],
            'toggle'      => [
                'href'       => 'act=toggle&amp;field=published',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'icon'       => 'visible.svg',
            ],
            'show'        => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes'    => [
        '__selector__' => ['addCustomThumb'],
        'default'      => '
            {picture_info_legend},picture,imageInfo;
            {title_legend},title,caption,cuser,date;
            {thumb_legend},addCustomThumb;
            {media_integration:hide},socialMediaSRC,localMediaSRC
        ',
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
            'eval'      => ['doNotCopy' => true],
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
            'toggle'    => true,
        ],
        'picture'        => [
            'eval' => ['tl_class' => 'w50'],
        ],
        'imageInfo'      => [
            'eval' => ['tl_class' => 'w50'],
        ],
        'title'          => [
            'eval'      => ['allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum', 'tl_class' => 'w50'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'caption'        => [
            'cols'      => 20,
            'eval'      => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'w50', 'allowHtml' => false, 'wrap' => 'soft'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'rows'      => 5,
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'date'           => [
            'default'   => time(), // A new uploaded image inherits the date of its parent album.
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard ', 'submitOnChange' => false],
            'filter'    => true,
            'inputType' => 'text',
            'sorting'   => true,
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'cuser'          => [
            'default'    => System::getContainer()->get('security.helper')->getUser() instanceof BackendUser ? System::getContainer()->get('security.helper')->getUser()->id : 0,
            'eval'       => ['includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'nospace' => true, 'tl_class' => 'clr w50'],
            'filter'     => true,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
            'sql'        => 'int(10) NOT NULL default 0',
        ],
        'addCustomThumb' => [
            'eval'      => ['submitOnChange' => true, 'isBoolean' => true],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'customThumb'    => [
            'eval'      => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => System::getContainer()->getParameter('markocupic_gallery_creator.valid_extensions')],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'socialMediaSRC' => [
            'eval'      => ['tl_class' => 'clr'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'localMediaSRC'  => [
            'eval'      => ['files' => true, 'filesOnly' => true, 'fieldType' => 'radio'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'fileTree',
            'sql'       => 'binary(16) NULL',
        ],
        'externalFile'   => [
            'eval' => ['isBoolean' => true],
            'sql'  => "char(1) NOT NULL default ''",
        ],
    ],
];
