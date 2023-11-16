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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\FrontendUser;
use Contao\StringUtil;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class SecurityUtil
{
    public function __construct(
        private readonly Security $security,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Check if a logged in frontend user
     * has access to a protected album.
     */
    public function isAuthorized(GalleryCreatorAlbumsModel $albumsModel): bool
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if (!$albumsModel->protected) {
            return true;
        }

        if ($request && $this->scopeMatcher->isFrontendRequest($request) && $user instanceof FrontendUser) {
            $allowedGroups = StringUtil::deserialize($albumsModel->groups, true);
            $userGroups = StringUtil::deserialize($user->groups, true);

            if (!empty(array_intersect($allowedGroups, $userGroups))) {
                return true;
            }
        }

        return false;
    }
}
