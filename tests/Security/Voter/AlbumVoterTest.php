<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Tests\Security\Voter;

use Contao\BackendUser;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Security\Voter\AlbumVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AlbumVoterTest extends ContaoTestCase
{
    private const string ATTRIBUTE = 'contao_gallery_creator_user.can_edit_album';

    public function testAbstainsWhenSubjectCannotBeResolvedToAlbum(): void
    {
        $voter = $this->voter(null);

        $result = $voter->vote($this->token($this->backendUser()), 999, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForForeignAttributePrefix(): void
    {
        $voter = $this->voter($this->album(['id' => 10]));

        $result = $voter->vote($this->token($this->backendUser()), 10, ['foo.can_edit_album']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForUnknownPermission(): void
    {
        $voter = $this->voter($this->album(['id' => 10]));

        $result = $voter->vote($this->token($this->backendUser()), 10, ['contao_gallery_creator_user.can_fly']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForMalformedAttribute(): void
    {
        $voter = $this->voter($this->album(['id' => 10]));

        $result = $voter->vote($this->token($this->backendUser()), 10, ['contao_gallery_creator_user']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testDeniesWhenUserIsNotABackendUser(): void
    {
        $voter = $this->voter($this->album(['id' => 10, 'includeChmod' => true]));

        $result = $voter->vote($this->token(null), 10, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsForAdminUser(): void
    {
        $voter = $this->voter($this->album(['id' => 10, 'includeChmod' => true]));

        $result = $voter->vote($this->token($this->backendUser(['admin' => true])), 10, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsWhenAlbumHasNoOwnPermissions(): void
    {
        $voter = $this->voter($this->album(['id' => 10, 'includeChmod' => false]));

        $result = $voter->vote($this->token($this->backendUser(['admin' => false])), 10, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsWhenUserMatchesAlbumChmod(): void
    {
        $album = $this->album([
            'id' => 10,
            'includeChmod' => true,
            'cuser' => 42,
            'cgroup' => 7,
            'chmod' => serialize(['u1']),
        ]);

        $voter = $this->voter($album);

        // can_edit_album => flag 1; the user is the owner (cuser), so "u1" applies.
        $user = $this->backendUser(['admin' => false, 'id' => 42, 'groups' => []]);

        $result = $voter->vote($this->token($user), 10, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesWhenUserDoesNotMatchAlbumChmod(): void
    {
        $album = $this->album([
            'id' => 10,
            'includeChmod' => true,
            'cuser' => 42,
            'cgroup' => 7,
            'chmod' => serialize(['w5']),
        ]);

        $voter = $this->voter($album);

        // Neither owner nor group match, and "w1" is not granted.
        $user = $this->backendUser(['admin' => false, 'id' => 1, 'groups' => []]);

        $result = $voter->vote($this->token($user), 10, [self::ATTRIBUTE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    private function voter(GalleryCreatorAlbumsModel|null $album): AlbumVoter
    {
        $albumsAdapter = $this->mockAdapter(['findById']);
        $albumsAdapter
            ->method('findById')
            ->willReturn($album)
        ;

        $stringUtil = $this->mockAdapter(['trimsplit']);
        $stringUtil
            ->method('trimsplit')
            ->willReturnCallback(static fn (string $pattern, string $string): array => explode($pattern, $string))
        ;

        $framework = $this->mockContaoFramework([
            GalleryCreatorAlbumsModel::class => $albumsAdapter,
            StringUtil::class => $stringUtil,
        ]);

        return new AlbumVoter($framework);
    }

    private function token(object|null $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        return $token;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function backendUser(array $props = []): BackendUser
    {
        return $this->mockClassWithProperties(BackendUser::class, array_merge([
            'id' => 1,
            'admin' => false,
            'groups' => [],
        ], $props));
    }

    /**
     * @param array<string, mixed> $props
     */
    private function album(array $props = []): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, $props);
    }
}
