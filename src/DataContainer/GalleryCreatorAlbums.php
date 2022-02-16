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

use Contao\Automator;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Folder;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Revise\ReviseAlbumDatabase;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class GalleryCreatorAlbums
{
    private RequestStack $requestStack;
    private FileUtil $fileUtil;
    private Connection $connection;
    private Security $security;
    private TranslatorInterface $translator;
    private TwigEnvironment $twig;
    private ReviseAlbumDatabase $reviseAlbumDatabase;
    private MarkdownUtil $markdownUtil;
    private string $projectDir;
    private string $galleryCreatorUploadPath;
    private bool $galleryCreatorBackendWriteProtection;
    private array $galleryCreatorValidExtensions;
    private ?LoggerInterface $logger;

    public function __construct(RequestStack $requestStack, FileUtil $fileUtil, Connection $connection, Security $security, TranslatorInterface $translator, TwigEnvironment $twig, ReviseAlbumDatabase $reviseAlbumDatabase, MarkdownUtil $markdownUtil, string $projectDir, string $galleryCreatorUploadPath, bool $galleryCreatorBackendWriteProtection, array $galleryCreatorValidExtensions, ?LoggerInterface $logger)
    {
        $this->requestStack = $requestStack;
        $this->fileUtil = $fileUtil;
        $this->connection = $connection;
        $this->security = $security;
        $this->translator = $translator;
        $this->reviseAlbumDatabase = $reviseAlbumDatabase;
        $this->markdownUtil = $markdownUtil;
        $this->projectDir = $projectDir;
        $this->galleryCreatorUploadPath = $galleryCreatorUploadPath;
        $this->galleryCreatorBackendWriteProtection = $galleryCreatorBackendWriteProtection;
        $this->galleryCreatorValidExtensions = $galleryCreatorValidExtensions;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload")
     */
    public function handleClipboard(DataContainer $dc): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $bag = $session->getBag('contao_backend');

        if (isset($bag['CLIPBOARD']['tl_gallery_creator_albums']['mode'])) {
            if ('copyAll' === $bag['CLIPBOARD']['tl_gallery_creator_albums']['mode']) {
                Controller::redirect('contao?do=gallery_creator&amp;clipboard=1');
            }
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload")
     */
    public function setPalettes(DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        $pm = PaletteManipulator::create();

        $arrAlb = $this->connection->fetchAssociative('SELECT * FROM tl_gallery_creator_albums WHERE id = ?', [$dc->id]);

        if ($arrAlb && 'markdown' === $arrAlb['captionType']) {
            $pm->removeField('caption');
        }

        if ($arrAlb && 'text' === $arrAlb['captionType']) {
            $pm->removeField('markdownCaption');
        }
        $pm->applyToPalette('default', 'tl_gallery_creator_albums');
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload")
     */
    public function setPalettesForRestrictedUser(DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        if (!$this->isRestrictedUser($dc->id)) {
            PaletteManipulator::create()
                ->removeField('albumInfo')
                ->applyToPalette('default', 'tl_gallery_creator_albums')
            ;
        }
    }

    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.operations.uploadImages.button")
     */
    public function buttonCbUploadImages(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $href = sprintf($href, $row['id']);

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            Backend::addToUrl($href),
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon, $label),
        );
    }

    /**
     * Check Permission callback (haste_ajax_operation).
     *
     * @throws DoctrineDBALException
     */
    public function checkPermissionCbToggle(string $table, array $hasteAjaxOperationSettings, bool &$hasPermission): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $hasPermission = true;

        if ($request->request->has('id')) {
            $id = (int) $request->request->get('id', 0);
            $album = $this->connection->fetchAssociative('SELECT * FROM tl_gallery_creator_albums WHERE id = ?', [$id]);

            if ($album) {
                if (!$user->isAdmin && (int) $album['owner'] !== (int) $user->id && $this->galleryCreatorBackendWriteProtection) {
                    $hasPermission = false;
                    Message::addError($this->translator->trans('ERR.rejectWriteAccessToAlbum', [$album['name']], 'contao_default'));

                    Controller::redirect(System::getReferer());
                }
            }
        }
    }

    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.operations.cut.button")
     */
    public function buttonCbCutPicture(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $user = $this->security->getUser();
        $href .= '&amp;id='.$row['id'];

        if ($user->isAdmin || !$this->isRestrictedUser($row['id'])) {
            return sprintf(
                '<a href="%s" title="%s"%s>%s</a> ',
                Backend::addToUrl($href),
                StringUtil::specialchars($title),
                $attributes,
                Image::getHtml($icon, $label),
            );
        }

        return Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Paste button callback.
     *
     * @param mixed|false $arrClipboard
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.sorting.paste_button")
     */
    public function buttonCbPastePicture(DataContainer $dc, array $row, string $table, bool $cr, $arrClipboard = false): string
    {
        $user = $this->security->getUser();

        $disablePA = false;
        $disablePI = false;

        // Disable all buttons if there is a circular reference
        if ($user->isAdmin && false !== $arrClipboard && ('cut' === $arrClipboard['mode'] && (1 === (int) $cr || (int) $arrClipboard['id'] === (int) $row['id']) || 'cutAll' === $arrClipboard['mode'] && (1 === (int) $cr || \in_array($row['id'], $arrClipboard['id'], false)))) {
            $disablePA = true;
            $disablePI = true;
        }

        // Return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.svg', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'), 'class="blink"');
        $imagePasteInto = Image::getHtml('pasteinto.svg', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'), 'class="blink"');

        $return = '';

        if ($row['id'] > 0) {
            $return = $disablePA ? Image::getHtml('pasteafter_.svg', '', 'class="blink"').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&amp;='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
        }

        return $return.($disablePI ? Image::getHtml('pasteinto_.svg', '', 'class="blink"').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&amp;='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ');
    }


    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.operations.delete.button")
     */
    public function buttonCbDelete(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $user = $this->security->getUser();
        $href .= '&amp;id='.$row['id'];

        if ($user->isAdmin || (int) $user->id === (int) $row['owner'] || !$this->galleryCreatorBackendWriteProtection) {
            return sprintf(
                '<a href="%s" title="%s"%s>%s</a> ',
                Backend::addToUrl($href),
                StringUtil::specialchars($title),
                $attributes,
                Image::getHtml($icon, $label),
            );
        }

        return Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Button callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.operations.importImagesFromFilesystem.button")
     */
    public function buttonCbImportImages(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $href = sprintf($href, $row['id']);

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            Backend::addToUrl($href),
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon, $label),
        );
    }

    /**
     * Input field callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.reviseDatabase.input_field")
     */
    public function inputFieldCbReviseDatabase(): string
    {
        $translator = System::getContainer()->get('translator');

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

    /**
     * Input field callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.albumInfo.input_field")
     */
    public function inputFieldCbAlbumInfo(DataContainer $dc): string
    {
        if (!$dc) {
            return '';
        }

        $translator = System::getContainer()->get('translator');

        $objAlb = GalleryCreatorAlbumsModel::findByPk($dc->id);

        $caption = StringUtil::decodeEntities($objAlb->caption);

        if ('markdown' === $objAlb->captionType) {
            $caption = $this->markdownUtil->parse($objAlb->markdownCaption);
        }

        $objUser = UserModel::findByPk($objAlb->owner);
        $ownersName = null !== $objUser ? $objUser->name : 'no-name';
        $date_formatted = Date::parse('Y-m-d', $objAlb->date);
        $name = StringUtil::decodeEntities($objAlb->name);

        return (new Response(
            $this->twig->render(
                '@MarkocupicGalleryCreator/Backend/album_information.html.twig',
                [
                    'restricted' => $this->isRestrictedUser($objAlb->id),
                    'model' => $objAlb->row(),
                    'album_id' => $objAlb->id,
                    'album_thumb' => $objAlb->thumb,
                    'album_name' => $name,
                    'album_owners_name' => $ownersName,
                    'album_date_formatted' => $date_formatted,
                    'album_caption' => $caption,
                    'trans' => [
                        'album_id' => $translator->trans('tl_gallery_creator_albums.id.0', [], 'contao_default'),
                        'album_name' => $translator->trans('tl_gallery_creator_albums.name.0', [], 'contao_default'),
                        'album_date' => $translator->trans('tl_gallery_creator_albums.date.0', [], 'contao_default'),
                        'album_owners_name' => $translator->trans('tl_gallery_creator_albums.owner.0', [], 'contao_default'),
                        'album_caption' => $translator->trans('tl_gallery_creator_albums.caption.0', [], 'contao_default'),
                        'album_thumb' => $translator->trans('tl_gallery_creator_albums.thumb.0', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Input field callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.fileUpload.input_field")
     */
    public function inputFieldCbFileUpload(): string
    {
        // Create the template object
        $objTemplate = new BackendTemplate('be_gc_uploader');

        // Maximum uploaded size
        $objTemplate->maxUploadedSize = FileUpload::getMaxUploadSize();

        // Allowed extensions
        $objTemplate->strAccepted = implode(',', array_map(static fn ($el) => '.'.$el, $this->galleryCreatorValidExtensions));

        // $_FILES['file']
        $objTemplate->strName = 'file';

        // Return the parsed uploader template
        return $objTemplate->parse();
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload", priority=100)
     *
     * @throws DoctrineDBALException
     */
    public function onloadCbHandleAjax(): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        if ($request->query->has('isAjaxRequest')) {
            // Change sorting value
            if ($request->query->has('pictureSorting')) {
                $sorting = 10;

                foreach (StringUtil::trimsplit(',', $request->query->get('pictureSorting')) as $pictureId) {
                    $picturesModel = GalleryCreatorPicturesModel::findByPk($pictureId);

                    if (null !== $picturesModel) {
                        $picturesModel->sorting = $sorting;
                        $picturesModel->save();
                        $sorting += 10;
                    }
                }
            }

            // Revise table in the backend
            if ($request->query->has('checkTables')) {
                if ($request->query->has('getAlbumIDS')) {
                    $arrIDS = $this->connection->fetchFirstColumn('SELECT id FROM tl_gallery_creator_albums ORDER BY RAND()');

                    throw new ResponseException(new JsonResponse(['ids' => $arrIDS]));
                }

                if ($request->query->has('albumId')) {
                    $albumsModel = GalleryCreatorAlbumsModel::findByPk($request->query->get('albumId', 0));

                    if (null !== $albumsModel) {
                        if ($request->query->has('checkTables') || $request->query->has('reviseTables')) {
                            // Delete damaged data records
                            $cleanDb = $user->isAdmin && $request->query->has('reviseTables');

                            $this->reviseAlbumDatabase->run($albumsModel, $cleanDb);

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

    /**
     * Label callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="list.label.label")
     *
     * @throws DoctrineDBALException
     */
    public function labelCb(array $row, string $label): string
    {
        $countImages = $this->connection->fetchOne('SELECT count(id) as countImg FROM tl_gallery_creator_pictures WHERE pid = ?', [$row['id']]);

        $icon = $row['published'] ? 'album.svg' : '_album.svg';
        $icon = 'bundles/markocupicgallerycreator/images/'.$icon;
        $icon = sprintf('<img height="18" width="18" data-icon="%s" src="%s">', $icon, $icon);

        $label = str_replace('#icon#', $icon, $label);
        $label = str_replace('#count_pics#', (string) $countImages, $label);

        return str_replace('#datum#', Date::parse(Config::get('dateFormat'), $row['date']), $label);
    }

    /**
     * Load callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.imageResolution.load")
     */
    public function loadCbImageResolution(): string
    {
        $user = $this->security->getUser();

        return $user->gcImageResolution;
    }

    /**
     * Buttons callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="edit.buttons")
     */
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('reviseDatabase' === $request->query->get('key')) {
            // Remove buttons
            unset($arrButtons['saveNcreate'], $arrButtons['saveNclose']);

            $arrButtons['save'] = '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">'.$this->translator->trans('tl_gallery_creator_albums.reviseTablesBtn',[], 'contao_default').'</button>';
        }

        if ('fileUpload' === $request->query->get('key')) {
            // Remove buttons
            unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate']);
        }

        if ('importImagesFromFilesystem' === $request->query->get('key')) {
            // Remove buttons
            unset($arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['uploadNback']);
        }

        return $arrButtons;
    }

    /**
     * Ondelete callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.ondelete", priority=9999)
     *
     * @throws DoctrineDBALException
     */
    public function ondeleteCb(DataContainer $dc, int $undoId): void
    {
        if (!$dc) {
            return;
        }

        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if ('deleteAll' !== $request->query->get('act')) {
            if ($this->isRestrictedUser($dc->id)) {

                Message::addError($this->translator->trans('ERR.notAllowedToDeleteAlbum',[$dc->id],'contao_default'));
                Controller::redirect(System::getReferer());
            }

            // Also delete child albums
            $arrDeletedAlbums = GalleryCreatorAlbumsModel::getChildAlbumsIds((int) $dc->id);
            $arrDeletedAlbums = array_merge([$dc->id], $arrDeletedAlbums ?? []);

            foreach ($arrDeletedAlbums as $idDelAlbum) {
                $albumsModel = GalleryCreatorAlbumsModel::findByPk($idDelAlbum);

                if (null === $albumsModel) {
                    continue;
                }

                if ($user->isAdmin || (int) $albumsModel->owner === (int) $user->id || !$this->galleryCreatorBackendWriteProtection) {
                    // Remove all pictures from the database
                    $picturesModel = GalleryCreatorPicturesModel::findByPid($idDelAlbum);

                    if (null !== $picturesModel) {
                        while ($picturesModel->next()) {
                            $fileUuid = $picturesModel->uuid;
                            $picturesModel->delete();

                            // Delete the picture from the filesystem if it is not in use on another album
                            if (null === GalleryCreatorPicturesModel::findByUuid($fileUuid)) {
                                $filesModel = FilesModel::findByUuid($fileUuid);

                                if (null !== $filesModel) {
                                    $file = new File($filesModel->path);
                                    $file->delete();
                                }
                            }
                        }
                    }

                    $filesModel = FilesModel::findByUuid($albumsModel->assignedDir);

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

                    // Do not delete the album entity and let Contao do this job, otherwise we run into an error.
                    if ((int) $dc->id !== (int) $albumsModel->id) {
                        // Remove the album entity
                        $albumsModel->delete();
                    }
                } else {
                    // Do not delete albums that are not owned by the currently logged-in user.
                    $this->connection->update('tl_gallery_creator_albums', ['pid' => 0], ['id' => $idDelAlbum]);
                }
            }
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload", priority=90)
     */
    public function onloadCbCheckFolderSettings(DataContainer $dc): void
    {
        // Create the upload directory if it doesn't already exist
        $objFolder = new Folder($this->galleryCreatorUploadPath);
        $objFolder->unprotect();
        Dbafs::addResource($this->galleryCreatorUploadPath, false);

        $translator = System::getContainer()->get('translator');

        if (!is_writable($this->projectDir.'/'.$this->galleryCreatorUploadPath)) {
            Message::addError($translator->trans('ERR.dirNotWriteable', [$this->galleryCreatorUploadPath], 'contao_default'));
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload", priority=80)
     */
    public function onloadCbFileUpload(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('fileUpload' !== $request->query->get('key') || !$request->query->has('id')) {
            return;
        }

        // Load language file
        Controller::loadLanguageFile('tl_files');

        // Album ID
        $intAlbumId = (int) $request->query->get('id');

        // Save uploaded files in $_FILES['file']
        $strName = 'file';

        // Return if there is no album
        if (null === ($albumsModel = GalleryCreatorAlbumsModel::findById($intAlbumId))) {
            Message::addError('Album with ID '.$intAlbumId.' does not exist.');

            return;
        }

        // Return if there is no album directory
        $objUploadDir = FilesModel::findByUuid($albumsModel->assignedDir);

        if (null === $objUploadDir || !is_dir($this->projectDir.'/'.$objUploadDir->path)) {
            Message::addError('No upload directory defined in the album settings!');

            return;
        }

        // Return if there is no upload
        if (!isset($_FILES[$strName])) {
            return;
        }

        // Call the uploader script
        $arrUpload = $this->fileUtil->uploadFile($albumsModel, $strName);

        foreach ($arrUpload as $strPath) {
            if (null === ($file = new File($strPath))) {
                throw new ResponseException(new JsonResponse('Could not upload file '.$strName.'.', Response::HTTP_BAD_REQUEST));
            }

            // Add new data records to the database
            $this->fileUtil->addImageToAlbum($albumsModel, $file);
        }

        // Do not exit the script if html5_uploader is selected and Javascript is disabled
        if (!$request->request->has('submit')) {
            throw new ResponseException(new Response('', Response::HTTP_NO_CONTENT));
        }
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload", priority=70)
     */
    public function onloadCbImportFromFilesystem(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('importImagesFromFilesystem' !== $request->query->get('key')) {
            return;
        }
        // Load language file
        Controller::loadLanguageFile('tl_content');

        if (!$request->request->get('FORM_SUBMIT')) {
            return;
        }

        if (null !== ($albumsModel = GalleryCreatorAlbumsModel::findByPk($request->query->get('id')))) {
            $albumsModel->preserveFilename = $request->request->get('preserveFilename');
            $albumsModel->save();

            // Comma separated list with folder uuid's => 10585872-5f1f-11e3-858a-0025900957c8,105e9de0-5f1f-11e3-858a-0025900957c8,105e9dd6-5f1f-11e3-858a-0025900957c8
            $arrMultiSRC = StringUtil::trimsplit(',', (string) $request->request->get('multiSRC'));

            if (!empty($arrMultiSRC)) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserveFilename']['eval']['submitOnChange'] = false;

                // Import Images from filesystem and write entries to tl_gallery_creator_pictures
                $this->fileUtil->importFromFilesystem($albumsModel, $arrMultiSRC);

                throw new RedirectResponseException('contao?do=gallery_creator&amp;table=tl_gallery_creator_pictures&amp;='.$albumsModel->id.'&amp;filesImported=true');
            }
        }

        throw new RedirectResponseException('contao?do=gallery_creator');
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="config.onload", priority=60)
     *
     * @throws DoctrineDBALException
     */
    public function onloadCbSetUpPalettes(): void
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $dca = &$GLOBALS['TL_DCA']['tl_gallery_creator_albums'];

        // Permit global operations to admin only
        if (!$user->isAdmin) {
            unset(
                $dca['list']['global_operations']['all'],
                $dca['list']['global_operations']['reviseDatabase']
            );
        }

        // For security reasons give only readonly rights to these fields
        $dca['fields']['id']['eval']['style'] = '" readonly="readonly';
        $dca['fields']['ownersName']['eval']['style'] = '" readonly="readonly';

        // Create the file uploader palette
        if ('fileUpload' === $request->query->get('key')) {
            $dca['palettes']['default'] = $dca['palettes']['fileUpload'];

            return;
        }

        // Create the *importImagesFromFilesystem* palette
        if ('importImagesFromFilesystem' === $request->query->get('key')) {
            $dca['palettes']['default'] = $dca['palettes']['importImagesFromFilesystem'];
            $dca['fields']['preserveFilename']['eval']['submitOnChange'] = false;

            return;
        }

        // The palette for admins
        if ($user->isAdmin) {
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

                return;
            }

            $dca['fields']['owner']['eval']['doNotShow'] = false;
            $dca['fields']['protected']['eval']['doNotShow'] = false;
            $dca['fields']['groups']['eval']['doNotShow'] = false;

            return;
        }
        $id = $request->query->get('id');

        // Give write access on these fields to admins and album owners only.
        if ($this->isRestrictedUser($id)) {
            $dca['palettes']['default'] = $dca['palettes']['restrictedUser'];
        }
    }

    /**
     * Input field callback.
     *
     * List all images of the album (and child albums).
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.thumb.input_field")
     *
     * @throws DoctrineDBALException
     * @throws Exception
     */
    public function inputFieldCbThumb(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === ($albumsModel = GalleryCreatorAlbumsModel::findByPk($request->query->get('id')))) {
            return '';
        }

        // Save input
        if ('tl_gallery_creator_albums' === $request->request->get('FORM_SUBMIT')) {
            if (null === GalleryCreatorPicturesModel::findByPk($request->request->get('thumb'))) {
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
        $arrChildIds = GalleryCreatorAlbumsModel::getChildAlbumsIds((int) $request->query->get('id'));

        if (!empty($arrChildIds)) {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM tl_gallery_creator_pictures WHERE pid IN (?) ORDER BY id',
                [$arrChildAlbums],
                [Connection::PARAM_INT_ARRAY]
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
                $filesModel = FilesModel::findByUuid($arrItem['uuid']);

                if (null !== $filesModel) {
                    if (file_exists($filesModel->getAbsolutePath())) {
                        $file = new File($filesModel->path);
                    } else {
                        $placeholder = 'web/bundles/markocupicgallerycreator/images/placeholder.png';
                        $file = new File($placeholder);
                    }

                    $src = $file->path;

                    if ($file->height <= Config::get('gdMaxImgHeight') && $file->width <= Config::get('gdMaxImgWidth')) {
                        $src = (new Image($file))
                            ->setTargetWidth(80)
                            ->setTargetHeight(60)
                            ->setResizeMode('center_center')
                            ->executeResize()->getResizedPath()
                        ;
                    }

                    $checked = (int) $albumsModel->thumb === (int) $arrItem['id'] ? ' checked' : '';

                    $arrContainer[$i][$ii]['attr_checked'] = $checked;
                    $arrContainer[$i][$ii]['class'] = \strlen($checked) ? ' class="checked"' : '';
                    $arrContainer[$i][$ii]['filename'] = StringUtil::specialchars($filesModel->name);
                    $arrContainer[$i][$ii]['image'] = Image::getHtml($src, $filesModel->name);
                }
            }
        }

        $translator = System::getContainer()->get('translator');

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

    /**
     * Save callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.filePrefix.save")
     */
    public function saveCbValidateFilePrefix(string $strPrefix, DataContainer $dc): string
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
            $picturesModel = GalleryCreatorPicturesModel::findAll($arrOptions);

            if (null !== $picturesModel) {
                while ($picturesModel->next()) {
                    $filesModel = FilesModel::findOneByUuid($picturesModel->uuid);

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
                                $picturesModel->path = $file->path;
                                $picturesModel->save();
                                Message::addInfo(sprintf('Picture with ID %s has been renamed to %s.', $picturesModel->id, $newPath));
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

    /**
     * Save callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.sortBy.save")
     */
    public function saveCbSortBy(string $varValue, DataContainer $dc): string
    {
        if ('----' === $varValue) {
            return $varValue;
        }

        $picturesModels = GalleryCreatorPicturesModel::findByPid($dc->id);

        if (null === $picturesModels) {
            return '----';
        }

        $files = [];
        $auxDate = [];

        while ($picturesModels->next()) {
            $filesModel = FilesModel::findByUuid($picturesModels->uuid);
            $file = new File($filesModel->path);
            $files[$filesModel->path] = [
                'id' => $picturesModels->id,
            ];
            $auxDate[] = $file->mtime;
        }

        switch ($varValue) {
            case '----':
                break;

            case 'name_asc':
                uksort($files, 'basename_natcasecmp');
                break;

            case 'name_desc':
                uksort($files, 'basename_natcasercmp');
                break;

            case 'date_asc':
                array_multisort($files, SORT_NUMERIC, $auxDate, SORT_ASC);
                break;

            case 'date_desc':
                array_multisort($files, SORT_NUMERIC, $auxDate, SORT_DESC);
                break;
        }

        $sorting = 0;

        foreach ($files as $arrFile) {
            $sorting += 10;

            if (null !== ($picturesModel = GalleryCreatorPicturesModel::findByPk($arrFile['id']))) {
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
     * @Callback(table="tl_gallery_creator_albums", target="fields.alias.save")
     *
     * @throws DoctrineDBALException
     */
    public function setAliasAndUploadFolder(string $strAlias, DataContainer $dc): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $blnDoNotCreateDir = false;

        // Get current row
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($dc->id);

        // Save assigned dir if it has been defined
        if ($request->request->has('FORM_SUBMIT') && \strlen((string) $request->request->get('assignedDir'))) {
            $objAlbum->assignedDir = $request->request->get('assignedDir');
            $objAlbum->save();
            $blnDoNotCreateDir = true;
        }

        $strAlias = StringUtil::standardize($strAlias);

        // Generate the album alias from the album name
        if (!\strlen($strAlias)) {
            $strAlias = StringUtil::standardize($dc->activeRecord->name);
        }

        // Limit alias to 50 characters
        $strAlias = substr($strAlias, 0, 43);

        // Remove invalid characters
        $strAlias = preg_replace('/[^a-z0-9\\_\\-]/', '', $strAlias);

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
            $oFolder = Dbafs::addResource($objFolder->path, true);
            $objAlbum->assignedDir = $oFolder->uuid;
            $objAlbum->save();

            // Important
            Input::setPost('assignedDir', StringUtil::binToUuid($objAlbum->assignedDir));
        }

        return $strAlias;
    }

    /**
     * Save callback.
     *
     * @Callback(table="tl_gallery_creator_albums", target="fields.imageResolution.save")
     *
     * @throws DoctrineDBALException
     */
    public function saveCbImageResolution(string $value): void
    {
        $user = $this->security->getUser();

        $this->connection->update('tl_user', ['gcImageResolution' => $value], ['id' => $user->id]);
    }

    /**
     * Checks if the current user has full access or only restricted access to the active album.
     */
    private function isRestrictedUser($id): bool
    {
        $user = $this->security->getUser();
        $owner = $this->connection->fetchOne('SELECT owner FROM tl_gallery_creator_albums WHERE id = ?', [$id]);

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
