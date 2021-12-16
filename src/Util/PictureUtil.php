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

namespace Markocupic\GalleryCreatorBundle\Util;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\File;
use Contao\FilesModel;
use Contao\Frontend;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Component\HttpFoundation\RequestStack;

class PictureUtil
{
    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(ScopeMatcher $scopeMatcher, RequestStack $requestStack)
    {
        $this->scopeMatcher = $scopeMatcher;
        $this->requestStack = $requestStack;
    }

    /**
     * @throws \Exception
     */
    public function getPictureData(GalleryCreatorPicturesModel $pictureModel, ContentModel $contentElementModel): ?array
    {
        global $objPage;

        $tlFilesUrl = System::getContainer()->get('contao.assets.files_context')->getStaticUrl();

        $request = $this->requestStack->getCurrentRequest();

        if (null === ($filesModel = FilesModel::findByUuid($pictureModel->uuid))) {
            return null;
        }

        if (!is_file($filesModel->getAbsolutePath())) {
            return null;
        }

        // Meta
        $arrMeta = Frontend::getMetaData($filesModel->meta, $objPage->language);

        if (empty($arrMeta['title'])) {
            $arrMeta['title'] = $filesModel->name;
        }

        $arrMeta['title'] = $pictureModel->caption ?? $arrMeta['title'];

        // Get thumb dimensions
        $arrSize = StringUtil::deserialize($contentElementModel->gcSizeDetailView);

        $href = null;
        $localMediaSrc = null;
        $socialMediaSrc = null;

        if ($request && $this->scopeMatcher->isFrontendRequest($request)) {
            // e.g. youtube or vimeo
            $href = $pictureModel->socialMediaSRC ?: null;
            $socialMediaSrc = $pictureModel->socialMediaSRC ?: null;

            // Local media
            if (null !== ($objMovieFile = FilesModel::findByUuid($pictureModel->localMediaSRC))) {
                $href = $objMovieFile->path;
                $localMediaSrc = $tlFilesUrl.$objMovieFile->path;
            }

            // Simply display the image as default
            if (!$href && $contentElementModel->gcFullsize) {
                $href = $filesModel->path;
            }

            $href = $href ? $tlFilesUrl.$href : null;
        }

        // CssID
        $cssID = StringUtil::deserialize($pictureModel->cssID, true);

        // Build the array
        return [
            'ownerModel' => UserModel::findByPk($pictureModel->owner),
            'filesModel' => $filesModel,
            'pictureModel' => $pictureModel,
            'albumModel' => $pictureModel->getRelated('pid'),
            'meta' => $arrMeta,
            'href' => $href,
            'localMediaSrc' => $localMediaSrc,
            'socialMediaSrc' => $socialMediaSrc,
            'exif' => Config::get('gc_read_exif') ? $this->getExif(new File($filesModel->path)) : [],
            'cssID' => '' !== $cssID[0] ? $cssID[0] : '',
            'cssClass' => '' !== $cssID[1] ? $cssID[1] : '',
            'singleImageUrl' => StringUtil::ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/').Input::get('items').'/img/'.$filesModel->name, $objPage->language)),
            'figureUuid' => $pictureModel->addCustomThumb ? $pictureModel->customThumb : $filesModel->uuid,
            'figureSize' => !empty($arrSize) ? $arrSize : null,
            'figureHref' => $href,
            'figureOptions' => [
                'metadata' => new Metadata($arrMeta),
                'enableLightbox' => (bool) $contentElementModel->gcFullsize,
                'lightboxGroupIdentifier' => sprintf('data-lightbox="lb%s"', $pictureModel->pid),
                //'lightboxSize' => '_big_size',
                'linkHref' => $href,
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function getExif(File $file): array
    {
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Exif
        try {
            $exif = \is_callable('exif_read_data') ? exif_read_data($projectDir.'/'.$file->path) : ['info' => "The function 'exif_read_data()' is not available on this server."];
        } catch (\Exception $e) {
            $exif = ['info' => "The function 'exif_read_data()' is not available on this server."];
        }

        return $exif;
    }
}
