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

namespace Markocupic\GalleryCreatorBundle\Security\Voter;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AlbumVoter extends Voter
{
    private const ALBUM_PERMISSIONS = [
        'can_edit_album' => 1,
        'can_add_child_albums' => 2,
        'can_delete_album' => 3,
        'can_move_album' => 4,
        'can_add_and_edit_images' => 5,
        'can_delete_images' => 6,
        'can_move_images' => 7,
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * @param $attribute
     * @param GalleryCreatorAlbumsModel|int $subject
     */
    protected function supports($attribute, $subject): bool
    {
        if (\is_scalar($subject)) {
            if (null !== ($album = GalleryCreatorAlbumsModel::findByPk($subject))) {
                $subject = $album;
            }
        }

        // Only vote on `GalleryCreatorAlbumsModel` objects
        if (!$subject instanceof GalleryCreatorAlbumsModel) {
            return false;
        }

        $arrPermission = StringUtil::trimsplit('.', $attribute);

        if (!\is_array($arrPermission) || 2 !== \count($arrPermission)) {
            return false;
        }

        if ('contao_gallery_creator_user' !== $arrPermission[0]) {
            return false;
        }

        if (!\in_array($arrPermission[1], array_keys(self::ALBUM_PERMISSIONS), true)) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, $albumId, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof BackendUser) {
            // the user must be logged in; if not, deny access
            return false;
        }

        if (!\is_scalar($albumId)) {
            throw new \Exception('The album id parameter has to be of type string or id.');
        }

        // Convert id to object if $subject is an album id.
        if (null === ($album = GalleryCreatorAlbumsModel::findByPk($albumId))) {
            throw new \Exception('Album with id '.$albumId.' not found.');
        }

        if ($user->admin || !$album->includeChmod) {
            return true;
        }

        if (!$album->includeChmod) {
            return false;
        }

        $permission = StringUtil::trimsplit('.', $attribute);

        $field = $permission[1];

        if (isset(self::ALBUM_PERMISSIONS[$field])) {
            return $this->isAllowed($album, self::ALBUM_PERMISSIONS[$field], $user);
        }

        throw new \Exception(sprintf('Permission "%s" not found.', $field));
    }

    /**
     * Checks if the user has access to a given album.
     */
    private function isAllowed(GalleryCreatorAlbumsModel $album, int $flag, BackendUser $user): bool
    {
        [$cuser, $cgroup, $chmod] = $this->getAlbumPermissions($album->row());

        $permission = ['w'.$flag];

        if (\in_array($cgroup, $user->groups, false)) {
            $permission[] = 'g'.$flag;
        }

        if ($cuser === (int) $user->id) {
            $permission[] = 'u'.$flag;
        }

        return \count(array_intersect($permission, $chmod)) > 0;
    }

    private function getAlbumPermissions(array $row): array
    {
        if (!($row['includeChmod'] ?? false)) {
            $pid = $row['pid'] ?? null;

            $row['chmod'] = false;
            $row['cuser'] = false;
            $row['cgroup'] = false;

            $parentAlbum = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findByPk($pid);

            while (null !== $parentAlbum && (false === $row['chmod'] && '' === $row['includeChmod']) && $pid > 0) {
                $pid = $parentAlbum->pid;

                $row['chmod'] = $parentAlbum->includeChmod ? $parentAlbum->chmod : false;
                $row['cuser'] = $parentAlbum->includeChmod ? $parentAlbum->cuser : false;
                $row['cgroup'] = $parentAlbum->includeChmod ? $parentAlbum->cgroup : false;

                $parentAlbum = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->findByPk($pid);
            }

            // Set default values
            if (false === $row['chmod']) {
                $config = $this->framework->getAdapter(Config::class);

                $row['chmod'] = $config->get('gcDefaultChmod');

                $cuser = $config->get('gcDefaultUser') ?: $row['cuser'];
                $row['cuser'] = (int) $cuser;

                $row['cgroup'] = (int) $config->get('gcDefaultGroup');
            }
        }

        return [(int) ($row['cuser'] ?? null), (int) ($row['cgroup'] ?? null), StringUtil::deserialize(($row['chmod'] ?? null), true)];
    }
}
