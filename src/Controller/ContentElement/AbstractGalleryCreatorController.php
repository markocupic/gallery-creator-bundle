<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Haste\Util\Pagination;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractGalleryCreatorController extends AbstractContentElementController
{
    private AlbumUtil $albumUtil;

    private Connection $connection;

    private PictureUtil $pictureUtil;


    private ScopeMatcher $scopeMatcher;

    public function __construct(AlbumUtil $albumUtil, Connection $connection, PictureUtil $pictureUtil, ScopeMatcher $scopeMatcher)
    {
        $this->albumUtil = $albumUtil;
        $this->connection = $connection;
        $this->pictureUtil = $pictureUtil;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * Add meta tags to the page header.
     */
    protected function addMetaTagsToPage(PageModel $pageModel, GalleryCreatorAlbumsModel $albumModel): void
    {
        $pageModel->description = '' !== $albumModel->description ? StringUtil::specialchars($albumModel->description) : StringUtil::specialchars($this->pageModel->description);
        $GLOBALS['TL_KEYWORDS'] = ltrim($GLOBALS['TL_KEYWORDS'].','.StringUtil::specialchars($albumModel->keywords), ',');
    }

    protected function triggerGenerateFrontendTemplateHook(Template $template, GalleryCreatorAlbumsModel $albumModel = null): void
    {
        // Trigger the galleryCreatorGenerateFrontendTemplate - HOOK
        if (isset($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'])) {
            foreach ($GLOBALS['TL_HOOKS']['galleryCreatorGenerateFrontendTemplate'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($this, $template, $albumModel);
            }
        }
    }

    /**
     * Augment template with some more properties of the active album.
     *
     * @throws DoctrineDBALException
     */
    protected function addAlbumToTemplate(GalleryCreatorAlbumsModel $albumModel, ContentModel $contentModel, Template $template, PageModel $pageModel): void
    {
        $template->album = $this->albumUtil->getAlbumData($albumModel, $contentModel);
    }

    /**
     * @param GalleryCreatorAlbumsModel $albumsModel
     * @param ContentModel $contentModel
     * @param $template
     * @param PageModel $pageModel
     * @return void
     * @throws DoctrineDBALDriverException
     * @throws DoctrineDBALException
     */
    protected function addAlbumPicturesToTemplate(GalleryCreatorAlbumsModel $albumsModel, ContentModel $contentModel, $template, PageModel $pageModel): void
    {
        // Count items
        $itemsTotal = $this->connection->fetchOne(
            'SELECT COUNT(id) AS itemsTotal FROM tl_gallery_creator_pictures WHERE published = ? AND pid = ?',
            ['1', $albumsModel->id]
        );

        $perPage = (int) $contentModel->gcThumbsPerPage;

        // Use Haste\Util\Pagination to generate the pagination.
        $pagination = new Pagination($itemsTotal, $perPage, 'page_g'.$contentModel->id);

        if ($pagination->isOutOfRange()) {
            throw new PageNotFoundException('Page not found/Out of pagination range exception: '.Environment::get('uri'));
        }

        $template->pagination = $pagination->generate();

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
            ->setParameter('pid', $albumsModel->id)
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit())
            ->execute()
        ;

        $arrPictures = [];

        while (false !== ($rowPicture = $stmt->fetchAssociative())) {
            $filesModel = FilesModel::findByUuid($rowPicture['uuid']);
            $basename = 'undefined';

            if (null !== $filesModel) {
                $basename = $filesModel->name;
            }

            if (null !== ($picturesModel = GalleryCreatorPicturesModel::findByPk($rowPicture['id']))) {
                // Prevent overriding items with same basename
                $arrPictures[$basename.'-id-'.$rowPicture['id']] = $this->pictureUtil->getPictureData($picturesModel, $contentModel);
            }
        }

        // Sort by name
        if ('name' === $contentModel->gcPictureSorting) {
            if ('ASC' === $contentModel->gcPictureSortingDirection) {
                uksort($arrPictures, static fn ($a, $b): int => strnatcasecmp(basename($a), basename($b)));
            } else {
                uksort($arrPictures, static fn ($a, $b): int => -strnatcasecmp(basename($a), basename($b)));
            }
        }

        // Add pictures to the template.
        $template->arrPictures = array_values($arrPictures);
    }
}
