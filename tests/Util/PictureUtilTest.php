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

use Contao\ContentModel;
use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\File\MetadataBag;
use Contao\CoreBundle\Filesystem\ExtraMetadata;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\FigureBuilder;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\GalleryCreatorBundle\Filesystem\FilesystemItemResolver;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Path;

class PictureUtilTest extends ContaoTestCase
{
    public function testPictureExistsReturnsTrueForFile(): void
    {
        $item = $this->createMock(FilesystemItem::class);
        $item
            ->method('isFile')
            ->willReturn(true)
        ;

        $util = $this->getPictureUtil(resolverItem: $item);

        $this->assertTrue($util->pictureExists($this->mockPicturesModel()));
    }

    public function testPictureExistsReturnsFalseWhenItemMissing(): void
    {
        $util = $this->getPictureUtil(resolverItem: null);

        $this->assertFalse($util->pictureExists($this->mockPicturesModel()));
    }

    public function testPictureExistsReturnsFalseWhenItemIsNotFile(): void
    {
        $item = $this->createMock(FilesystemItem::class);
        $item
            ->method('isFile')
            ->willReturn(false)
        ;

        $util = $this->getPictureUtil(resolverItem: $item);

        $this->assertFalse($util->pictureExists($this->mockPicturesModel()));
    }

    public function testGetPictureDataReturnsNullWhenFilesModelMissing(): void
    {
        $framework = $this->mockContaoFramework([
            FilesModel::class => $this->mockAdapter(['findByUuid']),
        ]);

        $util = $this->getPictureUtil(framework: $framework);

        $this->assertNull(
            $util->getPictureData($this->mockPicturesModel(), $this->mockContentModel()),
        );
    }

