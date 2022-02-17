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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DoctrineDBALException;
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
    private ContaoFramework $framework;
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

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, FileUtil $fileUtil, Security $security, TranslatorInterface $translator, TwigEnvironment $twig, string $projectDir, bool $galleryCreatorBackendWriteProtection, string $galleryCreatorUploadPath, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
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

        // Adapters
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->albums = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);
        $this->pictures = $this->framework->getAdapter(GalleryCreatorPicturesModel::class);
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.onload", priority=110)
     */
    public function onloadCallbackSetPermissions(DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        if ($this->isRestrictedUser($dc->id)) {
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['config']['closed'] = true;
            unset($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['list']['global_operations']['fileUpload']);
        }

        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        switch ($request->query->get('act')) {
            case 'create':
                // New records can only be inserted with a file upload
                $this->controller->redirect(sprintf(
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
    public function onloadCallbackSetCorrectReferer(DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        // Set the correct referer when redirecting from file import
        if ($request->query->has('filesImported')) {
            $arrReferer = $session->get('referer');
            $refererId = $request->attributes->get('_contao_referer_id');
            $arrReferer[$refererId]['current'] = 'contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.$dc->id;
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
    public function onloadCallbackRoute(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc || !$request->query->has('key')) {
            return;
        }

        $key = $request->query->get('key');

        switch ($key) {
            case 'imagerotate':

                if (!$this->isRestrictedUser($dc->id)) {
                    $picturesModel = $this->pictures->findByPk($dc->id);
                    $files = $this->framework->getAdapter(FilesModel::class);

                    $filesModel = $files->findByUuid($picturesModel->uuid);

                    if (null !== $picturesModel && null !== $filesModel) {
                        $file = new File($filesModel->path);

                        // Rotate image anticlockwise
                        $this->fileUtil->imageRotate($file, 270);

                        $dbafs = $this->framework->getAdapter(Dbafs::class);
                        $dbafs->addResource($filesModel->path, true);

                        $this->controller->redirect($this->system->getReferer());
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
                $this->backend->addToUrl($href.'&amp;id='.$row['id']),
                $this->stringUtil->specialchars($title),
                $attributes,
                $this->image->getHtml($icon, $label),
            );
        }

        return $this->image->getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Child record callback.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="list.sorting.child_record", priority=100)
     */
    public function childRecordCallback(array $arrRow): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $key = $arrRow['published'] ? 'published' : 'unpublished';

        $files = $this->framework->getAdapter(FilesModel::class);
        $filesModel = $files->findByUuid($arrRow['uuid']);

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

        $validator = $this->framework->getAdapter(Validator::class);

        if ($validator->isBinaryUuid($arrRow['localMediaSRC'])) {
            $files = $this->framework->getAdapter(FilesModel::class);

            if (null !== ($lmSRC = $files->findByUuid($arrRow['localMediaSRC']))) {
                $src = $lmSRC->path;
            }
        }

        if ($arrRow['socialMediaSRC'] || $lmSRC) {
            $type = empty(trim((string) $arrRow['localMediaSRC'])) ? $this->translator->trans('GALLERY_CREATOR.localMedia', [], 'contao_default') : $this->translator->trans('GALLERY_CREATOR.socialMedia', [], 'contao_default');
            $iconSrc = 'bundles/markocupicgallerycreator/images/movie.svg';
            $hasMovie = sprintf(
                '<div class="block" style="margin-bottom: 10px; line-height:1.7; display: flex; flex-wrap: wrap; align-items: center;"><img src="%s" style="margin-right: 6px;"> <span style="color:darkred; font-weight:500">%s:&nbsp;</span><a href="%s" data-lightbox="gc_album_%s">%s</a></div>',
                $iconSrc,
                $type,
                $src,
                $request->query->get('id'),
                $src,
            );
        }

        $blnShowThumb = false;
        $src = '';

        $config = $this->framework->getAdapter(Config::class);

        // Generate icon/thumbnail
        if ($config->get('thumbnails') && null !== $filesModel) {
            $blnShowThumb = true;

            $src = (new Image(new File($filesModel->path)))
                ->setTargetWidth(100)
                ->setResizeMode('center_center')
                ->executeResize()->getResizedPath();
        }

        $return = sprintf('<div class="cte_type %s"><strong>%s</strong> - %s [%s x %s px, %s]</div>', $key, $arrRow['headline'] ?? '', basename($filesModel->path), $file->width, $file->height, $this->backend->getReadableSize($file->filesize));
        $return .= $hasMovie;
        $return .= $blnShowThumb ? '<div class="block"><img src="'.$src.'" width="100"></div>' : null;
        $return .= sprintf('<div class="limit_height%s block">%s</div>', ($config->get('thumbnails') ? ' h64' : ''), $this->stringUtil->specialchars($arrRow['caption']));

        return $return;
    }

    /**
     * Move file to the correct directory, when cutting & pasting images
     * from one album into another.
     *
     * @Callback(table="tl_gallery_creator_pictures", target="config.oncut", priority=100)
     */
    public function oncutCallback(DataContainer $dc): void
    {
        $picture = $this->pictures->findByPk($dc->id);

        $album = $picture->getRelated('pid');

        $files = $this->framework->getAdapter(FilesModel::class);

        $sourcePath = $files->findByUuid($picture->uuid)->path;
        $targetPath = $files->findByUuid($album->assignedDir)->path.'/'.basename($sourcePath);

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
    public function inputFieldCallbackPicture(DataContainer $dc): string
    {
        $objImg = $this->pictures->findByPk($dc->id);

        $files = $this->framework->getAdapter(FilesModel::class);
        $filesModel = $files->findByUuid($objImg->uuid);

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
    public function inputFieldCallbackImageInfo(DataContainer $dc): string
    {
        $picturesModel = $this->pictures->findByPk($dc->id);

        $user = $this->framework->getAdapter(UserModel::class);
        $userModel = $user->findByPk($picturesModel->owner);

        $files = $this->framework->getAdapter(FilesModel::class);
        $filesModel = $files->findByUuid($picturesModel->uuid);

        $objSocial = $files->findByUuid($picturesModel->socialMediaSRC);
        $objLocal = $files->findByUuid($picturesModel->localMediaSRC);

        $picturesModel->video_href_social = $objSocial ? $objSocial->path : '';
        $picturesModel->video_href_local = $objLocal ? $objLocal->path : '';
        $picturesModel->path = $filesModel->path;
        $picturesModel->filename = basename($filesModel->path);
        $picturesModel->date_formatted = date('Y-m-d', (int) $picturesModel->date);
        $picturesModel->owner_name = '' === $userModel->name ? '---' : $userModel->name;

        $translator = $this->system->getContainer()->get('translator');

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
    public function ondeleteCallback(DataContainer $dc, int $undoInt): void
    {
        if (!$dc->id) {
            return;
        }

        $picturesModel = $this->pictures->findByPk($dc->id);

        if (null === $picturesModel) {
            return;
        }

        if (!$this->isRestrictedUser($dc->id)) {
            $pid = $picturesModel->pid;

            // Delete data record
            $uuid = $picturesModel->uuid;

            // Do not delete the picture entity and let Contao do this job, otherwise we run into an error.
            // $picturesModel->delete();

            $files = $this->framework->getAdapter(FilesModel::class);
            $filesModel = $files->findByUuid($uuid);

            $albumsModel = $this->albums->findByPk($pid);
            $folderModel = $files->findByUuid($albumsModel->assignedDir);

            // Only delete images if they are located in the directory assigned to the album
            if (null !== $folderModel && null !== $filesModel && strstr($filesModel->path, $folderModel->path)) {
                // Delete file from filesystem
                $file = new File($filesModel->path);
                $file->delete();
            }
        } else {
            $this->message->addError($this->translator->trans('ERR.notAllowedToDeletePicture', [$dc->id], 'contao_default'));
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Check Permission callback (haste_ajax_operation).
     *
     * @throws DoctrineDBALException
     */
    public function checkPermissionCallbackToggle(string $table, array $hasteAjaxOperationSettings, bool &$hasPermission): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $hasPermission = true;

        if ($request->request->has('id')) {
            $id = (int) $request->request->get('id');
            $result = $this->connection->fetchAssociative('SELECT * FROM tl_gallery_creator_pictures WHERE id = ?', [$id]);

            if ($result) {
                if ($this->isRestrictedUser($id)) {
                    $hasPermission = false;
                    $this->message->addError($this->translator->trans('ERR.rejectWriteAccessToPicture', [$result['id']], 'contao_default'));

                    $this->controller->reload();
                }
            }
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
