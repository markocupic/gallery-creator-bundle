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

namespace Markocupic\GalleryCreatorBundle\Tests\Controller\ContentElement;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\AbstractGalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\Filesystem\Filesystem;

class AbstractGalleryCreatorControllerTest extends ContaoTestCase
{
    public function testGetAlbumPreviewThumbReturnsNullWhenThumbPictureIsMissing(): void
    {
        $controller = $this->controller($this->framework(null), $this->createMock(Filesystem::class));

        $this->assertNull($controller->getAlbumPreviewThumb($this->album()));
    }

    public function testGetAlbumPreviewThumbReturnsNullWhenPictureIsUnpublished(): void
    {
        $picture = $this->picture(published: false);
        $controller = $this->controller($this->framework($picture), $this->createMock(Filesystem::class));

        $this->assertNull($controller->getAlbumPreviewThumb($this->album()));
    }

    public function testGetAlbumPreviewThumbReturnsNullWhenFileModelIsMissing(): void
    {
        $picture = $this->picture(published: true);
        $controller = $this->controller($this->framework($picture, null), $this->createMock(Filesystem::class));

        $this->assertNull($controller->getAlbumPreviewThumb($this->album()));
    }

    public function testGetAlbumPreviewThumbReturnsNullWhenFileIsGoneFromDisk(): void
    {
        $picture = $this->picture(published: true);
        $files = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/preview.jpg']);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(false)
        ;

        $controller = $this->controller($this->framework($picture, $files), $filesystem);

        $this->assertNull($controller->getAlbumPreviewThumb($this->album()));
    }

    public function testGetAlbumPreviewThumbReturnsFilesModelOnSuccess(): void
    {
        $picture = $this->picture(published: true);
        $files = $this->mockClassWithProperties(FilesModel::class, ['path' => 'files/preview.jpg']);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(true)
        ;

        $controller = $this->controller($this->framework($picture, $files), $filesystem);

        $this->assertSame($files, $controller->getAlbumPreviewThumb($this->album()));
    }

    /**
     * getAlbumPreviewThumb() lives on the abstract controller and only touches
     * $this->framework, $this->filesystem and $this->projectDir. We therefore
     * instantiate the concrete controller without its (large) constructor and
     * inject just those three readonly properties via reflection.
     */
    private function controller(ContaoFramework $framework, Filesystem $filesystem, string $projectDir = '/project'): GalleryCreatorController
    {
        $controller = (new \ReflectionClass(GalleryCreatorController::class))->newInstanceWithoutConstructor();

        $this->setProperty($controller, 'framework', $framework);
        $this->setProperty($controller, 'filesystem', $filesystem);
        $this->setProperty($controller, 'projectDir', $projectDir);

        return $controller;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        // Readonly properties may only be initialized from the scope of the class that
        // declares them, so build the ReflectionProperty from the abstract controller.
        $property = new \ReflectionProperty(AbstractGalleryCreatorController::class, $name);
        $property->setValue($object, $value);
    }

    private function framework(object|null $picturesModel, object|null $filesModel = null): ContaoFramework
    {
        $picturesAdapter = $this->mockAdapter(['findOneById']);
        $picturesAdapter
            ->method('findOneById')
            ->willReturn($picturesModel)
        ;

        $filesAdapter = $this->mockAdapter(['findByUuid']);
        $filesAdapter
            ->method('findByUuid')
            ->willReturn($filesModel)
        ;

        return $this->mockContaoFramework([
            GalleryCreatorPicturesModel::class => $picturesAdapter,
            FilesModel::class => $filesAdapter,
        ]);
    }

    private function album(): GalleryCreatorAlbumsModel
    {
        return $this->mockClassWithProperties(GalleryCreatorAlbumsModel::class, ['thumb' => 7]);
    }

    private function picture(bool $published): GalleryCreatorPicturesModel
    {
        return $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, [
            'published' => $published,
            'uuid' => 'pic-uuid',
        ]);
    }
}
