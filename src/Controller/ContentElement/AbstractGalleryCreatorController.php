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

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Dto\GalleryAlbumDto;
use Markocupic\GalleryCreatorBundle\Listing\AlbumListingService;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractGalleryCreatorController extends AbstractContentElementController
{
    public function __construct(
        protected readonly AlbumListingService $albumListingService,
        protected readonly AlbumUtil $albumUtil,
        protected readonly Connection $connection,
        protected readonly ContaoFramework $framework,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly Filesystem $filesystem,
        protected readonly HtmlDecoder $htmlDecoder,
        protected readonly InsertTagParser $insertTagParser,
        protected readonly MarkdownUtil $markdownUtil,
        protected readonly PictureUtil $pictureUtil,
        protected readonly RequestStack $requestStack,
        protected readonly ResponseContextAccessor $responseContextAccessor,
        protected readonly SecurityUtil $securityUtil,
        protected readonly Studio $studio,
        protected readonly string $projectDir,
    ) {
    }

    public function overridePageMetaData(GalleryCreatorAlbumsModel $albumModel): void
    {
        $responseContext = $this->responseContextAccessor->getResponseContext();

        if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
            /** @var HtmlHeadBag $htmlHeadBag */
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);

            if ($albumModel->pageTitle) {
                $htmlHeadBag->setTitle($albumModel->pageTitle); // Already stored decoded
            } elseif ($albumModel->title) {
                $htmlHeadBag->setTitle($this->htmlDecoder->inputEncodedToPlainText($albumModel->title));
            }

            if ($albumModel->description) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($albumModel->description));
            } elseif ($albumModel->teaser) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($albumModel->teaser));
            }

            if ($albumModel->robots) {
                $htmlHeadBag->setMetaRobots($albumModel->robots);
            }
        }
    }

    public function getAlbumData(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel): GalleryAlbumDto
    {
        /** @var PageModel $pageModel */
        $pageModel = $this->requestStack->getCurrentRequest()->attributes->get('pageModel');

        // Count images
        $pictureCount = $this->connection
            ->fetchOne(
                'SELECT COUNT(id) AS pictureCount FROM tl_gallery_creator_pictures WHERE pid = ? AND published = ?',
                [
                    $albumModel->id,
                    1,
                ],
            )
        ;

        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Image size
        $size = $stringUtilAdapter->deserialize($contentModel->gcSizeAlbumListing);
        $arrSize = !empty($size) && \is_array($size) ? $size : null;

        $params = '/'.$albumModel->alias;
        $href = $stringUtilAdapter->ampersand($pageModel->getFrontendUrl($params));

        /** @var FilesModel $previewImage */
        $previewImage = $this->getAlbumPreviewThumb($albumModel);

        $arrCssClasses = [];
        $arrCssClasses[] = 'gc-level-'.$this->albumUtil->getAlbumLevelFromPid($albumModel->pid);
        $arrCssClasses[] = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class)->hasChildAlbums($albumModel->id) ? 'gc-has-child-album' : null;
        $arrCssClasses[] = !$pictureCount ? 'gc-empty-album' : null;

        // Do not show child albums, in news elements
        if (GalleryCreatorNewsController::TYPE === $contentModel->type) {
            $childAlbums = null;
        } else {
            $childAlbums = $this->getChildAlbums($albumModel, $contentModel, true);
        }

        $childAlbumCount = null !== $childAlbums ? \count($childAlbums) : 0;

        $teaserText = $this->insertTagParser->replaceInline(nl2br((string) $albumModel->teaser));
        $captionText = $this->insertTagParser->replaceInline(nl2br((string) $albumModel->caption));
        $markdownText = 'markdown' === $albumModel->captionType && $albumModel->markdownCaption ? $this->markdownUtil->parse($albumModel->markdownCaption) : null;

        // Meta
        $arrMeta = [];
        $arrMeta['alt'] = $albumModel->name;
        $arrMeta['caption'] = $teaserText;
        $arrMeta['title'] = $albumModel->name;
        $metadata = new Metadata($arrMeta);

        // Build the figure
        $figure = null;

        if ($previewImage) {
            $figure = $this->studio
                ->createFigureBuilder()
                ->setSize($arrSize)
                ->enableLightbox(false)
                ->setOverwriteMetadata($metadata)
                ->fromUuid($previewImage->uuid)
                ->setMetadata($metadata)
                ->build()
            ;
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        $arrAlbum = $albumModel->row();
        $arrAlbum['teaser'] = $teaserText;
        $arrAlbum['caption'] = $captionText;
        $arrAlbum['markdownCaption'] = $markdownText ?: false;
        $arrAlbum['dateFormatted'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $albumModel->date);
        $arrAlbum['datimFormatted'] = $dateAdapter->parse($configAdapter->get('datimFormat'), $albumModel->date);
        $arrAlbum['metadata'] = $metadata;
        $arrAlbum['href'] = $href;
        $arrAlbum['pictureCount'] = $pictureCount;
        $arrAlbum['cssClass'] = !(empty(implode(' ', array_filter($arrCssClasses)))) ? implode(' ', array_filter($arrCssClasses)) : false;
        $arrAlbum['hasChildAlbums'] = (bool) $childAlbumCount;
        $arrAlbum['childAlbumCount'] = $childAlbums ? \count($childAlbums) : 0;
        $arrAlbum['childAlbums'] = $childAlbums;

        return new GalleryAlbumDto(
            figure: $figure,
            metadata: $metadata,
            data: $arrAlbum,
        );
    }

    public function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $albumModel): FilesModel|null
    {
        $picturesModel = $this->framework->getAdapter(GalleryCreatorPicturesModel::class)->findOneById($albumModel->thumb);

        if (null === $picturesModel || !$picturesModel->published) {
            return null;
        }

        $files = $this->framework->getAdapter(FilesModel::class)->findByUuid($picturesModel->uuid);

        if (null === $files || !$this->filesystem->exists(Path::makeAbsolute($files->path, $this->projectDir))) {
            return null;
        }

        return $files;
    }

    /**
     * @throws Exception
     * @throws DoctrineDBALDriverException
     */
    public function getChildAlbums(GalleryCreatorAlbumsModel $albumModel, ContentModel $content, bool $blnOnlyAllowed = false): array|null
    {
        $albumsAdapter = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);

        $arrChildren = [];

        foreach ($this->albumListingService->getSortedAlbumIds($content, (int) $albumModel->id) as $id) {
            $childAlbum = $albumsAdapter->findById($id);

            if (null === $childAlbum) {
                continue;
            }

            // Skip albums outside the selection or protected from the current user
            if ($blnOnlyAllowed && (!$this->albumListingService->isInSelection($content, $childAlbum) || !$this->securityUtil->isAuthorized($childAlbum))) {
                continue;
            }

            $arrChildren[] = $this->getAlbumData($childAlbum, $content);
        }

        return !empty($arrChildren) ? $arrChildren : null;
    }

    /**
     * Add meta tags to the page header.
     */
    protected function addMetaTagsToPage(PageModel $pageModel, GalleryCreatorAlbumsModel $albumModel): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $pageModel->description = '' !== $albumModel->description ? $stringUtilAdapter->specialchars($albumModel->description) : $stringUtilAdapter->specialchars($pageModel->description);
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        $template->set('album', $this->getAlbumData($albumModel, $contentModel));
    }

    /**
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function addAlbumPicturesToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, FragmentTemplate $template): void
    {
        // Picture sorting
        $arrSorting = match (true) {
            empty($contentModel->gcPictureSorting), empty($contentModel->gcPictureSortingDirection) => ['sorting', 'ASC'],
            default => [$contentModel->gcPictureSorting, $contentModel->gcPictureSortingDirection],
        };

        // Sort by name will be done below.
        $arrSorting[0] = str_replace('name', 'id', $arrSorting[0]);

        $dataPictures = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tl_gallery_creator_pictures', 't')
            ->where('t.pid = :pid')
            ->andWhere('t.published = :published')
            ->orderBy(...$arrSorting)
            ->setParameter('published', 1)
            ->setParameter('pid', $albumModel->id)
            ->fetchAllAssociative()
        ;

        $images = [];

        foreach ($dataPictures as $dataPicture) {
            $filesModel = $this->framework->getAdapter(FilesModel::class)->findByUuid($dataPicture['uuid']);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }

            if (null !== ($picturesModel = $this->framework->getAdapter(GalleryCreatorPicturesModel::class)->findById($dataPicture['id']))) {
                if ($picturesModel->uuid && $this->pictureUtil->pictureExists($picturesModel)) {
                    // Prevent overriding items with same basename
                    $images[$basename.'-id-'.$dataPicture['id']] = $this->pictureUtil->getPictureData($picturesModel, $contentModel);
                }
            }
        }

        // Sort by name
        if ('name' === $contentModel->gcPictureSorting) {
            if ('ASC' === $contentModel->gcPictureSortingDirection) {
                uksort($images, static fn ($a, $b): int => strnatcasecmp(basename($a), basename($b)));
            } else {
                uksort($images, static fn ($a, $b): int => -strnatcasecmp(basename($a), basename($b)));
            }
        }

        // Add pictures to the template.
        $template->set('images', array_values($images));
    }
}