    public function testGetPictureDataBuildsFigureFromFilesModelUuid(): void
    {
        $framework = $this->mockFrameworkWithFilesModels();

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->once())
            ->method('fromUuid')
            ->with('pic-uuid')
        ;

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
        );

        $util->getPictureData($this->mockPicturesModel(), $this->mockContentModel());
    }

    public function testGetPictureDataBuildsFigureFromCustomThumb(): void
    {
        $framework = $this->mockFrameworkWithFilesModels();

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->once())
            ->method('fromUuid')
            ->with('custom-thumb-uuid')
        ;

        $picturesModel = $this->mockPicturesModel([
            'addCustomThumb' => true,
            'customThumb' => 'custom-thumb-uuid',
        ]);

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
        );

        $dto = $util->getPictureData($picturesModel, $this->mockContentModel());

        $this->assertInstanceOf(Figure::class, $dto->figure);
    }

    public function testGetPictureDataUsesSocialMediaSrcAsLinkHref(): void
    {
        $framework = $this->mockFrameworkWithFilesModels();

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->once())
            ->method('setLinkHref')
            ->with('https://youtu.be/abc123')
        ;

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
        );

        $dto = $util->getPictureData(
            $this->mockPicturesModel(['socialMediaSRC' => 'https://youtu.be/abc123']),
            $this->mockContentModel(),
        );

        $this->assertSame('https://youtu.be/abc123', $dto->socialMediaSrc);
        $this->assertNull($dto->localMediaModel);
    }

    public function testGetPictureDataUsesLocalMediaPathAsLinkHref(): void
    {
        $localMediaModel = $this->mockClassWithProperties(FilesModel::class, [
            'uuid' => 'local-uuid',
            'path' => 'files/movie.mp4',
        ]);

        $framework = $this->mockFrameworkWithFilesModels(['local-uuid' => $localMediaModel]);

        $filesContext = $this->createMock(ContaoContext::class);
        $filesContext
            ->method('getStaticUrl')
            ->willReturn('https://cdn.example.com/')
        ;

        $expectedHref = Path::join('https://cdn.example.com/', 'files/movie.mp4');

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->once())
            ->method('setLinkHref')
            ->with($expectedHref)
        ;

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
            filesContext: $filesContext,
        );

        $dto = $util->getPictureData(
            $this->mockPicturesModel(['localMediaSRC' => 'local-uuid']),
            $this->mockContentModel(),
        );

        $this->assertSame($localMediaModel, $dto->localMediaModel);
        $this->assertSame('', $dto->socialMediaSrc);
    }

    public function testGetPictureDataPrefersSocialMediaOverLocalMedia(): void
    {
        $localMediaModel = $this->mockClassWithProperties(FilesModel::class, [
            'uuid' => 'local-uuid',
            'path' => 'files/movie.mp4',
        ]);

        $framework = $this->mockFrameworkWithFilesModels(['local-uuid' => $localMediaModel]);

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->once())
            ->method('setLinkHref')
            ->with('https://vimeo.com/12345')
        ;

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
        );

        $dto = $util->getPictureData(
            $this->mockPicturesModel([
                'socialMediaSRC' => 'https://vimeo.com/12345',
                'localMediaSRC' => 'local-uuid',
            ]),
            $this->mockContentModel(),
        );

        $this->assertSame('https://vimeo.com/12345', $dto->socialMediaSrc);
    }

    public function testGetPictureDataOmitsLinkHrefWithoutMedia(): void
    {
        $framework = $this->mockFrameworkWithFilesModels();

        $figureBuilder = $this->mockFigureBuilder();
        $figureBuilder
            ->expects($this->never())
            ->method('setLinkHref')
        ;

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($figureBuilder),
            resolverItem: $this->mockFilesystemItem(null),
        );

        $dto = $util->getPictureData($this->mockPicturesModel(), $this->mockContentModel());

        $this->assertNull($dto->localMediaModel);
        $this->assertSame('', $dto->socialMediaSrc);
    }

    /**
     * @param array<string, mixed>       $picProps
     * @param array<string, string>|null $fileMeta
     */
    #[DataProvider('metadataProvider')]
    public function testGetMetadataResolvesTitleAndCaption(array $picProps, array|null $fileMeta, string $expectedTitle, string $expectedCaption): void
    {
        $framework = $this->mockFrameworkWithFilesModels();

        $util = $this->getPictureUtil(
            framework: $framework,
            studio: $this->mockStudio($this->mockFigureBuilder()),
            resolverItem: $this->mockFilesystemItem($fileMeta),
        );

        $dto = $util->getPictureData($this->mockPicturesModel($picProps), $this->mockContentModel());

        $this->assertSame($expectedTitle, $dto->metadata->getTitle());
        $this->assertSame($expectedCaption, $dto->metadata->getCaption());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>|null, string, string}>
     */
    public static function metadataProvider(): iterable
    {
        yield 'picture title wins' => [
            ['title' => 'Pic Title', 'caption' => ''],
            null,
            'Pic Title',
            '',
        ];

        yield 'caption fills empty title' => [
            ['title' => '', 'caption' => 'Pic Caption'],
            null,
            'Pic Caption',
            'Pic Caption',
        ];

        yield 'falls back to file metadata' => [
            ['title' => '', 'caption' => ''],
            ['title' => 'Meta Title', 'caption' => 'Meta Caption'],
            'Meta Title',
            'Meta Caption',
        ];

        yield 'picture title over file metadata, caption from file' => [
            ['title' => 'Pic Title', 'caption' => ''],
            ['title' => 'Meta Title', 'caption' => 'Meta Caption'],
            'Pic Title',
            'Meta Caption',
        ];

        yield 'everything empty' => [
            ['title' => '', 'caption' => ''],
            null,
            '',
            '',
        ];
    }

    private function getPictureUtil(ContaoFramework|null $framework = null, Studio|null $studio = null, FilesystemItem|null $resolverItem = null, ContaoContext|null $filesContext = null, string $projectDir = '/project'): PictureUtil
    {
        $resolver = $this->createMock(FilesystemItemResolver::class);
        $resolver
            ->method('first')
            ->willReturn($resolverItem)
        ;

        return new PictureUtil(
            filesContext: $filesContext ?? $this->createMock(ContaoContext::class),
            framework: $framework ?? $this->mockContaoFramework(),
            filesystemItemResolver: $resolver,
            studio: $studio ?? $this->createMock(Studio::class),
            projectDir: $projectDir,
        );
    }

    /**
     * @param array<string, FilesModel> $extra Zusätzliche uuid => FilesModel-Zuordnungen
     */
    private function mockFrameworkWithFilesModels(array $extra = []): ContaoFramework
    {
        $map = array_merge(
            [
                'pic-uuid' => $this->mockClassWithProperties(FilesModel::class, [
                    'uuid' => 'pic-uuid',
                    'path' => 'files/foo.jpg',
                ]),
            ],
            $extra,
        );

        $adapter = $this->mockAdapter(['findByUuid']);
        $adapter
            ->method('findByUuid')
            ->willReturnCallback(static fn ($uuid) => $map[$uuid] ?? null)
        ;

        return $this->mockContaoFramework([FilesModel::class => $adapter]);
    }

    private function mockStudio(FigureBuilder $figureBuilder): Studio
    {
        $studio = $this->createMock(Studio::class);
        $studio
            ->method('createFigureBuilder')
            ->willReturn($figureBuilder)
        ;

        return $studio;
    }

    private function mockFigureBuilder(): FigureBuilder
    {
        $figureBuilder = $this->createMock(FigureBuilder::class);

        // Nur die verketteten Aufrufe müssen $this zurückgeben.
        foreach (['setSize', 'setLightboxGroupIdentifier', 'enableLightbox', 'setOverwriteMetadata', 'setMetadata'] as $method) {
            $figureBuilder
                ->method($method)
                ->willReturnSelf()
            ;
        }

        // fromUuid()/setLinkHref() werden als Statements aufgerufen (Rückgabe
        // ungenutzt) und bleiben ungestubbt, damit Tests eigene expects() setzen.
        $figureBuilder
            ->method('build')
            ->willReturn($this->dummyFigure())
        ;

        return $figureBuilder;
    }

    private function dummyFigure(): Figure
    {
        // Figure ist final und lässt sich nicht mocken; wir brauchen nur eine
        // Instanz als Rückgabewert, ohne sie je zu rendern.
        return (new \ReflectionClass(Figure::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, string>|null $fileMeta
     */
    private function mockFilesystemItem(array|null $fileMeta): FilesystemItem
    {
        // Metadata ist final -> real instanziieren. ExtraMetadata/MetadataBag ggf.
        // an deine Contao-Version anpassen, falls sie final sind.
        $bag = $this->createMock(MetadataBag::class);
        $bag
            ->method('getDefault')
            ->willReturn(null === $fileMeta ? null : new Metadata($fileMeta))
        ;

        $extra = $this->createMock(ExtraMetadata::class);
        $extra
            ->method('getLocalized')
            ->willReturn($bag)
        ;

        $item = $this->createMock(FilesystemItem::class);
        $item
            ->method('isFile')
            ->willReturn(true)
        ;

        $item
            ->method('getExtraMetadata')
            ->willReturn($extra)
        ;

        return $item;
    }

    private function mockContentModel(): ContentModel
    {
        return $this->mockClassWithProperties(ContentModel::class, [
            'id' => 7,
            'gcSizeDetailView' => serialize([100, 100, 'crop']),
            'gcFullSize' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function mockPicturesModel(array $props = []): GalleryCreatorPicturesModel
    {
        return $this->mockClassWithProperties(GalleryCreatorPicturesModel::class, array_merge([
            'id' => 1,
            'uuid' => 'pic-uuid',
            'socialMediaSRC' => '',
            'localMediaSRC' => '',
            'title' => '',
            'caption' => '',
            'addCustomThumb' => false,
            'customThumb' => '',
        ], $props));
    }
}
