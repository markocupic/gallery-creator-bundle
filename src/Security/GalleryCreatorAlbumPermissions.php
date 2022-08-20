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

namespace Markocupic\GalleryCreatorBundle\Security;

final class GalleryCreatorAlbumPermissions
{
    /**
     * Access is granted, if the current user can edit a certain album.
     * Subject must be an album ID.
     */
    public const USER_CAN_EDIT_ALBUM = 'contao_gallery_creator_user.can_edit_album';

    /**
     * Access is granted, if the current user can add child albums to the current album.
     * Subject must be an album ID.
     */
    public const USER_CAN_ADD_CHILD_ALBUMS = 'contao_gallery_creator_user.can_add_child_albums';

    /**
     * Access is granted, if the current user can delete a certain album.
     * Subject must be an album ID.
     */
    public const USER_CAN_DELETE_ALBUM = 'contao_gallery_creator_user.can_delete_album';

    /**
     * Access is granted, if the current user can change the hierarchy of albums.
     * Subject must be an album ID.
     */
    public const USER_CAN_MOVE_ALBUM = 'contao_gallery_creator_user.can_move_album';

    /**
     * Access is granted, if the current user can add and edit images inside a certain album.
     * Subject must be an album ID.
     */
    public const USER_CAN_ADD_AND_EDIT_MAGES = 'contao_gallery_creator_user.can_add_and_edit_images';

    /**
     * Access is granted, if the current user can delete images inside a certain album.
     * Subject must be an album ID.
     */
    public const USER_CAN_DELETE_IMAGES = 'contao_gallery_creator_user.can_delete_images';

    /**
     * Access is granted, if the current user can move images inside a certain album.
     * Subject must be an album ID.
     */
    public const USER_CAN_MOVE_IMAGES = 'contao_gallery_creator_user.can_move_images';
}
