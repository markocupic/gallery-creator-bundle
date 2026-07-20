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

namespace Markocupic\GalleryCreatorBundle\Tests\Util;

use Contao\BackendUser;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Message;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Event\ImagePostInsertEvent;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileUtilTest extends ContaoTestCase
{
    public function testIsValidFileNameAcceptsAllowedExtension(): void
    {
        $fileUtil = $this->createFileUtil($this->frameworkWithValidator(true), validExtensions: ['jpg', 'png']);

        $this->assertTrue($fileUtil->isValidFileName('Photo.JPG'));
    }

    public function testIsValidFileNameRejectsDisallowedExtension(): void
    {
        $fileUtil = $this->createFileUtil($this->frameworkWithValidator(true), validExtensions: ['jpg', 'png']);

        $this->assertFalse($fileUtil->isValidFileName('document.txt'));
    }

    public function testIsValidFileNameRejectsMissingExtension(): void
    {
        $fileUtil = $this->createFileUtil($this->frameworkWithValidator(true), validExtensions: ['jpg']);

        $this->assertFalse($fileUtil->isValidFileName('photo'));
    }

    public function testIsValidFileNameRejectsInvalidContaoName(): void
    {
        $fileUtil = $this->createFileUtil($this->frameworkWithValidator(false), validExtensions: ['jpg']);

        $this->assertFalse($fileUtil->isValidFileName('photo.jpg'));
    }

    public function testGenerateFilenameReturnsSanitizedPathWhenUnique(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(false)
        ;

        $fileUtil = $this->createFileUtil(
            $this->frameworkWithSanitize(static fn (string $name): string => $name),
            filesystem: $filesystem,
        );

        $this->assertSame('files/album/photo.jpg', $fileUtil->generateSanitizedAndUniqueFilename('files/album/photo.jpg'));
    }

    public function testGenerateFilenameAppendsSuffixOnCollision(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        // First candidate exists, second one is free.
        $filesystem
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $fileUtil = $this->createFileUtil(
            $this->frameworkWithSanitize(static fn (string $name): string => $name),
            filesystem: $filesystem,
        );

        $this->assertSame('files/album/photo_0001.jpg', $fileUtil->generateSanitizedAndUniqueFilename('files/album/photo.jpg'));
    }

    public function testGenerateFilenameThrowsOnTrailingDot(): void
    {
        $fileUtil = $this->createFileUtil($this->frameworkWithSanitize(static fn (): string => 'photo.'));

        $this->expectException(\Exception::class);

        $fileUtil->generateSanitizedAndUniqueFilename('files/album/whatever.jpg');
    }

    public function testAddImageToAlbumDispatchesEventOnSuccess(): void
    {
        $pictureModel = $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, ['id' => 99]);

        $framework = $this->frameworkForAddImage($pictureModel);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(true)
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(10)
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ImagePostInsertEvent::class))
            ->willReturnArgument(0)
        ;

        $fileUtil = $this->createFileUtil(
            $framework,
            connection: $connection,
            eventDispatcher: $dispatcher,
            filesystem: $filesystem,
            security: $this->securityWithUser($this->mockClassWithProperties(BackendUser::class, ['id' => 3])),
        );

        $this->assertTrue($fileUtil->addImageToAlbum($this->album(), $this->file()));
    }

    public function testAddImageToAlbumDeletesRecordAndReturnsFalseWhenFileMissing(): void
    {
        $pictureModel = $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, ['id' => 99]);
        $pictureModel
            ->expects($this->once())
            ->method('delete')
        ;

        $framework = $this->frameworkForAddImage($pictureModel);

        $filesystem = $this->createMock(Filesystem::class);

        // Upload directory exists, but the (renamed) file does not.
        $filesystem
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, false)
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(10)
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->never())
            ->method('dispatch')
        ;

        $fileUtil = $this->createFileUtil(
            $framework,
            connection: $connection,
            eventDispatcher: $dispatcher,
            filesystem: $filesystem,
            security: $this->securityWithUser($this->mockClassWithProperties(BackendUser::class, ['id' => 3])),
        );

        $this->assertFalse($fileUtil->addImageToAlbum($this->album(), $this->file()));
    }

    public function testAddImageToAlbumThrowsWithoutBackendUser(): void
    {
        $fileUtil = $this->createFileUtil(
            $this->mockContaoFramework(),
            security: $this->securityWithUser(null),
        );

        $this->expectException(\Exception::class);

        $fileUtil->addImageToAlbum($this->album(), $this->file());
    }

    public function testAddImageToAlbumThrowsWhenFileHasNoModel(): void
    {
        $file = $this->mockClassWithProperties(File::class, ['path' => 'files/album/x.jpg']);
        $file
            ->method('getModel')
            ->willReturn(null)
        ;

        $fileUtil = $this->createFileUtil(
            $this->mockContaoFramework(),
            security: $this->securityWithUser($this->mockClassWithProperties(BackendUser::class, ['id' => 3])),
        );

        $this->expectException(ResponseException::class);

        $fileUtil->addImageToAlbum($this->album(), $file);
    }

    public function testAddImageToAlbumThrowsWithoutAssignedDir(): void
    {
        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn(null) // no upload folder
        ;

        $framework = $this->mockContaoFramework([FilesModel::class => $filesAdapter]);

        $fileUtil = $this->createFileUtil(
            $framework,
            security: $this->securityWithUser($this->mockClassWithProperties(BackendUser::class, ['id' => 3])),
        );

        $this->expectException(ResponseException::class);

        $fileUtil->addImageToAlbum($this->album(), $this->file());
    }

    public function testImportFromFilesystemReturnsEarlyWithoutRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchFirstColumn')
        ;

        $fileUtil = $this->createFileUtil($this->mockContaoFramework(), connection: $connection, requestStack: new RequestStack());

        $fileUtil->importFromFilesystem($this->album(), ['some-uuid']);

        $this->addToAssertionCount(1);
    }

    public function testImportFromFilesystemReturnsEarlyWhenNoFilesFound(): void
    {
        $filesAdapter = $this->mockAdapter(['findMultipleByUuids']);
        $filesAdapter
            ->method('findMultipleByUuids')
            ->willReturn(null)
        ;

        $framework = $this->mockContaoFramework([FilesModel::class => $filesAdapter]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchFirstColumn')
        ;

        $fileUtil = $this->createFileUtil($framework, connection: $connection, requestStack: $this->requestStack());

        $fileUtil->importFromFilesystem($this->album(), ['some-uuid']);

        $this->addToAssertionCount(1);
    }

    public function testImportFromFilesystemSkipsAlreadyImportedFiles(): void
    {
        $fileFilesModel = $this->mockClassWithProperties(FilesModel::class, [
            'type' => 'file',
            'path' => 'files/x.jpg',
            'uuid' => 'dup-uuid',
        ]);

        $collection = new Collection([$fileFilesModel], FilesModel::getTable());

        $filesAdapter = $this->mockAdapter(['findMultipleByUuids']);
        $filesAdapter
            ->method('findMultipleByUuids')
            ->willReturn($collection)
        ;

        $validator = $this->mockAdapter(['isValidFileName']);
        $validator
            ->method('isValidFileName')
            ->willReturn(true)
        ;

        $framework = $this->mockContaoFramework([
            FilesModel::class => $filesAdapter,
            Validator::class => $validator,
            Message::class => $this->mockAdapter(['addError']),
            Dbafs::class => $this->mockAdapter(['addResource']),
        ]);

        $file = $this->mockClassWithProperties(File::class, [
            'name' => 'x.jpg',
            'basename' => 'x',
            'path' => 'files/x.jpg',
        ]);

        $framework
            ->method('createInstance')
            ->willReturnCallback(static fn (string $class, array $args = []) => File::class === $class ? $file : null)
        ;

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(true)
        ;

        $connection = $this->createMock(Connection::class);

        // First query: existing uuids; second query: existing paths.
        $connection
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls(['dup-uuid'], ['files/x.jpg'])
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->never())
            ->method('dispatch')
        ;

        $fileUtil = $this->createFileUtil(
            $framework,
            connection: $connection,
            eventDispatcher: $dispatcher,
            filesystem: $filesystem,
            requestStack: $this->requestStack(),
        );

        $fileUtil->importFromFilesystem($this->album(), ['dup-uuid']);
    }

    private function createFileUtil(ContaoFramework $framework, Connection|null $connection = null, EventDispatcherInterface|null $eventDispatcher = null, Filesystem|null $filesystem = null, RequestStack|null $requestStack = null, Security|null $security = null, TranslatorInterface|null $translator = null, bool $copyImagesOnImport = false, array $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'],): FileUtil
    {
        return new FileUtil(
            $connection ?? $this->createMock(Connection::class),
            $framework,
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $filesystem ?? $this->createMock(Filesystem::class),
            $requestStack ?? new RequestStack(),
            $security ?? $this->createMock(Security::class),
            $translator ?? $this->translator(),
            '/project',
            $copyImagesOnImport,
            $validExtensions,
            null,
        );
    }

    private function frameworkWithValidator(bool $isValid): ContaoFramework
    {
        $validator = $this->mockAdapter(['isValidFileName']);
        $validator
            ->method('isValidFileName')
            ->willReturn($isValid)
        ;

        return $this->mockContaoFramework([Validator::class => $validator]);
    }

    private function frameworkWithSanitize(callable $sanitize): ContaoFramework
    {
        $stringUtil = $this->mockAdapter(['sanitizeFileName']);
        $stringUtil
            ->method('sanitizeFileName')
            ->willReturnCallback($sanitize)
        ;

        return $this->mockContaoFramework([StringUtil::class => $stringUtil]);
    }

    private function frameworkForAddImage(GalleryCreatorPicturesModel $pictureModel): ContaoFramework
    {
        $objFolder = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/album']);

        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn($objFolder)
        ;

        $framework = $this->mockContaoFramework([
            FilesModel::class => $filesAdapter,
            Message::class => $this->mockAdapter(['addError']),
        ]);

        $framework
            ->method('createInstance')
            ->willReturnCallback(static fn (string $class, array $args = []) => GalleryCreatorPicturesModel::class === $class ? $pictureModel : null)
        ;

        return $framework;
    }

    private function securityWithUser(object|null $user): Security
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($user)
        ;

        return $security;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        return $translator;
    }

    private function requestStack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request());

        return $stack;
    }

    private function album(): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, [
            'id' => 5,
            'assignedDir' => 'dir-uuid',
            'preserveFilename' => true,
            'thumb' => 0,
            'date' => 123456,
            'name' => 'My Album',
        ]);
    }

    private function file(): File
    {
        $filesModel = $this->mockClassWithProperties(FilesModel::class, [
            'uuid' => 'file-uuid',
            'path' => 'files/album/x.jpg',
        ]);

        $file = $this->mockClassWithProperties(File::class, [
            'path' => 'files/album/x.jpg',
            'dirname' => 'files/album',
            'extension' => 'jpg',
        ]);
        $file->method('getModel')->willReturn($filesModel);

        return $file;
    }
}
