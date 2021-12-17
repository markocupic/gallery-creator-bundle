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

namespace Markocupic\GalleryCreatorBundle\Dca;

use Contao\Automator;
use Contao\Backend;
use Contao\Config;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Versions;
use Markocupic\GalleryCreatorBundle\Helper\GcHelper;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class TlGalleryCreatorAlbums.
 */
class TlGalleryCreatorAlbums extends Backend
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var
     */
    private $albumUtil;

    /**
     * @var FileUtil
     */
    private $fileUtil;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $galleryCreatorUploadPath;

    /**
     * @var
     */
    private $galleryCreatorBackendWriteProtection;

    /**
     * @var Session|null
     */
    private $session;

    /**
     * @var bool
     */
    private $restrictedUser = false;

    public function __construct(RequestStack $requestStack, AlbumUtil $albumUtil, FileUtil $fileUtil, string $projectDir, string $galleryCreatorUploadPath, string $galleryCreatorBackendWriteProtection)
    {

        $this->requestStack = $requestStack;
        $this->albumUtil = $albumUtil;
        $this->fileUtil = $fileUtil;
        $this->projectDir = $projectDir;
        $this->galleryCreatorUploadPath = $galleryCreatorUploadPath;
        $this->galleryCreatorBackendWriteProtection = $galleryCreatorBackendWriteProtection;

        $this->session = $requestStack->getCurrentRequest()->getSession();

        parent::__construct();

        $this->import('BackendUser', 'User');

        // Register the parseBackendTemplate Hook
        $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = [
            self::class,
            'parseBackendTemplate',
        ];

        $bag = $this->session->getBag('contao_backend');

        if (isset($bag['CLIPBOARD']['tl_gallery_creator_albums']['mode'])) {
            if ('copyAll' === $bag['CLIPBOARD']['tl_gallery_creator_albums']['mode']) {
                $this->redirect('contao?do=gallery_creator&clipboard=1');
            }
        }
    }

    /**
     * Return the add-images-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbAddImages($row, $href, $label, $title, $icon, $attributes): string
    {
        $href .= 'id='.$row['id'].'&act=edit&table=tl_gallery_creator_albums&mode=fileupload';

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.' style="margin-right:5px">'.Image::getHtml($icon, $label).'</a>';
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (!empty(Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), 1 === Input::get('state'));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.gif';
        }

        Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
            ->limit(1)
            ->execute($row['id'])
        ;

        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
            return Image::getHtml($icon).' ';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Toggle visibility of a certain album.
     *
     * @param int  $intId
     * @param bool $blnVisible
     */
    public function toggleVisibility($intId, $blnVisible): void
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intId);

        // Check if the user is allowed to publish/unpublish an album.
        if (!$this->User->isAdmin && $objAlbum->owner !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
            $this->log('Not enough permissions to publish/unpublish tl_gallery_creator_albums ID "'.$intId.'"', __METHOD__, TL_ERROR);
            $this->redirect('contao?act=error');
        }

        $objVersions = new Versions('tl_gallery_creator_albums', $intId);
        $objVersions->initialize();

        // Trigger the save callback
        if (\is_array($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, $this);
                }
            }
        }

        // Update the database
        Database::getInstance()
            ->prepare('UPDATE tl_gallery_creator_albums SET tstamp='.time().", published='".($blnVisible ? 1 : '')."' WHERE id=?")
            ->execute($intId)
        ;

        $objVersions->create();
        $this->log('A new version of record "tl_gallery_creator_albums.id='.$intId.'" has been created.', __METHOD__, TL_GENERAL);
    }

    /**
     * Return the cut-picture-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function buttonCbCutPicture($row, $href, $label, $title, $icon, $attributes)
    {
        // Allow cutting albums for album-owners and admins only
        return (int) $this->User->id === (int) $row['owner'] || $this->User->isAdmin || !$this->galleryCreatorBackendWriteProtection ? ' <a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : ' '.Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)).' ';
    }

    /**
     * Return the delete-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbDelete($row, $href, $label, $title, $icon, $attributes): string
    {
        // enable deleting albums to album-owners and admins only
        return $this->User->isAdmin || (int) $this->User->id === (int) $row['owner'] || !$this->galleryCreatorBackendWriteProtection ? '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)).' ';
    }

    /**
     * Return the editheader button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbEditHeader($row, $href, $label, $title, $icon, $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id'], 1).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the edit-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function buttonCbEdit($row, $href, $label, $title, $icon, $attributes)
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id'], 1).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the import-images button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbImportImages($row, $href, $label, $title, $icon, $attributes): string
    {
        $href .= 'id='.$row['id'].'&act=edit&table=tl_gallery_creator_albums&mode=importImages';

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a>';
    }

    /**
     * Return the paste-picture-button.
     *
     * @param $row
     * @param $table
     * @param $cr
     * @param bool $arrClipboard
     */
    public function buttonCbPastePicture(DataContainer $dc, $row, $table, $cr, $arrClipboard = false): string
    {
        $disablePA = false;
        $disablePI = false;

        // Disable all buttons if there is a circular reference
        if ($this->User->isAdmin && false !== $arrClipboard && ('cut' === $arrClipboard['mode'] && (1 === (int) $cr || (int) $arrClipboard['id'] === (int) $row['id']) || 'cutAll' === $arrClipboard['mode'] && (1 === (int) $cr || \in_array($row['id'], $arrClipboard['id'], false)))) {
            $disablePA = true;
            $disablePI = true;
        }

        // Return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id']), 'class="blink"');
        $imagePasteInto = Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id']), 'class="blink"');

        if ($row['id'] > 0) {
            $return = $disablePA ? Image::getHtml('pasteafter_.gif', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&id='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
        }

        return $return.($disablePI ? Image::getHtml('pasteinto_.gif', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&id='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ');
    }

    /**
     * Checks if the current user has full access or only restricted access to the selected album.
     */
    public function checkUserRole($albumId): void
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);

        if ($this->User->isAdmin || !$this->galleryCreatorBackendWriteProtection) {
            $this->restrictedUser = false;

            return;
        }

        if ($objAlbum->owner !== $this->User->id) {
            $this->restrictedUser = true;

            return;
        }
        // ...so the current user is the album owner
        $this->restrictedUser = false;
    }



    /**
     * Return the html.
     */
    public function inputFieldCbCleanDb(): string
    {
        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        return (new Response(
            $twig->render(
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
     * Return the html table with the album information for restricted users.
     */
    public function inputFieldCbGenerateAlbumInformations(): string
    {
        $objAlb = GalleryCreatorAlbumsModel::findByPk(Input::get('id'));
        $objUser = UserModel::findByPk($objAlb->owner);
        $objAlb->ownersName = null !== $objUser ? $objUser->name : 'no-name';
        $objAlb->date_formatted = Date::parse('Y-m-d', $objAlb->date);

        // Check User Role
        $this->checkUserRole(Input::get('id'));

        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/album_information.html.twig',
                [
                    'restricted' => $this->restrictedUser,
                    'model' => $objAlb->row(),
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
     * Return the markup for the file uploader.
     *
     * @return string
     */
    public function inputFieldCbGenerateUploaderMarkup()
    {
        return GcHelper::generateUploader($this->User->gcBeUploaderTemplate);
    }

    /**
     * Handle ajax requests.
     */
    public function handleAjaxRequests(): void
    {
        if (Input::get('isAjaxRequest')) {
            // change sorting value
            if (Input::get('pictureSorting')) {
                $sorting = 10;

                foreach (explode(',', Input::get('pictureSorting')) as $pictureId) {
                    $objPicture = GalleryCreatorPicturesModel::findByPk($pictureId);

                    if (null !== $objPicture) {
                        $objPicture->sorting = $sorting;
                        $objPicture->save();
                        $sorting += 10;
                    }
                }
            }

            // Revise table in the backend
            if (Input::get('checkTables')) {
                if (Input::get('getAlbumIDS')) {
                    $objDb = Database::getInstance()
                        ->execute('SELECT id FROM tl_gallery_creator_albums ORDER BY RAND()')
                    ;

                    echo (new JsonResponse(['ids' => $objDb->fetchEach('id')]))->getContent();
                    exit();
                }

                if (Input::get('albumId')) {
                    $objAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('albumId'));

                    if (null !== $objAlbum) {
                        if (Input::get('checkTables') || Input::get('reviseTables')) {
                            // Delete damaged data records
                            $cleanDb = $this->User->isAdmin && Input::get('reviseTables') ? true : false;
                            GcHelper::reviseTables($objAlbum, $cleanDb);

                            if ($this->session->has('gc_error') && \is_array($this->session->get('gc_error'))) {
                                if (!empty($this->session->get('gc_error'))) {
                                    $arrErrors = $this->session->get('gc_error');

                                    if (!empty($arrErrors)) {
                                        echo (new JsonResponse(['errors' => $arrErrors]))->getContent();
                                        exit();
                                    }
                                }
                            }
                        }
                    }
                    $this->session->remove('gc_error');
                }
            }
            echo (new Response('', Response::HTTP_NO_CONTENT))->getContent();
            exit();
        }
    }

    /**
     * Label callback for the album listing.
     *
     * @param array  $row
     * @param string $label
     *
     * @return string
     */
    public function labelCb($row, $label)
    {
        $mysql = Database::getInstance()
            ->prepare('SELECT count(id) as countImg FROM tl_gallery_creator_pictures WHERE pid=?')
            ->execute($row['id'])
        ;

        $objAlbum = GalleryCreatorAlbumsModel::findByPk($row['id']);

        $label = str_replace('#count_pics#', $mysql->countImg, $label);
        $label = str_replace('#datum#', Date::parse(Config::get('dateFormat'), $row['date']), $label);
        $image = $row['published'] ? 'picture_edit.png' : 'picture_edit_1.png';
        $label = str_replace('#icon#', 'bundles/markocupicgallerycreator/images/'.$image, $label);
        $href = sprintf('contao?do=gallery_creator&table=tl_gallery_creator_albums&id=%s&act=edit&rt=%s&ref=%s', $row['id'], REQUEST_TOKEN, TL_REFERER_ID);
        $label = str_replace('#href#', $href, $label);
        $label = str_replace('#title#', sprintf($GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit_album'][1], $row['id']), $label);
        $level = $this->albumUtil->getAlbumLevelFromPid((int) $row['pid']);
        $padding = $this->isNode($objAlbum) ? 3 * $level : 20 + (3 * $level);
        $label = str_replace('#padding-left#', 'padding-left:'.$padding.'px;', $label);

        return $label;
    }

    /**
     * load-callback for uploader type.
     *
     * @return string
     */
    public function loadCbGetUploader()
    {
        return $this->User->gcBeUploaderTemplate;
    }

    /**
     * load-callback for image-quality.
     *
     * @return string
     */
    public function loadCbGetImageQuality()
    {
        return $this->User->gcImageQuality;
    }

    /**
     * Load callback for image resolution.
     */
    public function loadCbGetImageResolution(): string
    {
        return $this->User->gcImageResolution;
    }

    /**
     * Buttons callback.
     */
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        if ('reviseDatabase' === Input::get('mode')) {
            // Remove buttons
            unset($arrButtons['saveNcreate'], $arrButtons['saveNclose']);

            $arrButtons['save'] = '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'][0].'</button>';
        }

        if ('fileupload' === Input::get('mode')) {
            // Remove buttons
            unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate']);
        }

        if ('importImages' === Input::get('mode')) {
            // Remove buttons
            unset($arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['uploadNback']);
        }

        return $arrButtons;
    }

    /**
     * Parse Backend Template Hook.
     */
    public function parseBackendTemplate(string $strContent, string $strTemplate): string
    {
        if ('fileupload' === Input::get('mode')) {
            // form encode
            $strContent = str_replace('application/x-www-form-urlencoded', 'multipart/form-data', $strContent);
        }

        return $strContent;
    }

    /**
     * on-delete-callback.
     */
    public function ondeleteCb(DataContainer $dc): void
    {
        if ('deleteAll' !== Input::get('act')) {
            $this->checkUserRole($dc->id);

            if ($this->restrictedUser) {
                $this->log('An unauthorized user tried to delete an entry from tl_gallery_creator_albums with ID '.Input::get('id').'.', __METHOD__, TL_ERROR);
                $this->redirect('contao?do=error');
            }
            // Also delete the child element
            $arrDeletedAlbums = GalleryCreatorAlbumsModel::getChildAlbums(Input::get('id'));
            $arrDeletedAlbums = array_merge([Input::get('id')], $arrDeletedAlbums);

            foreach ($arrDeletedAlbums as $idDelAlbum) {
                $objAlbumModel = GalleryCreatorAlbumsModel::findByPk($idDelAlbum);

                if (null === $objAlbumModel) {
                    continue;
                }

                if ($this->User->isAdmin || (int) $objAlbumModel->owner === (int) $this->User->id || !$this->galleryCreatorBackendWriteProtection) {
                    // remove all pictures from tl_gallery_creator_pictures
                    $objPicturesModel = GalleryCreatorPicturesModel::findByPid($idDelAlbum);

                    if (null !== $objPicturesModel) {
                        while ($objPicturesModel->next()) {
                            $fileUuid = $objPicturesModel->uuid;
                            $objPicturesModel->delete();
                            $objPicture = GalleryCreatorPicturesModel::findByUuid($fileUuid);

                            if (null === $objPicture) {
                                $oFile = FilesModel::findByUuid($fileUuid);

                                if (null !== $oFile) {
                                    $file = new File($oFile->path);
                                    $file->delete();
                                }
                            }
                        }
                    }
                    // Remove the albums from tl_gallery_creator_albums and then
                    // remove the directory from the filesystem
                    $oFolder = FilesModel::findByUuid($objAlbumModel->assignedDir);

                    if (null !== $oFolder) {
                        $folder = new Folder($oFolder->path);

                        if ($folder->isEmpty()) {
                            $folder->delete();
                        }
                    }
                    $objAlbumModel->delete();
                } else {
                    // Do not delete albums that are not owned by the user.
                    Database::getInstance()
                        ->prepare('UPDATE tl_gallery_creator_albums SET pid=? WHERE id=?')
                        ->execute('0', $idDelAlbum)
                    ;
                }
            }
        }
    }

    /**
     * Onload callback
     * Check if upload folder exists.
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
     * Onload callback
     * Initiate the file upload.
     */
    public function onloadCbFileupload(): void
    {
        if ('fileupload' !== Input::get('mode')) {
            return;
        }

        // Load language file
        $this->loadLanguageFile('tl_files');

        // Album ID
        $intAlbumId = (int) Input::get('id');

        // Save uploaded files in $_FILES['file']
        $strName = 'file';

        // Get the album object
        $blnNoAlbum = false;
        $objAlbum = GalleryCreatorAlbumsModel::findById($intAlbumId);

        if (null === $objAlbum) {
            Message::addError('Album with ID '.$intAlbumId.' does not exist.');
            $blnNoAlbum = true;
        }

        // Check for a valid upload directory
        $blnNoUploadDir = false;
        $objUploadDir = FilesModel::findByUuid($objAlbum->assignedDir);

        if (null === $objUploadDir || !is_dir($this->projectDir.'/'.$objUploadDir->path)) {
            Message::addError('No upload directory defined in the album settings!');
            $blnNoUploadDir = true;
        }

        // Exit if there is no upload or the upload directory is missing
        if (!\is_array($_FILES[$strName]) || $blnNoUploadDir || $blnNoAlbum) {
            return;
        }

        // Call the uploader script
        $arrUpload = $this->fileUtil->fileupload($objAlbum, $strName);

        foreach ($arrUpload as $strFileSrc) {
            // Add  new data records into tl_gallery_creator_pictures
            $this->fileUtil->addImageToAlbum($objAlbum, $strFileSrc);
        }

        // Do not exit script if html5_uploader is selected and Javascript is disabled
        if (!Input::post('submit')) {
            exit;
        }
    }

    /**
     * Onload callback
     * Import images from an external directory to an existing album.
     *
     * @throws \Exception
     */
    public function onloadCbImportFromFilesystem(): void
    {
        if ('importImages' !== Input::get('mode')) {
            return;
        }
        // Load language file
        $this->loadLanguageFile('tl_content');

        if (!$this->Input->post('FORM_SUBMIT')) {
            return;
        }

        if (null !== ($objAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('id')))) {
            $objAlbum->preserveFilename = Input::post('preserveFilename');
            $objAlbum->save();

            // Comma separated list with folder uuid's => 10585872-5f1f-11e3-858a-0025900957c8,105e9de0-5f1f-11e3-858a-0025900957c8,105e9dd6-5f1f-11e3-858a-0025900957c8
            $arrMultiSRC = explode(',', (string) $this->Input->post('multiSRC'));

            if (!empty($arrMultiSRC)) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserveFilename']['eval']['submitOnChange'] = false;

                // import Images from filesystem and write entries to tl_gallery_creator_pictures
                $this->fileUtil->importFromFilesystem($objAlbum, $arrMultiSRC);

                throw new RedirectResponseException('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.$objAlbum->id.'&ref='.TL_REFERER_ID.'&filesImported=true');
            }
        }

        throw new RedirectResponseException('contao?do=gallery_creator');
    }

    /**
     * Onload callback
     * Create the palette.
     */
    public function onloadCbSetUpPalettes(): void
    {
        $dca = &$GLOBALS['TL_DCA']['tl_gallery_creator_albums'];

        // Permit global operations to admin only
        if (!$this->User->isAdmin) {
            unset(
                $dca['list']['global_operations']['all'],
                $dca['list']['global_operations']['reviseDatabase']
            );
        }

        // For security reasons give only readonly rights to these fields
        $dca['fields']['id']['eval']['style'] = '" readonly="readonly';
        $dca['fields']['ownersName']['eval']['style'] = '" readonly="readonly';

        // Create the file uploader palette
        if ('fileupload' === Input::get('mode')) {
            if ('no_scaling' === $this->User->gcImageResolution) {
                $dca['palettes']['fileupload'] = str_replace(',imageQuality', '', $dca['palettes']['fileupload']);
            }

            $dca['palettes']['default'] = $dca['palettes']['fileupload'];

            return;
        }

        // Create the *importImages* palette
        if ('importImages' === Input::get('mode')) {
            $dca['palettes']['default'] = $dca['palettes']['importImages'];
            $dca['fields']['preserveFilename']['eval']['submitOnChange'] = false;

            return;
        }

        // The palette for admins
        if ($this->User->isAdmin) {
            $objAlb = Database::getInstance()
                ->prepare('SELECT id FROM tl_gallery_creator_albums')
                ->limit(1)
                ->execute()
            ;

            if ($objAlb->numRows) {
                $dca['list']['global_operations']['reviseDatabase']['href'] = 'act=edit&table&mode=reviseDatabase&id='.$objAlb->id;
            } else {
                unset($dca['list']['global_operations']['reviseDatabase']);
            }

            if ('reviseDatabase' === Input::get('mode')) {
                $dca['palettes']['default'] = $dca['palettes']['reviseDatabase'];

                return;
            }

            $dca['fields']['owner']['eval']['doNotShow'] = false;
            $dca['fields']['protected']['eval']['doNotShow'] = false;
            $dca['fields']['groups']['eval']['doNotShow'] = false;

            return;
        }

        $objAlb = Database::getInstance()
            ->prepare('SELECT id, owner FROM tl_gallery_creator_albums WHERE id=?')
            ->execute(Input::get('id'))
        ;

        // Give write access on these fields to admins and album owners only.
        $this->checkUserRole(Input::get('id'));

        if ($objAlb->owner !== $this->User->id && true === $this->restrictedUser) {
            $dca['palettes']['default'] = $dca['palettes']['restrictedUser'];
        }
    }

    /**
     * Input field callback for the album preview thumb select
     * List each image of the album (and child-albums).
     */
    public function inputFieldCbThumb(): string
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('id'));

        // Save input
        if ('tl_gallery_creator_albums' === Input::post('FORM_SUBMIT')) {
            if (null === GalleryCreatorPicturesModel::findByPk(Input::post('thumb'))) {
                $objAlbum->thumb = 0;
            } else {
                $objAlbum->thumb = Input::post('thumb');
            }
            $objAlbum->save();
        }

        $arrAlbums = [];
        $arrSubalbums = [];

        // Generate picture list
        $objPicture = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? ORDER BY sorting')
            ->execute(Input::get('id'))
        ;

        if ($objPicture->numRows) {
            while ($objPicture->next()) {
                $arrAlbums[] = [
                    'uuid' => $objPicture->uuid,
                    'id' => $objPicture->id,
                ];
            }
        }

        // Get all child albums
        $arrSubIds = GalleryCreatorAlbumsModel::getChildAlbums(Input::get('id'));

        if (\count($arrSubIds)) {
            $objPicture = Database::getInstance()
                ->execute('SELECT * FROM tl_gallery_creator_pictures WHERE pid IN ('.implode(',', $arrSubIds).') ORDER BY id')
            ;

            while ($objPicture->next()) {
                $arrSubalbums[] = [
                    'uuid' => $objPicture->uuid,
                    'id' => $objPicture->id,
                ];
            }
        }

        $arrContainer = [
            $arrAlbums,
            $arrSubalbums,
        ];

        foreach ($arrContainer as $i => $arrData) {
            foreach ($arrData as $ii => $arrItem) {
                $objFileModel = FilesModel::findByUuid($arrItem['uuid']);

                if (null !== $objFileModel) {
                    if (file_exists($this->projectDir.'/'.$objFileModel->path)) {
                        $objFile = new \File($objFileModel->path);
                        $src = 'placeholder.png';

                        if ($objFile->height <= Config::get('gdMaxImgHeight') && $objFile->width <= Config::get('gdMaxImgWidth')) {
                            $src = Image::get($objFile->path, 80, 60, 'center_center');
                        }

                        $arrContainer[$i][$ii]['attr_checked'] = $checked = (int) $objAlbum->thumb === (int) $arrItem['id'] ? ' checked' : '';
                        $arrContainer[$i][$ii]['class'] = '' !== \strlen($checked) ? ' class="checked"' : '';
                        $arrContainer[$i][$ii]['filename'] = StringUtil::specialchars($objFile->name);
                        $arrContainer[$i][$ii]['image'] = Image::getHtml($src, $objFile->name);
                    }
                }
            }
        }

        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/album_thumbnail_list.html.twig',
                [
                    'album_thumbs' => $arrContainer[0],
                    'sub_album_thumbs' => $arrContainer[1],
                    'has_album_thumbs' => \count($arrContainer[0]) ? true : false,
                    'has_sub_album_thumbs' => \count($arrContainer[1]) ? true : false,
                    'trans' => [
                        'album_thumb' => $translator->trans('tl_gallery_creator_albums.thumb.0', [], 'contao_default'),
                        'drag_items_hint' => $translator->trans('tl_gallery_creator_albums.thumb.1', [], 'contao_default'),
                        'sub_albums' => $translator->trans('gallery_creator.subalbums', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Save callback.
     */
    public function saveCbValidateFilePrefix(string $strPrefix, DataContainer $dc): string
    {
        $i = 0;

        if ('' !== $strPrefix) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', \Transliterator::FORWARD);
            $strPrefix = $transliterator->transliterate($strPrefix);
            $strPrefix = str_replace('.', '_', $strPrefix);

            $arrOptions = [
                'column' => ['tl_gallery_creator_pictures.pid=?'],
                'value' => [$dc->id],
                'order' => 'sorting ASC',
            ];
            $objPicture = GalleryCreatorPicturesModel::findAll($arrOptions);

            if (null !== $objPicture) {
                while ($objPicture->next()) {
                    $objFile = FilesModel::findOneByUuid($objPicture->uuid);

                    if (null !== $objFile) {
                        if (is_file($this->projectDir.'/'.$objFile->path)) {
                            $oFile = new File($objFile->path);
                            ++$i;

                            while (is_file($oFile->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($oFile->extension))) {
                                ++$i;
                            }
                            $oldPath = $oFile->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($oFile->extension);
                            $newPath = str_replace($this->projectDir.'/', '', $oldPath);

                            // Rename file
                            if ($oFile->renameTo($newPath)) {
                                $objPicture->path = $oFile->path;
                                $objPicture->save();
                                Message::addInfo(sprintf('Picture with ID %s has been renamed to %s.', $objPicture->id, $newPath));
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
     * Sort images by a selectable field.
     */
    public function saveCbSortAlbum(string $varValue, DataContainer $dc): string
    {
        if ('----' === $varValue) {
            return $varValue;
        }

        $objPictures = GalleryCreatorPicturesModel::findByPid($dc->id);

        if (null === $objPictures) {
            return '----';
        }

        $files = [];
        $auxDate = [];

        while ($objPictures->next()) {
            $oFile = FilesModel::findByUuid($objPictures->uuid);
            $objFile = new File($oFile->path, true);
            $files[$oFile->path] = [
                'id' => $objPictures->id,
            ];
            $auxDate[] = $objFile->mtime;
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
            $objPicture = GalleryCreatorPicturesModel::findByPk($arrFile['id']);
            $objPicture->sorting = $sorting;
            $objPicture->save();
        }

        // Return default value
        return '----';
    }

    /**
     * generate an album alias based on the album name,
     * then create a directory with the same name
     * and register the directory in tl_files.
     *
     * @param $strAlias
     *
     * @return mixed|string
     */
    public function saveCbGenerateAlias(string $strAlias, DataContainer $dc)
    {
        $blnDoNotCreateDir = false;

        // Get current row
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($dc->id);

        if (null === $objAlbum) {
            return;
        }

        // Save assigned directory if it has been defined.
        if ($this->Input->post('FORM_SUBMIT') && \strlen((string) $this->Input->post('assignedDir'))) {
            $objAlbum->assignedDir = $this->Input->post('assignedDir');
            $objAlbum->save();
            $blnDoNotCreateDir = true;
        }

        $strAlias = StringUtil::standardize($strAlias);

        // If there isn't an existing album alias, generate one based on the album name
        if (!\strlen($strAlias)) {
            $strAlias = standardize($dc->activeRecord->name);
        }

        // Limit alias to 50 characters.
        $strAlias = substr($strAlias, 0, 43);

        // Remove invalid characters.
        $strAlias = preg_replace('/[^a-z0-9\\_\\-]/', '', $strAlias);

        // If alias already exists, append the album id to the alias.
        $objAlb = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id!=? AND alias=?')
            ->execute($dc->activeRecord->id, $strAlias)
        ;

        if ($objAlb->numRows) {
            $strAlias = 'id-'.$dc->id.'-'.$strAlias;
        }

        // Create default upload folder.
        if (false === $blnDoNotCreateDir) {
            // Create the new folder and register it in tl_files
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
     * Save callback for the file uploader.
     */
    public function saveCbSaveUploader(string $strTemplate): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gcBeUploaderTemplate=? WHERE id=?')
            ->execute($strTemplate, $this->User->id)
        ;
    }

    /**
     * Save callback for the image quality, which can be set in the file upload form.
     *
     * @param $value
     */
    public function saveCbSaveImageQuality($value): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gcImageQuality=? WHERE id=?')
            ->execute($value, $this->User->id)
        ;
    }

    /**
     * Save callback for the image resolution, which can be set in the file upload form.
     *
     * @param $value
     */
    public function saveCbSaveImageResolution($value): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gcImageResolution=? WHERE id=?')
            ->execute($value, $this->User->id)
        ;
    }

    /**
     * Check if album has subalbums.
     */
    private function isNode(GalleryCreatorAlbumsModel $objAlbum): bool
    {
        $objAlbums = GalleryCreatorAlbumsModel::findByPid($objAlbum->id);

        if (null !== $objAlbums) {
            return true;
        }

        return false;
    }
}
