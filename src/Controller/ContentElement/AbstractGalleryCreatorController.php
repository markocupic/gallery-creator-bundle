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

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractGalleryCreatorController extends AbstractContentElementController
{
    protected AlbumUtil $albumUtil;
    protected Connection $connection;
    protected HtmlDecoder $htmlDecoder;
    protected InsertTagParser $insertTagParser;
    protected MarkdownUtil $markdownUtil;
    protected PictureUtil $pictureUtil;
    protected RequestStack $requestStack;
    protected ResponseContextAccessor $responseContextAccessor;
    protected ScopeMatcher $scopeMatcher;
    protected SecurityUtil $securityUtil;
    protected Studio $studio;
    protected string $projectDir;

    public function __construct(DependencyAggregate $dependencyAggregate)
    {
        $this->albumUtil = $dependencyAggregate->albumUtil;
        $this->connection = $dependencyAggregate->connection;
        $this->htmlDecoder = $dependencyAggregate->htmlDecoder;
        $this->insertTagParser = $dependencyAggregate->insertTagParser;
        $this->markdownUtil = $dependencyAggregate->markdownUtil;
        $this->pictureUtil = $dependencyAggregate->pictureUtil;
        $this->projectDir = $dependencyAggregate->projectDir;
        $this->requestStack = $dependencyAggregate->requestStack;
        $this->responseContextAccessor = $dependencyAggregate->responseContextAccessor;
        $this->scopeMatcher = $dependencyAggregate->scopeMatcher;
        $this->securityUtil = $dependencyAggregate->securityUtil;
        $this->studio = $dependencyAggregate->studio;
    }

    public function overridePageMetaData(GalleryCreatorAlbumsModel $objAlbum): void
    {
        // Overwrite the page metadata (see #2853, #4955 and #87)
        $responseContext = $this->responseContextAccessor->getResponseContext();

        if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
            /** @var HtmlHeadBag $htmlHeadBag */
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);

            if ($objAlbum->pageTitle) {
                $htmlHeadBag->setTitle($objAlbum->pageTitle); // Already stored decoded
            } elseif ($objAlbum->title) {
                $htmlHeadBag->setTitle($this->htmlDecoder->inputEncodedToPlainText($objAlbum->title));
            }

            if ($objAlbum->description) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($objAlbum->description));
            } elseif ($objAlbum->teaser) {
                $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($objAlbum->teaser));
            }

            if ($objAlbum->robots) {
                $htmlHeadBag->setMetaRobots($objAlbum->robots);
            }
        }
    }

    public function getAlbumData(GalleryCreatorAlbumsModel $album, ContentModel $content): array
    {
        /** @var PageModel $page */
        $page = $this->requestStack->getCurrentRequest()->attributes->get('pageModel');

        // Count images
        $pictureCount = $this->connection
            ->fetchOne(
                'SELECT COUNT(id) AS pictureCount FROM tl_gallery_creator_pictures WHERE pid = ? AND published = ?',
                [$album->id, '1'],
            )
        ;

        // Image size
        $size = StringUtil::deserialize($content->gcSizeAlbumListing);
        $arrSize = !empty($size) && \is_array($size) ? $size : null;

        $params = '/'.$album->alias;
        $href = StringUtil::ampersand($page->getFrontendUrl($params));

        /** @var FilesModel $previewImage */
        $previewImage = $this->getAlbumPreviewThumb($album);

        $arrCssClasses = [];
        $arrCssClasses[] = 'gc-level-'.$this->albumUtil->getAlbumLevelFromPid((int) $album->pid);
        $arrCssClasses[] = GalleryCreatorAlbumsModel::hasChildAlbums((int) $album->id) ? 'gc-has-child-album' : null;
        $arrCssClasses[] = !$pictureCount ? 'gc-empty-album' : null;

        // Do not show child albums, in news elements
        if (GalleryCreatorNewsController::TYPE === $content->type) {
            $childAlbums = null;
        } else {
            $childAlbums = $this->getChildAlbums($album, $content, true);
        }

        $childAlbumCount = null !== $childAlbums ? \count($childAlbums) : 0;

        $strTeaser = $this->insertTagParser->replaceInline(nl2br((string) $album->teaser));
        $strCaption = $this->insertTagParser->replaceInline(nl2br((string) $album->caption));
        $strMarkdown = 'markdown' === $album->captionType && $album->markdownCaption ? $this->markdownUtil->parse($album->markdownCaption) : null;

        // Meta
        $arrMeta = [];
        $arrMeta['alt'] = StringUtil::specialchars($album->name);
        $arrMeta['caption'] = $strTeaser;
        $arrMeta['title'] = StringUtil::specialchars($album->name);

        // Compile list of images
        if ($previewImage) {
            $figure = $this->studio
                ->createFigureBuilder()
                ->setSize($arrSize)
                ->enableLightbox(false)
                ->setOverwriteMetadata(new Metadata($arrMeta))
                ->fromUuid($previewImage->uuid)
                ->setMetadata(new Metadata($arrMeta))
            ;
        }

        $arrAlbum = $album->row();
        $arrAlbum['teaser'] = $strTeaser;
        $arrAlbum['caption'] = $strCaption;
        $arrAlbum['markdownCaption'] = $strMarkdown ?: false;
        $arrAlbum['dateFormatted'] = Date::parse(Config::get('dateFormat'), $album->date);
        $arrAlbum['datimFormatted'] = Date::parse(Config::get('datimFormat'), $album->date);
        $arrAlbum['meta'] = new Metadata($arrMeta);
        $arrAlbum['href'] = $href;
        $arrAlbum['pictureCount'] = $pictureCount;
        $arrAlbum['cssClass'] = !(empty(implode(' ', array_filter($arrCssClasses)))) ? implode(' ', array_filter($arrCssClasses)) : false;
        $arrAlbum['hasChildAlbums'] = (bool) $childAlbumCount;
        $arrAlbum['childAlbumCount'] = $childAlbums ? \count($childAlbums) : 0;
        $arrAlbum['childAlbums'] = $childAlbums;
        $arrAlbum['figure'] = [
            'build' => isset($figure) ? $figure->build() : null,
            'uuid' => isset($figure) ? $previewImage->uuid : null,
            'size' => $arrSize,
            'enable_lightbox' => false,
            'meta_data' => new Metadata($arrMeta),
        ];

        return $arrAlbum;
    }

    public function getAlbumPreviewThumb(GalleryCreatorAlbumsModel $album): FilesModel|null
    {
        $picture = GalleryCreatorPicturesModel::findOneById($album->thumb);

        if (null === $picture || !$picture->published) {
            return null;
        }

        $files = FilesModel::findByUuid($picture->uuid);

        if (null === $files || !is_file($this->projectDir.'/'.$files->path)) {
            return null;
        }

        return $files;
    }

    /**
     * @throws Exception
     * @throws DoctrineDBALDriverException
     */
    public function getChildAlbums(GalleryCreatorAlbumsModel $album, ContentModel $content, bool $blnOnlyAllowed = false): array|null
    {
        $strSorting = $content->gcSorting.' '.$content->gcSortingDirection;

        $stmt = $this->connection->executeQuery(
            "SELECT * FROM tl_gallery_creator_albums WHERE pid = ? AND published = ? ORDER BY $strSorting",
            [$album->id, '1']
        );

        $arrChildren = [];

        while (false !== ($arrChild = $stmt->fetchAssociative())) {
            $objChild = GalleryCreatorAlbumsModel::findByPk($arrChild['id']);

            if ($blnOnlyAllowed) {
                if ($content->gcShowAlbumSelection) {
                    $arrAllowed = StringUtil::deserialize($content->gcAlbumSelection, true);

                    if (!\in_array($objChild->id, $arrAllowed, false)) {
                        continue;
                    }

                    if (!$this->securityUtil->isAuthorized($objChild)) {
                        continue;
                    }
                }
            }

            if (null !== $objChild) {
                $arrChildren[] = $this->getAlbumData($objChild, $content);
            }
        }

        return !empty($arrChildren) ? $arrChildren : null;
    }

    /**
     * Add meta tags to the page header.
     */
    protected function addMetaTagsToPage(PageModel $pageModel, GalleryCreatorAlbumsModel $album): void
    {
        $pageModel->description = '' !== $album->description ? StringUtil::specialchars($album->description) : StringUtil::specialchars($pageModel->description);
    }

    protected function triggerGenerateFrontendTemplateHook(FragmentTemplate $template, GalleryCreatorAlbumsModel $album = null): void
    {
        // Trigger the galleryCreatorGenerateFrontendTemplate - HOOK
        if (isset($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($this, $template, $album);
            }
        }
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $album, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        $template->set('album', $this->getAlbumData($album, $contentModel));
    }

    /**
     * @param $template
     *
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function addAlbumPicturesToTemplate(GalleryCreatorAlbumsModel $album, ContentModel $contentModel, FragmentTemplate $template, PageModel $pageModel): void
    {
        // Picture sorting
        $arrSorting = empty($contentModel->gcPictureSorting) || empty($contentModel->gcPictureSortingDirection) ? ['sorting', 'ASC'] : [$contentModel->gcPictureSorting, $contentModel->gcPictureSortingDirection];

        // Sort by name will be done below.
        $arrSorting[0] = str_replace('name', 'id', $arrSorting[0]);

        $stmt = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('tl_gallery_creator_pictures', 't')
            ->where('t.pid = :pid')
            ->andWhere('t.published = :published')
            ->orderBy(...$arrSorting)
            ->setParameter('published', '1')
            ->setParameter('pid', $album->id)
            ->executeQuery()
        ;

        $images = [];

        while (false !== ($rowPicture = $stmt->fetchAssociative())) {
            $filesModel = FilesModel::findByUuid($rowPicture['uuid']);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }

            if (null !== ($picture = GalleryCreatorPicturesModel::findByPk($rowPicture['id']))) {
                if ($picture->uuid && $this->pictureUtil->pictureExists($picture)) {
                    // Prevent overriding items with same basename
                    $images[$basename.'-id-'.$rowPicture['id']] = $this->pictureUtil->getPictureData($picture, $contentModel);
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
