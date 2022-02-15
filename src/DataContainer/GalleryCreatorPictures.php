<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Backend;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class GalleryCreatorPictures
{
    private RequestStack $requestStack;
    private Connection $connection;
    private FileUtil $fileUtil;
    private Security $security;
    private TranslatorInterface $translator;
    private TwigEnvironment $twig;
    private string $projectDir;
    private bool $galleryCreatorBackendWriteProtection;
    private string $galleryCreatorUploadPath;
    private ?LoggerInterface $logger;

    public function __construct(RequestStack $requestStack, Connection $connection, FileUtil $fileUtil, Security $security, TranslatorInterface $translator, TwigEnvironment $twig, string $projectDir, bool $galleryCreatorBackendWriteProtection, string $galleryCreatorUploadPath, LoggerInterface $logger = null)
    {
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->fileUtil = $fileUtil;
        $this->security = $security;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->projectDir = $projectDir;
        $this->galleryCreatorBackendWriteProtection = $galleryCreatorBackendWriteProtection;
        $this->galleryCreatorUploadPath = $galleryCreatorUploadPath;
        $this->logger = $logger;
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.onload", priority=110)
     */
    public function setPermissions(): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        switch ($request->query->get('act')) {
            case 'create':
                // New images can only be implemented via an image upload
                Controller::redirect(sprintf(
                    'contao?do=gallery_creator&amp;table=tl_gallery_creator_pictures&amp;id=%s',
                    $request->query->get('pid')
                ));

                // no break
            case 'select':
                if (!$user->isAdmin) {
                    // Only list pictures where user is the picture owner
                    if ($this->galleryCreatorBackendWriteProtection) {
                        $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['list']['sorting']['filter'] = [['owner = ?', $user->id]];
                    }
                }

                break;

            default:
                break;
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        // Set the correct referer when redirecting from file import
        if ($request->query->has('filesImported')) {
            $arrReferer = $session->get('referer');
            $refererId = $request->attributes->get('_contao_referer_id');
            $arrReferer[$refererId]['current'] = 'contao?do=gallery_creator';
            $session->set('referer', $arrReferer);
        }
    }

    /**
     * Onload callback.
     *
     * @throws \Exception
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.onload", priority=90)
     */
    public function route(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc || !$request->query->has('key')) {
            return;
        }

        $key = $request->query->get('key');

        switch ($key) {
            case 'imagerotate':

                if (!$this->isRestrictedUser($dc->id)) {
                    $picturesModel = GalleryCreatorPicturesModel::findByPk($dc->id);
                    $filesModel = FilesModel::findByUuid($picturesModel->uuid);

                    if (null !== $picturesModel && null !== $filesModel) {
                        $file = new File($filesModel->path);

                        // Rotate image anticlockwise
                        $this->fileUtil->imageRotate($file, 270);
                        Dbafs::addResource($filesModel->path, true);

                        Controller::redirect(System::getReferer());
                    }
                }

                break;

            default:
                break;
        }
    }

    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="list.operations.edit.button", priority=100)
     * @Callback(table="tl_gallery_creator_pictures", target="list.operations.delete.button", priority=100)
     * @Callback(table="tl_gallery_creator_pictures", target="list.operations.cut.button", priority=100)
     * @Callback(table="tl_gallery_creator_pictures", target="list.operations.imagerotate.button", priority=100)
     */
    public function buttonCallback(array $row, ?string $href, ?string $label, ?string $title, ?string $icon, ?string $attributes): string
    {
        if (!$this->isRestrictedUser($row['id'])) {
            return sprintf(
                '<a href="%s" title="%s"%s>%s</a> ',
                Backend::addToUrl($href.'&amp;id='.$row['id']),
                StringUtil::specialchars($title),
                $attributes,
                Image::getHtml($icon, $label),
            );
        }

        return Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Child record callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="list.sorting.child_record", priority=100)
     */
    public function childRecordCb(array $arrRow): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $key = $arrRow['published'] ? 'published' : 'unpublished';

        $filesModel = FilesModel::findByUuid($arrRow['uuid']);

        if (!is_file($this->projectDir.'/'.$filesModel->path)) {
            return '';
        }

        $file = new File($filesModel->path);

        // If data record contains a link to a movie file...
        $hasMovie = null;
        $src = $file->path;

        // Has social media?
        $src = $arrRow['socialMediaSRC'] ?: $src;

        // Local media (movies, etc.)
        $lmSRC = null;

        if (Validator::isBinaryUuid($arrRow['localMediaSRC'])) {
            if (null !== ($lmSRC = FilesModel::findByUuid($arrRow['localMediaSRC']))) {
                $src = $lmSRC->path;
            }
        }

        if ($arrRow['socialMediaSRC'] || $lmSRC) {
            $type = empty(trim((string) $arrRow['localMediaSRC'])) ? $this->translator->trans('GALLERY_CREATOR.localMedia', [], 'contao_default') : $this->translator->trans('GALLERY_CREATOR.socialMedia', [], 'contao_default');
            $iconSrc = 'bundles/markocupicgallerycreator/images/movie.svg';
            $hasMovie = sprintf(
                '<div class="block"><img src="%s" width="18" height="18"> <span style="color:darkred; font-weight:500">%s:</span> <a href="%s" data-lightbox="gc_album_%s">%s</a></div>',
                $iconSrc,
                $type,
                $src,
                $request->query->get('id'),
                $src,
            );
        }

        $blnShowThumb = false;
        $src = '';

        // Generate icon/thumbnail
        if (Config::get('thumbnails') && null !== $filesModel) {
            $blnShowThumb = true;

            $src = (new Image(new File($filesModel->path)))
                ->setTargetWidth(100)
                ->setResizeMode('center_center')
                ->executeResize()->getResizedPath();
        }

        $return = sprintf('<div class="cte_type %s"><strong>%s</strong> - %s [%s x %s px, %s]</div>', $key, $arrRow['headline'] ?? '', basename($filesModel->path), $file->width, $file->height, Backend::getReadableSize($file->filesize));
        $return .= $hasMovie;
        $return .= $blnShowThumb ? '<div class="block"><img src="'.$src.'" width="100"></div>' : null;
        $return .= sprintf('<div class="limit_height%s block">%s</div>', (Config::get('thumbnails') ? ' h64' : ''), StringUtil::specialchars($arrRow['caption']));

        return $return;
    }

    /**
     * Move file to the correct directory, when cutting & pasting images
     * from one album into another.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.oncut", priority=100)
     */
    public function onCutCb(DataContainer $dc): void
    {
        $picture = GalleryCreatorPicturesModel::findByPk($dc->id);

        $album = $picture->getRelated('pid');

        $sourcePath = FilesModel::findByUuid($picture->uuid)->path;
        $targetPath = FilesModel::findByUuid($album->assignedDir)->path.'/'.basename($sourcePath);

        if ($sourcePath === $targetPath) {
            return;
        }

        // Return if it is an external file
        if (false === strpos($sourcePath, $this->galleryCreatorUploadPath)) {
            return;
        }

        $file = new File($sourcePath);

        // Move file to the target folder
        if ($file->renameTo($targetPath)) {
            $picture->path = $targetPath;
            $picture->save();
        }
    }

    /**
     * Input field callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="fields.picture.input_field")
     */
    public function inputFieldCbPicture(DataContainer $dc): string
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $filesModel = FilesModel::findByUuid($objImg->uuid);

        if (null !== $filesModel) {
            $src = (new Image(new File($filesModel->path)))
                ->setTargetWidth(380)
                ->setResizeMode('proportional')
                ->executeResize()->getResizedPath();

            return (new Response(
                $this->twig->render(
                    '@MarkocupicGalleryCreator/Backend/picture.html.twig',
                    [
                        'basename' => basename($filesModel->path),
                        'img_src' => $src,
                    ]
                )
            ))->getContent();
        }

        return '';
    }

    /**
     * Input field callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="fields.imageInfo.input_field")
     */
    public function inputFieldCbImageInfo(DataContainer $dc): string
    {
        $picturesModel = GalleryCreatorPicturesModel::findByPk($dc->id);
        $userModel = UserModel::findByPk($picturesModel->owner);
        $filesModel = FilesModel::findByUuid($picturesModel->uuid);
        $objSocial = FilesModel::findByUuid($picturesModel->socialMediaSRC);
        $objLocal = FilesModel::findByUuid($picturesModel->localMediaSRC);
        $picturesModel->video_href_social = $objSocial ? $objSocial->path : '';
        $picturesModel->video_href_local = $objLocal ? $objLocal->path : '';
        $picturesModel->path = $filesModel->path;
        $picturesModel->filename = basename($filesModel->path);
        $picturesModel->date_formatted = Date::parse('Y-m-d', $picturesModel->date);
        $picturesModel->owner_name = '' === $userModel->name ? '---' : $userModel->name;

        $translator = System::getContainer()->get('translator');

        return (new Response(
            $this->twig->render(
                '@MarkocupicGalleryCreator/Backend/image_information.html.twig',
                [
                    'model' => $picturesModel->row(),
                    'trans' => [
                        'picture_id' => $translator->trans('tl_gallery_creator_pictures.id.0', [], 'contao_default'),
                        'picture_info' => $translator->trans('tl_gallery_creator_pictures.imageInfo.0', [], 'contao_default'),
                        'picture_path' => $translator->trans('tl_gallery_creator_pictures.path.0', [], 'contao_default'),
                        'picture_filename' => $translator->trans('tl_gallery_creator_pictures.filename.0', [], 'contao_default'),
                        'picture_date' => $translator->trans('tl_gallery_creator_pictures.date.0', [], 'contao_default'),
                        'picture_owner' => $translator->trans('tl_gallery_creator_pictures.owner.0', [], 'contao_default'),
                        'picture_title' => $translator->trans('tl_gallery_creator_pictures.title.0', [], 'contao_default'),
                        'picture_video_href_social' => $translator->trans('tl_gallery_creator_pictures.socialMediaSRC.0', [], 'contao_default'),
                        'picture_video_href_local' => $translator->trans('tl_gallery_creator_pictures.localMediaSRC.0', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Edit buttons callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="edit.buttons")
     */
    public function editButtonsCallback(array $buttons, DataContainer $dc): array
    {
        unset($buttons['saveNcreate'], $buttons['copy']);

        return $buttons;
    }

    /**
     * Ondelete callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.ondelete", priority=100)
     */
    public function ondeleteCb(DataContainer $dc, int $undoInt): void
    {
        if (!$dc->id) {
            return;
        }

        $picturesModel = GalleryCreatorPicturesModel::findByPk($dc->id);

        if (null === $picturesModel) {
            return;
        }

        if (!$this->isRestrictedUser($dc->id)) {
            $pid = $picturesModel->pid;

            // Delete data record
            $uuid = $picturesModel->uuid;

            // Do not delete the picture entity and let Contao do this job, otherwise we run into an error.
            // $picturesModel->delete();
            $filesModel = FilesModel::findByUuid($uuid);

            $albumsModel = GalleryCreatorAlbumsModel::findByPk($pid);
            $folderModel = FilesModel::findByUuid($albumsModel->assignedDir);

            // Only delete images if they are located in the directory assigned to the album
            if (null !== $folderModel && null !== $filesModel && strstr($filesModel->path, $folderModel->path)) {
                // Delete file from filesystem
                $file = new File($filesModel->path);
                $file->delete();
            }
        } else {
            Message::addError($this->translator->trans('ERR.notAllowedToDeletePicture', [$dc->id], 'contao_default'));
            Controller::redirect(System::getReferer());
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.onload", priority=80)
     */
    public function setPalettes(DataContainer $dc): void
    {
        if ($this->isRestrictedUser($dc->id)) {
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['restrictedUser'];
        }
    }

    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="list.operations.toggle.button", priority=50)
     */
    public function buttonCallbackToggle(array $row, ?string $href, ?string $label, ?string $title, ?string $icon, ?string $attributes): string
    {
        if ($this->isRestrictedUser($row['id'])) {
            return ' ';
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!empty($request->query->get('tid'))) {
            $this->toggleVisibility((int) $request->query->get('tid'), 1 === (int) $request->query->get('state'));
            Controller::redirect(System::getReferer());
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            Backend::addToUrl($href.'&amp;id='.$row['id']),
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon, $label),
        );
    }

    public function toggleVisibility(int $intId, bool $blnVisible): void
    {
        // Check if user is allowed to toggle visibility
        if ($this->isRestrictedUser($intId)) {
            return;
        }

        $objVersions = new Versions('tl_gallery_creator_pictures', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (isset($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback']) && \is_array($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $callback = System::importStatic($callback[0]);
                    $blnVisible = $callback->$callback[1]($blnVisible, $this);
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, $this);
                }
            }
        }

        // Update the database
        $this->connection->update(
            'tl_gallery_creator_pictures',
            [
                'tstamp' => time(),
                'published' => $blnVisible ? 1 : '',
            ],
            ['id' => $intId]
        );

        $objVersions->create();

        if ($this->logger) {
            $this->logger->info(
                sprintf('A new version of record "tl_gallery_creator_pictures.id=%s" has been created.', $intId),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
            );
        }
    }

    /**
     * Checks if the current user has full access or only restricted access to the active picture.
     */
    private function isRestrictedUser($id): bool
    {
        $user = $this->security->getUser();
        $owner = $this->connection->fetchOne('SELECT owner FROM tl_gallery_creator_pictures WHERE id = ?', [$id]);

        if (!$owner) {
            return false;
        }

        if ($user->isAdmin || !$this->galleryCreatorBackendWriteProtection) {
            return false;
        }

        if ((int) $owner !== (int) $user->id) {
            return true;
        }

        // The current user is the album owner
        return false;
    }
}
