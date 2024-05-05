<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Widget\Backend;

use Contao\Controller;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Doctrine\DBAL\Driver\Exception;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use Knp\Menu\Renderer\ListRenderer;
use Symfony\Component\HttpFoundation\Response;

class AlbumTreeWidget extends Widget
{
    public const NAME = 'gcAlbumTree';

    protected $blnSubmitInput = true;
    protected $blnForAttribute = true;
    protected $strTemplate = 'be_widget';
    protected MenuItem|null $picker;

    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);
    }

    public function generate(): string
    {
        Controller::loadLanguageFile('tl_content');
        $twig = System::getContainer()->get('twig');

        $factory = new MenuFactory();

        /** @var MenuItem $picker */
        $picker = $factory->createItem('albumPicker');

        $this->getChildAlbumsAsUnorderedList(0, $picker);

        $renderer = new ListRenderer(new Matcher());

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/be_ff_album_tree_field.html.twig',
                [
                    'id' => 'ctrl_'.$this->name,
                    'picker' => $renderer->render($picker, ['allow_safe_labels' => true]),
                    'input' => [
                        'multiple' => $this->multiple ? '1' : '',
                        'mandatory' => $this->mandatory ? '1' : '',
                        'name' => $this->strName,
                    ],
                ]
            )
        ))->getContent();
    }

    public function validate(): void
    {
        parent::validate();
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getChildAlbumsAsUnorderedList(int $pid, $picker): void
    {
        $connection = System::getContainer()->get('database_connection');

        $stmt = $connection->executeQuery(
            'SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY sorting',
            [$pid, '1']
        );

        while (false !== ($album = $stmt->fetchAssociative())) {
            if ($this->multiple) {
                $checked = \in_array($album['id'], StringUtil::deserialize($this->value, true), false) ? ' checked' : '';
            } else {
                $checked = (int) $this->value === (int) $album['id'] ? ' checked' : '';
            }

            // Add a new item to the picker
            $pickerItem = $picker->addChild('album_'.$album['id']);
            $pickerItem->setAttribute('class', 'gc-album-list-item');

            $label = sprintf(
                '<div class="gc-flex"><div class="gc-flex-left">%s</div><div class="gc-flex-right gc-text-align-right"><input type="%s" name="%s" class="%s" id="albumControlField-%s" value="%s"%s></div></div>',
                $album['name'],
                $this->multiple ? 'checkbox' : 'radio',
                $this->multiple ? $this->strName.'[]' : $this->strName,
                $this->multiple ? 'tl_checkbox album-control-field' : 'tl_radio album-control-field',
                $album['id'],
                $album['id'],
                $checked,
            );

            $pickerItem->setLabel($label);

            // Allow html in labels
            $pickerItem->setExtra('safe_label', true);

            // Add children
            $this->getChildAlbumsAsUnorderedList((int) $album['id'], $pickerItem);
        }
    }
}
