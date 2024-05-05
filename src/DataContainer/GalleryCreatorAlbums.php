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

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Automator;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactory;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Folder;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use FOS\HttpCacheBundle\CacheManager;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Revise\ReviseAlbumDatabase;
use Markocupic\GalleryCreatorBundle\Security\GalleryCreatorAlbumPermissions;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class GalleryCreatorAlbums
{
    // Adapters
    private Adapter $albums;
    private Adapter $backend;
    private Adapter $controller;
    private Adapter $image;
    private Adapter $message;
    private Adapter $pictures;
    private Adapter $stringUtil;
    private Adapter $system;
    private Adapter $config;

    /**
     * @throws DoctrineDBALException
     * @throws Exception
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly FileUtil $fileUtil,
        private readonly Connection $connection,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly ImageFactory $imageFactory,
        private readonly TwigEnvironment $twig,
        private readonly ReviseAlbumDatabase $reviseAlbumDatabase,
        private readonly CacheManager $cacheManager,
        private readonly string $projectDir,
        private readonly string $galleryCreatorUploadPath,
        private readonly array $galleryCreatorValidExtensions,
    ) {
        // Adapters
        $this->albums = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->pictures = $this->framework->getAdapter(GalleryCreatorPicturesModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->config = $this->framework->getAdapter(Config::class);

        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('isAjaxRequest') && $request->query->has('checkTables') && $request->query->has('getAlbumIDS')) {
            $this->reviseTables();
        }
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function checkPermissions(DataContainer $dc): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if ($user->admin) {
            return;
        }

        $act = $request->query->get('act');
        $mode = $request->query->get('mode');
        $pid = $request->query->get('pid');

        switch ($act) {
            case 'edit':
                if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $dc->id)) {
                    $this->message->addInfo($this->translator->trans('MSC.notAllowedEditAlbum', [$dc->id], 'contao_default'));
                    $this->controller->redirect($this->system->getReferer());
                }
                break;

            case 'delete':
                if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_DELETE_ALBUM, $dc->id)) {
                    $this->message->addInfo($this->translator->trans('MSC.notAllowedDeleteAlbum', [$dc->id], 'contao_default'));
                    $this->controller->redirect($this->system->getReferer());
                }
                break;

            case 'create':
                if ('2' === $mode) {
                    if ($pid > 0) {
                        if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $pid)) {
                            $this->message->addInfo($this->translator->trans('MSC.notAllowedAddPictures', [$pid], 'contao_default'));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    }
                }

                break;

            case 'cut':
                if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_MOVE_ALBUM, $dc->id)) {
                    $this->message->addInfo($this->translator->trans('MSC.notAllowedMoveAlbum', [$dc->id], 'contao_default'));
                    $this->controller->redirect($this->system->getReferer());
                }

                if ('2' === $mode) {
                    if ($pid > 0) {
                        if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_ADD_CHILD_ALBUMS, $pid)) {
                            $this->message->addInfo($this->translator->trans('MSC.notAllowedAddChildAlbum', [$pid], 'contao_default'));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    }
                }
                break;

            case 'deleteAll':
            case 'editAll':
            case 'overrideAll':
            case 'cutAll':
                $session = $request->getSession();
                $current = $session->get('CURRENT');

                if (isset($current['IDS'])) {
                    foreach ($current['IDS'] as $id) {
                        if ('deleteAll' === $act) {
                            if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_DELETE_ALBUM, $id)) {
                                $this->message->addInfo($this->translator->trans('MSC.notAllowedDeleteAlbum', [$id], 'contao_default'));
                                $this->controller->redirect($this->system->getReferer());
                            }
                        } elseif ('editAll' === $act || 'overrideAll' === $act) {
                            if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $id)) {
                                $this->message->addInfo($this->translator->trans('MSC.notAllowedDeleteAlbum', [$id], 'contao_default'));
                                $this->controller->redirect($this->system->getReferer());
                            }
                        } else {
                            // cutAll (paste into)
                            if ('2' === $mode && $pid > 0) {
                                if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_ADD_CHILD_ALBUMS, $pid)) {
                                    $this->message->addInfo($this->translator->trans('MSC.notAllowedAddChildAlbum', [$pid], 'contao_default'));
                                    $this->controller->redirect($this->system->getReferer());
                                }
                            }

                            // cutAll (paste below)
                            if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_MOVE_ALBUM, $id)) {
                                $this->message->addInfo($this->translator->trans('MSC.notAllowedMoveAlbum', [$id], 'contao_default'));
                                $this->controller->redirect($this->system->getReferer());
                            }
                        }
                    }

                    $session->set('CURRENT', $current);
                }
                break;

            default:
                break;
        }
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function setPalettes(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        // Handle markdown caption field
        $pm = $this->framework->createInstance(PaletteManipulator::class);

        $arrAlb = $this->connection->fetchAssociative('SELECT * FROM tl_gallery_creator_albums WHERE id = ?', [$dc->id]);

        if ($arrAlb && 'markdown' === $arrAlb['captionType']) {
            $pm->removeField('caption');
        }

        if ($arrAlb && 'text' === $arrAlb['captionType']) {
            $pm->removeField('markdownCaption');
        }

        $pm->applyToPalette('default', 'tl_gallery_creator_albums');

        $dca = &$GLOBALS['TL_DCA']['tl_gallery_creator_albums'];

        // Allow database revise to admins only
        if (!$user->admin) {
            unset(
                $dca['list']['global_operations']['reviseDatabase']
            );
        } else {
            // Global operation: revise database
            $albumCount = $this->connection->fetchFirstColumn('SELECT COUNT(id) AS albumCount FROM tl_gallery_creator_albums');
            $albumId = $this->connection->fetchOne('SELECT id FROM tl_gallery_creator_albums');

            if ($albumCount > 0) {
                $dca['list']['global_operations']['reviseDatabase']['href'] = sprintf($dca['list']['global_operations']['reviseDatabase']['href'], $albumId);
            } else {
                unset($dca['list']['global_operations']['reviseDatabase']);
            }

            if ('reviseDatabase' === $request->query->get('key')) {
                $dca['palettes']['default'] = $dca['palettes']['reviseDatabase'];
            }
        }

        // Create the file uploader palette
        if ('fileUpload' === $request->query->get('key')) {
            $dca['palettes']['default'] = $dca['palettes']['fileUpload'];
        }

        // Create the "importImagesFromFilesystem" palette
        if ('importImagesFromFilesystem' === $request->query->get('key')) {
            $dca['palettes']['default'] = $dca['palettes']['importImagesFromFilesystem'];
            $dca['fields']['preserveFilename']['eval']['submitOnChange'] = false;
        }
    }

    /**
     * Handle image sorting ajax requests.
     */
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function sortPictures(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('isAjaxRequest')) {
            // Change sorting value
            if ($request->query->has('pictureSorting')) {
                $count = 0;

                $picturesModel = null;

                foreach ($this->stringUtil->trimsplit(',', $request->query->get('pictureSorting')) as $pictureId) {
                    $picturesModel = $this->pictures->findByPk($pictureId);

                    if (null !== $picturesModel) {
                        $picturesModel->sorting = (($count++) + 1) * 128;
                        $picturesModel->save();
                    }
                }

                if ($count && null !== $picturesModel) {
                    // Invalidate cache tags.
                    $arrTags = [
                        'contao.db.tl_gallery_creator_albums.'.$picturesModel->pid,
                    ];

                    $this->cacheManager->invalidateTags($arrTags);
                }
            }

            throw new ResponseException(new Response('', Response::HTTP_NO_CONTENT));
        }
    }

    /**
     * Return the "toggle visibility" button.
     */
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.operations.toggle.button', priority: 100)]
    public function toggleVisibility(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, 'tl_gallery_creator_albums::published')) {
            return '';
        }

        $href .= '&amp;id='.$row['id'];

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $row['id'])) {
            if ($row['published']) {
                $icon = preg_replace('/\.svg$/i', '_.svg', $icon); // see #8126
            }

            return $this->image->getHtml($icon).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href).'" title="'.$this->stringUtil->specialchars($title).'" onclick="Backend.getScrollOffset();return AjaxRequest.toggleField(this,true)">'.$this->image->getHtml($icon, $label, 'data-icon="'.$this->image->getPath('visible.svg').'" data-icon-disabled="'.$this->image->getPath('invisible.svg').'" data-state="'.($row['published'] ? 1 : 0).'"').'</a> ';
    }

    /**
     * Button callback.
     */
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.operations.editheader.button', priority: 100)]
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.operations.delete.button', priority: 100)]
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.operations.uploadImages.button', priority: 100)]
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.operations.importImagesFromFilesystem.button', priority: 100)]
    public function buttonCallback(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $href .= '&amp;id='.$row['id'];

        $blnGranted = false;

        if (str_contains($href, 'act=edit') && str_contains($href, 'key=fileUpload')) {
            $blnGranted = $this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_ADD_AND_EDIT_IMAGES, $row['id']);
        } elseif (str_contains($href, 'act=edit') && str_contains($href, 'key=importImagesFromFilesystem')) {
            $blnGranted = $this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_ADD_AND_EDIT_IMAGES, $row['id']);
        } elseif (str_contains($href, 'act=edit')) {
            $blnGranted = $this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $row['id']);
        } elseif (str_contains($href, 'act=delete')) {
            $blnGranted = $this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_EDIT_ALBUM, $row['id']);
        }

        if ($blnGranted) {
            return sprintf(
                '<a href="%s" title="%s"%s>%s</a> ',
                $this->backend->addToUrl($href),
                $this->stringUtil->specialchars($title),
                $attributes,
                $this->image->getHtml($icon, $label),
            );
        }

        return $this->image->getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.reviseDatabase.input_field', priority: 100)]
    public function getReviseDatabaseWidget(): string
    {
        $translator = $this->system->getContainer()->get('translator');

        return (new Response(
            $this->twig->render(
                '@MarkocupicGalleryCreator/Backend/revise_database.html.twig',
                [
                    'trans' => [
                        'albums_messages_reviseDatabase' => [
                            $translator->trans('tl_gallery_creator_albums.reviseDatabase.0', [], 'contao_default'),
                            $translator->trans('tl_gallery_creator_albums.reviseDatabase.1', [], 'contao_default'),
                        ],
                    ],
                ]
            )
        ))->getContent();
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.fileUpload.input_field', priority: 100)]
    public function getFileUploadWidget(): string
    {
        // Create the template object
        $objTemplate = new BackendTemplate('be_gc_uploader');

        $fileUpload = $this->framework->getAdapter(FileUpload::class);

        // Maximum uploaded size
        $objTemplate->maxUploadedSize = $fileUpload->getMaxUploadSize();

        // Allowed extensions
        $objTemplate->strAccepted = implode(',', array_map(static fn ($el) => '.'.$el, $this->galleryCreatorValidExtensions));

        // $_FILES['file']
        $objTemplate->strName = 'file';

        // Return the parsed uploader template
        return $objTemplate->parse();
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'list.label.label', priority: 100)]
    public function labelCallback(array $row, string $label): string
    {
        $countImages = $this->connection->fetchOne('SELECT count(id) as countImg FROM tl_gallery_creator_pictures WHERE pid = ?', [$row['id']]);

        $icon = $row['published'] ? 'album.svg' : '_album.svg';
        $alt = $row['published'] ? $this->translator->trans('MSC.published', [], 'contao_default') : $this->translator->trans('MSC.unpublished', [], 'contao_default');
        $icon = 'bundles/markocupicgallerycreator/images/'.$icon;
        $icon = sprintf('<img height="18" width="18" data-icon="%s" src="%s" alt="%s">', $icon, $icon, $this->stringUtil->specialchars($alt));

        $label = str_replace('#icon#', $icon, $label);
        $label = str_replace('#count_pics#', (string) $countImages, $label);

        return str_replace('#datum#', date($this->config->get('dateFormat'), (int) $row['date']), $label);
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'edit.buttons', priority: 100)]
    public function setFormSubmitButtons(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('reviseDatabase' === $request->query->get('key')) {
            // Remove useless buttons
            unset($arrButtons['saveNcreate'], $arrButtons['saveNclose']);

            // Replace the save button with the revise table button.
            $arrButtons['save'] = sprintf(
                '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">%s</button>',
                $this->translator->trans('tl_gallery_creator_albums.reviseTablesBtn', [], 'contao_default'),
            );
        }

        if ('fileUpload' === $request->query->get('key')) {
            // Remove useless buttons
            unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate']);
        }

        if ('importImagesFromFilesystem' === $request->query->get('key')) {
            // Remove useless buttons
            unset($arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['uploadNback']);
        }

        return $arrButtons;
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.ondelete', priority: 100)]
    public function ondeleteCallback(DataContainer $dc, int $undoId): void
    {
        // Also delete child albums
        $arrDeletedAlbums = $this->albums->getChildAlbumsIds((int) $dc->id);
        $arrDeletedAlbums = array_merge([$dc->id], $arrDeletedAlbums ?? []);

        // Abort deletion, if user is not the owner of an album in the selection
        foreach ($arrDeletedAlbums as $idDelAlbum) {
            if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_DELETE_ALBUM, $idDelAlbum)) {
                $this->message->addError($this->translator->trans('MSC.notAllowedDeleteAlbum', [$idDelAlbum], 'contao_default'));
                $this->controller->redirect($this->system->getReferer());
            }
        }

        foreach ($arrDeletedAlbums as $idDelAlbum) {
            $albumsModel = $this->albums->findByPk($idDelAlbum);

            if (null === $albumsModel) {
                continue;
            }

            // Remove all pictures from the database
            $picturesModel = $this->pictures->findByPid($idDelAlbum);

            $files = $this->framework->getAdapter(FilesModel::class);

            if (null !== $picturesModel) {
                while ($picturesModel->next()) {
                    $fileUuid = $picturesModel->uuid;

                    // Delete the picture from the filesystem if it is not used by another album
                    if (null !== $this->pictures->findOneByUuid($fileUuid)) {
                        $filesModel = $files->findOneByUuid($fileUuid);

                        if (null !== $filesModel) {
                            $file = new File($filesModel->path);
                            $file->delete();
                        }
                    }
                }
            }

            $filesModel = $files->findOneByUuid($albumsModel->assignedDir);

            if (null !== $filesModel) {
                $finder = new Finder();
                $finder->in($filesModel->getAbsolutePath())
                    ->depth('== 0')
                    ->notName('.public')
                ;

                if (!$finder->hasResults()) {
                    // Remove the folder if empty
                    (new Folder($filesModel->path))->delete();
                }
            }
        }
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function checkAlbumFolder(DataContainer $dc): void
    {
        // Create the upload directory if it doesn't already exist
        $objFolder = new Folder($this->galleryCreatorUploadPath);
        $objFolder->unprotect();

        $dbafs = $this->framework->getAdapter(Dbafs::class);

        $dbafs->addResource($this->galleryCreatorUploadPath, false);

        $translator = $this->system->getContainer()->get('translator');

        if (!is_writable($this->projectDir.'/'.$this->galleryCreatorUploadPath)) {
            $this->message->addError($translator->trans('ERR.dirNotWriteable', [$this->galleryCreatorUploadPath], 'contao_default'));
        }
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function uploadFile(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('fileUpload' !== $request->query->get('key')) {
            return;
        }

        // Do not allow uploads to not authorized users
        if (!$this->security->isGranted(GalleryCreatorAlbumPermissions::USER_CAN_ADD_AND_EDIT_IMAGES, $dc->id)) {
            $this->message->addInfo($this->translator->trans('MSC.notAllowedEditAlbum', [$dc->id], 'contao_default'));
            $this->controller->redirect($this->system->getReferer());
        }

        // Load language file
        $this->controller->loadLanguageFile('tl_files');

        // Store uploaded files under $_FILES['file']
        $strName = 'file';

        // Return if there is no album
        if (null === ($albumsModel = $this->albums->findById($dc->id))) {
            $this->message->addError('Album with ID '.$dc->id.' not found.');

            return;
        }

        $files = $this->framework->getAdapter(FilesModel::class);

        // Return if the album directory does not exist.
        $objUploadDir = $files->findOneByUuid($albumsModel->assignedDir);

        if (null === $objUploadDir || !is_dir($this->projectDir.'/'.$objUploadDir->path)) {
            $this->message->addError('No upload directory defined in the album settings!');

            return;
        }

        // Return if there is no upload
        if (!isset($_FILES[$strName])) {
            return;
        }

        // Call the file upload script
        $arrUpload = $this->fileUtil->uploadFile($albumsModel, $strName);

        // Invalidate cache tags.
        $arrTags = [
            'contao.db.tl_gallery_creator_albums.'.$dc->id,
        ];

        $this->cacheManager->invalidateTags($arrTags);

        foreach ($arrUpload as $strPath) {
            if (null === ($file = new File($strPath))) {
                throw new ResponseException(new JsonResponse('Could not upload file '.$strName.'.', Response::HTTP_BAD_REQUEST));
            }

            // Add new data records to the database
            $this->fileUtil->addImageToAlbum($albumsModel, $file);
        }

        // Exit script
        if (!$request->request->has('submit')) {
            throw new ResponseException(new Response('', Response::HTTP_NO_CONTENT));
        }
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onload', priority: 100)]
    public function importFromFilesystem(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('importImagesFromFilesystem' !== $request->query->get('key')) {
            return;
        }
        // Load language file
        $this->controller->loadLanguageFile('tl_content');

        if (!$request->request->get('FORM_SUBMIT')) {
            return;
        }

        if (null !== ($albumsModel = $this->albums->findByPk($request->query->get('id')))) {
            $albumsModel->preserveFilename = $request->request->get('preserveFilename');
            $albumsModel->save();

            // Comma separated list with folder uuid's => 10585872-5f1f-11e3-858a-0025900957c8,105e9de0-5f1f-11e3-858a-0025900957c8,105e9dd6-5f1f-11e3-858a-0025900957c8
            $arrMultiSRC = $this->stringUtil->trimsplit(',', (string) $request->request->get('multiSRC'));

            if (!empty($arrMultiSRC)) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserveFilename']['eval']['submitOnChange'] = false;

                // Import Images from filesystem and write entries to tl_gallery_creator_pictures
                $this->fileUtil->importFromFilesystem($albumsModel, $arrMultiSRC);

                // Invalidate cache tags.
                $arrTags = [
                    'contao.db.tl_gallery_creator_albums.'.$dc->id,
                ];

                $this->cacheManager->invalidateTags($arrTags);

                throw new RedirectResponseException('contao?do=gallery_creator&amp;table=tl_gallery_creator_pictures&amp;='.$albumsModel->id.'&amp;filesImported=true');
            }
        }

        throw new RedirectResponseException('contao?do=gallery_creator');
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'config.onsubmit', priority: 100)]
    public function invalidateCacheOnAlbumUpdate(DataContainer $dc): void
    {
        // Invalidate cache tags.
        $arrTags = [
            'contao.db.tl_gallery_creator_albums.'.$dc->id,
        ];

        $this->cacheManager->invalidateTags($arrTags);
    }

    /**
     * Input field callback.
     *
     * List all images of the album (and child albums).
     *
     * @throws DoctrineDBALException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.thumb.input_field', priority: 100)]
    public function getAlbumThumbsWidget(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === ($albumsModel = $this->albums->findByPk($request->query->get('id')))) {
            return '';
        }

        // Save input
        if ('tl_gallery_creator_albums' === $request->request->get('FORM_SUBMIT')) {
            if (null === $this->pictures->findByPk($request->request->get('thumb'))) {
                $albumsModel->thumb = 0;
            } else {
                $albumsModel->thumb = $request->request->get('thumb');
            }
            $albumsModel->save();
        }

        $arrAlbums = [];
        $arrChildAlbums = [];

        // Generate picture list
        $id = $request->query->get('id');
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_gallery_creator_pictures WHERE pid = ? ORDER BY sorting', [$id]);

        while (false !== ($arrPicture = $stmt->fetchAssociative())) {
            $arrAlbums[] = [
                'uuid' => $arrPicture['uuid'],
                'id' => $arrPicture['id'],
            ];
        }

        // Get all child albums
        $arrChildIds = $this->albums->getChildAlbumsIds((int) $request->query->get('id'));

        if (!empty($arrChildIds)) {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM tl_gallery_creator_pictures WHERE pid IN (?) ORDER BY id',
                [$arrChildAlbums],
                [ArrayParameterType::INTEGER]
            );

            while (false !== ($arrPicture = $stmt->fetchAssociative())) {
                $arrChildAlbums[] = [
                    'uuid' => $arrPicture['uuid'],
                    'id' => $arrPicture['id'],
                ];
            }
        }

        $arrContainer = [
            $arrAlbums,
            $arrChildAlbums,
        ];

        foreach ($arrContainer as $i => $arrData) {
            foreach ($arrData as $ii => $arrItem) {
                $files = $this->framework->getAdapter(FilesModel::class);

                $filesModel = $files->findOneByUuid($arrItem['uuid']);

                if (null !== $filesModel) {
                    if (file_exists($filesModel->getAbsolutePath())) {
                        $file = new File($filesModel->path);
                    } else {
                        $placeholder = 'web/bundles/markocupicgallerycreator/images/placeholder.png';
                        $file = new File($placeholder);
                    }

                    $src = $file->path;

                    if ($file->height <= $this->config->get('gdMaxImgHeight') && $file->width <= $this->config->get('gdMaxImgWidth')) {
                        $image = $this->imageFactory->create(
                            $this->projectDir.'/'.$src,
                            [80, 60, 'center_center']
                        );

                        $src = $image->getUrl($this->projectDir);
                    }

                    $checked = (int) $albumsModel->thumb === (int) $arrItem['id'] ? ' checked' : '';

                    $arrContainer[$i][$ii]['attr_checked'] = $checked;
                    $arrContainer[$i][$ii]['class'] = \strlen($checked) ? ' class="checked"' : '';
                    $arrContainer[$i][$ii]['filename'] = $this->stringUtil->specialchars($filesModel->name);
                    $arrContainer[$i][$ii]['image'] = $this->image->getHtml($src, $filesModel->name);
                }
            }
        }

        $translator = $this->system->getContainer()->get('translator');

        return (new Response(
            $this->twig->render(
                '@MarkocupicGalleryCreator/Backend/album_thumbnail_list.html.twig',
                [
                    'album_thumbs' => $arrContainer[0],
                    'child_album_thumbs' => $arrContainer[1],
                    'has_album_thumbs' => !empty($arrContainer[0]),
                    'has_child_album_thumbs' => !empty($arrContainer[1]),
                    'trans' => [
                        'album_thumb' => $translator->trans('tl_gallery_creator_albums.thumb.0', [], 'contao_default'),
                        'drag_items_hint' => $translator->trans('tl_gallery_creator_albums.thumb.1', [], 'contao_default'),
                        'child_albums' => $translator->trans('GALLERY_CREATOR.childAlbums', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.filePrefix.save', priority: 100)]
    public function validateFilePrefix(string $strPrefix, DataContainer $dc): string
    {
        $i = 0;

        if ('' !== $strPrefix) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', \Transliterator::FORWARD);
            $strPrefix = $transliterator->transliterate($strPrefix);
            $strPrefix = str_replace('.', '_', $strPrefix);

            $arrOptions = [
                'column' => ['tl_gallery_creator_pictures.pid = ?'],
                'value' => [$dc->id],
                'order' => 'sorting ASC',
            ];
            $picturesModel = $this->pictures->findAll($arrOptions);

            if (null !== $picturesModel) {
                while ($picturesModel->next()) {
                    $files = $this->framework->getAdapter(FilesModel::class);

                    $filesModel = $files->findOneByUuid($picturesModel->uuid);

                    if (null !== $filesModel) {
                        if (is_file($this->projectDir.'/'.$filesModel->path)) {
                            $file = new File($filesModel->path);
                            ++$i;

                            while (is_file($file->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($file->extension))) {
                                ++$i;
                            }
                            $oldPath = $file->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($file->extension);
                            $newPath = str_replace($this->projectDir.'/', '', $oldPath);

                            // Rename file
                            if ($file->renameTo($newPath)) {
                                $this->message->addInfo(sprintf('Picture with ID %s has been renamed to %s.', $picturesModel->id, $newPath));
                            }
                        }
                    }
                }
                // Purge image cache too
                $objAutomator = new Automator();
                $objAutomator->purgeImageCache();
            }
        }

        return '';
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.sortBy.save', priority: 100)]
    public function sortAlbumEntities(string $varValue, DataContainer $dc): string
    {
        if ('----' === $varValue) {
            return $varValue;
        }

        $picturesModels = $this->pictures->findByPid($dc->id);

        if (null === $picturesModels) {
            return '----';
        }

        $arrFiles = [];
        $auxDate = [];

        while ($picturesModels->next()) {
            $files = $this->framework->getAdapter(FilesModel::class);

            $filesModel = $files->findOneByUuid($picturesModels->uuid);

            $file = new File($filesModel->path);
            $arrFiles[$filesModel->path] = [
                'id' => $picturesModels->id,
            ];
            $auxDate[] = $file->mtime;
        }

        switch ($varValue) {
            case '----':
                break;

            case 'name_asc':

                uksort($arrFiles, static fn ($a, $b): int => strnatcasecmp(basename($a), basename($b)));
                break;

            case 'name_desc':
                uksort($arrFiles, static fn ($a, $b): int => -strnatcasecmp(basename($a), basename($b)));
                break;

            case 'date_asc':
                array_multisort($arrFiles, SORT_NUMERIC, $auxDate, SORT_ASC);
                break;

            case 'date_desc':
                array_multisort($arrFiles, SORT_NUMERIC, $auxDate, SORT_DESC);
                break;
        }

        $sorting = 0;

        foreach ($arrFiles as $arrFile) {
            $sorting += 10;

            if (null !== ($picturesModel = $this->pictures->findByPk($arrFile['id']))) {
                $picturesModel->sorting = $sorting;
                $picturesModel->save();
            }
        }

        // Return default value
        return '----';
    }

    /**
     * Save callback.
     *
     * Generate a unique album alias based on the album name
     * and create a directory with the same name
     *
     * @throws DoctrineDBALException
     */
    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.alias.save', priority: 100)]
    public function saveCallbackSetAliasAndUploadFolder(string $strAlias, DataContainer $dc): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $blnDoNotCreateDir = false;

        // Get current row
        $objAlbum = $this->albums->findByPk($dc->id);

        // Save assigned dir if it has been defined
        if ($request->request->has('FORM_SUBMIT') && \strlen((string) $request->request->get('assignedDir'))) {
            $objAlbum->assignedDir = $request->request->get('assignedDir');
            $objAlbum->save();
            $blnDoNotCreateDir = true;
        }

        $strAlias = $this->stringUtil->standardize($strAlias);

        // Generate the album alias from the album name
        if (!\strlen($strAlias)) {
            $strAlias = $this->stringUtil->standardize($dc->activeRecord->name);
        }

        // Limit alias to 50 characters
        $strAlias = substr($strAlias, 0, 43);

        // Remove invalid characters
        $strAlias = preg_replace('/[^a-z0-9_\-]/', '', $strAlias);

        // If alias already exists add the album-id to the alias
        $result = $this->connection->fetchOne('SELECT id FROM tl_gallery_creator_albums WHERE id != ? AND alias = ?', [$dc->activeRecord->id, $strAlias]);

        if ($result) {
            $strAlias = 'id-'.$dc->id.'-'.$strAlias;
        }

        // Create the default upload folder
        if (false === $blnDoNotCreateDir) {
            // Create the new folder and register it in the dbafs
            $objFolder = new Folder($this->galleryCreatorUploadPath.'/'.$strAlias);
            $objFolder->unprotect();

            $dbafs = $this->framework->getAdapter(Dbafs::class);

            $oFolder = $dbafs->addResource($objFolder->path, true);

            $objAlbum->assignedDir = $oFolder->uuid;
            $objAlbum->save();

            // Important
            $request->request->set('assignedDir', $this->stringUtil->binToUuid($objAlbum->assignedDir));
        }

        return $strAlias;
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.imageResolution.load', priority: 100)]
    public function getImageResolution(): string
    {
        $user = $this->security->getUser();

        return $user->gcImageResolution;
    }

    #[AsCallback(table: 'tl_gallery_creator_albums', target: 'fields.imageResolution.save', priority: 100)]
    public function setImageResolution(string $value): void
    {
        $user = $this->security->getUser();

        $this->connection->update('tl_user', ['gcImageResolution' => $value], ['id' => $user->id]);
    }

    /**
     * @throws DoctrineDBALException
     * @throws Exception
     */
    private function reviseTables(): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        if ($request->query->has('isAjaxRequest')) {
            // Revise table in the backend
            if ($request->query->has('checkTables')) {
                if ($request->query->has('getAlbumIDS')) {
                    $arrIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_gallery_creator_albums ORDER BY RAND()');

                    throw new ResponseException(new JsonResponse(['ids' => $arrIds]));
                }

                if ($request->query->has('albumId')) {
                    $albumsModel = $this->albums->findByPk($request->query->get('albumId', 0));

                    if (null !== $albumsModel) {
                        if ($request->query->has('checkTables') || $request->query->has('reviseTables')) {
                            // Delete damaged data records
                            $blnCleanDb = $user->admin && $request->query->has('reviseTables');

                            $this->reviseAlbumDatabase->run($albumsModel, $blnCleanDb);

                            if ($session->has('gc_error') && \is_array($session->get('gc_error'))) {
                                if (!empty($session->get('gc_error'))) {
                                    $arrErrors = $session->get('gc_error');

                                    if (!empty($arrErrors)) {
                                        throw new ResponseException(new JsonResponse(['errors' => $arrErrors]));
                                    }
                                }
                            }
                        }
                    }
                    $session->remove('gc_error');
                }
            }

            throw new ResponseException(new Response('', Response::HTTP_NO_CONTENT));
        }
    }
}
