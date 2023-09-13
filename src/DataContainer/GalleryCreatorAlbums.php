<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle for Contao CMS.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Automator;
use Contao\Backend;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\DC_Table;
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
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\GalleryCreatorUtil;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GalleryCreatorAlbums extends Backend
{
    public bool $restrictedUser = false;
    public static string|null $uploadPath = null;
    private BackendUser|null $user = null;

    public function __construct()
    {
        parent::__construct();

        if (($user = System::getContainer()->get('security.helper')->getUser()) instanceof BackendUser) {
            $this->user = $user;
        }

        // path to the gallery_creator upload-directory
        $this->uploadPath = System::getContainer()->getParameter('markocupic_gallery_creator.upload_path');

        // Register the parseBackendTemplate Hook
        $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = [
            self::class,
            'setCorrectEnctype',
        ];
    }

    /**
     * Return the add-images-button.
     */
    public function buttonCbAddImages(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        $href .= 'id='.$row['id'].'&act=edit&table=tl_gallery_creator_albums&mode=fileupload';

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.' style="margin-right:5px">'.Image::getHtml($icon, $label).'</a>';
    }

    /**
     * Return the "toggle visibility" button.
     */
    public function toggleIcon(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (\strlen((string) Input::get('tid'))) {
            $this->toggleVisibility((int) Input::get('tid'), '1' === Input::get('state'));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->user->admin && $row['owner'] !== $this->user->id && !Config::get('gc_disable_backend_edit_protection')) {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')
            ->limit(1)
            ->execute($row['id'])
        ;

        if (!$this->user->admin && $row['owner'] !== $this->user->id && !Config::get('gc_disable_backend_edit_protection')) {
            return Image::getHtml($icon).' ';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    public function toggleVisibility(int $intId, bool $blnVisible): void
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intId);

        // Check permissions to publish
        if (!$this->user->admin && $objAlbum->owner !== $this->user->id && !Config::get('gc_disable_backend_edit_protection')) {
            $strText = 'Not enough permissions to publish/unpublish tl_gallery_creator_albums ID "'.$intId.'"';
            $logger = System::getContainer()->get('monolog.logger.contao.error');
            $logger->error($strText);

            $this->redirect('contao?act=error');
        }

        $objVersions = new Versions('tl_gallery_creator_albums', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (isset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback']) && \is_array($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback'])) {
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

        $strText = 'A new version of record "tl_gallery_creator_albums.id='.$intId.'" has been created.';
        $logger = System::getContainer()->get('monolog.logger.contao.general');
        $logger->info($strText);
    }

    /**
     * Return the cut-picture-button.
     */
    public function buttonCbCutPicture(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        // enable cutting albums to album-owners and admins only
        return $this->user->id === $row['owner'] || $this->user->admin || Config::get('gc_disable_backend_edit_protection') ? ' <a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : ' '.Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the delete-button.
     */
    public function buttonCbDelete(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        // enable deleting albums to album-owners and admins only
        return $this->user->admin || $this->user->id === $row['owner'] || Config::get('gc_disable_backend_edit_protection') ? '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the editheader-button.
     */
    public function buttonCbEditHeader(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id'], 1).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the edit-button.
     */
    public function buttonCbEdit(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id'], 1).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the import-images button.
     */
    public function buttonCbImportImages(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        $href .= 'id='.$row['id'].'&act=edit&table=tl_gallery_creator_albums&mode=import_images';

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a>';
    }

    /**
     * Return the paste-picture-button.
     */
    public function buttonCbPastePicture(DataContainer $dc, array $row, string $table, int $cr, $arrClipboard = false): string
    {
        $disablePA = false;
        $disablePI = false;
        // Disable all buttons if there is a circular reference
        if ($this->user->admin && false !== $arrClipboard && ('cut' === $arrClipboard['mode'] && (1 === $cr || $arrClipboard['id'] === $row['id']) || 'cutAll' === $arrClipboard['mode'] && (1 === $cr || \in_array($row['id'], $arrClipboard['id'], false)))) {
            $disablePA = true;
            $disablePI = true;
        }
        // Return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.svg', sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id']), 'class="blink"');
        $imagePasteInto = Image::getHtml('pasteinto.svg', sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id']), 'class="blink"');

        $return = '';

        if ($row['id'] > 0) {
            $return = $disablePA ? Image::getHtml('pasteafter_.svg', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&id='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
        }

        return $return.($disablePI ? Image::getHtml('pasteinto_.svg', '', 'class="blink"').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].(!\is_array($arrClipboard['id']) ? '&id='.$arrClipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ');
    }

    /**
     * Checks if the current user has full access.
     */
    public function checkUserRole($albumId): void
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);

        if ($this->user->admin || Config::get('gc_disable_backend_edit_protection')) {
            $this->restrictedUser = false;

            return;
        }

        if (null !== $objAlbum && $objAlbum->owner !== $this->user->id) {
            $this->restrictedUser = true;

            return;
        }
        // ...so the current user is the album owner
        $this->restrictedUser = false;
    }

    public function inputFieldCbCleanDb(): string
    {
        return '
<div class="widget revise_tables">
<br><br>
       	<input type="checkbox" name="revise_tables">
		<label for="revise_tables">'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['messages']['revise_database'].'</label>
</div>
			';
    }

    public function inputFieldCbGenerateAlbumInformationTable(DataContainer $dc): string
    {
        $objAlb = GalleryCreatorAlbumsModel::findByPk($dc->id);
        $objUser = UserModel::findByPk($objAlb->owner);
        $owner = null === $objUser ? 'no-name' : $objUser->name;

        // check User Role
        $this->checkUserRole($dc->id);

        if (!$this->restrictedUser) {
            return '';
        }

        return '
<div class="widget long album_infos">
<table style="margin-top: 16px">
	<tr class="odd">
		<td style="width:25%"><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['id'][0].': </strong></td>
		<td>'.$objAlb->id.'</td>
	</tr>
	<tr>
		<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date'][0].': </strong></td>
		<td>'.Date::parse('Y-m-d', $objAlb->date).'</td>
	</tr>
	<tr class="odd">
		<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owners_name'][0].': </strong></td>
		<td>'.$owner.'</td>
	</tr>
	<tr>
		<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name'][0].': </strong></td>
		<td>'.$objAlb->name.'</td>
	</tr>

	<tr class="odd">
		<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['comment'][0].': </strong></td>
		<td>'.$objAlb->comment.'</td>
	</tr>
	<tr>
		<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb'][0].': </strong></td>
		<td>'.$objAlb->thumb.'</td>
	</tr>
</table>
</div>
		';
    }

    public function inputFieldCbGenerateUploaderMarkup(): string
    {
        return GalleryCreatorUtil::generateUploader($this->user->gc_be_uploader_template);
    }

    public function isAjaxRequest(): void
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

                $response = new JsonResponse(['status' => 'success']);

                throw new ResponseException($response);
            }

            // revise table in the backend
            if (Input::get('checkTables')) {
                if (Input::get('getAlbumIDS')) {
                    $arrIds = [];
                    $objDb = Database::getInstance()
                        ->execute('SELECT id FROM tl_gallery_creator_albums ORDER BY RAND()')
                    ;

                    while ($objDb->next()) {
                        $arrIds[] = $objDb->id;
                    }

                    $response = new JsonResponse(['albumIDS' => $arrIds]);

                    throw new ResponseException($response);
                }

                if (Input::get('albumId')) {
                    $albumId = (int) Input::get('albumId');

                    if (Input::get('reviseTables') && $this->user->admin) {
                        // delete damaged data records
                        $msg = GalleryCreatorUtil::reviseTables($albumId, true);
                    } else {
                        $msg = GalleryCreatorUtil::reviseTables($albumId);
                    }

                    if (!empty($msg)) {
                        $strError = implode('***', $msg);
                        $response = new JsonResponse(['errors' => $strError]);

                        throw new ResponseException($response);
                    }

                    $response = new JsonResponse(['status' => 'success']);

                    throw new ResponseException($response);
                }
            }
        }
    }

    public function labelCb(array $row, string $label): string
    {
        $mysql = Database::getInstance()
            ->prepare('SELECT count(id) as countImg FROM tl_gallery_creator_pictures WHERE pid=?')
            ->execute($row['id'])
        ;
        $label = str_replace('#count_pics#', (string) $mysql->countImg, $label);
        $label = str_replace('#datum#', Date::parse(Config::get('dateFormat'), $row['date']), $label);
        $image = $row['published'] ? 'picture_edit.png' : 'picture_edit_1.png';
        $label = str_replace('#icon#', 'bundles/markocupicgallerycreator/images/'.$image, $label);
        $href = sprintf(
            'contao?do=gallery_creator&table=tl_gallery_creator_albums&id=%s&act=edit&rt=%s&ref=%s',
            $row['id'],
            System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
            System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id'),
        );
        $label = str_replace('#href#', $href, $label);
        $label = str_replace('#title#', sprintf($GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit_album'][1], $row['id']), $label);
        $level = GalleryCreatorUtil::getAlbumLevel($row['pid']);
        $padding = $this->isNode($row['id']) ? 3 * $level : 4 + (3 * $level);

        return str_replace('#padding-left#', 'padding-left:'.$padding.'px;', $label);
    }

    public function loadCbGetUploader(): string
    {
        return $this->user->gc_be_uploader_template;
    }

    public function loadCbGetImageQuality(): int
    {
        return (int) $this->user->gc_img_quality;
    }

    public function loadCbGetImageResolution(): int
    {
        return (int) $this->user->gc_img_resolution;
    }

    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        if ('revise_tables' === Input::get('mode')) {
            // remove buttons
            unset($arrButtons['saveNcreate'], $arrButtons['saveNclose']);

            $arrButtons['save'] = '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'][0].'</button>';
        }

        if ('fileupload' === Input::get('mode')) {
            // remove buttons
            unset($arrButtons['save'], $arrButtons['saveNclose'], $arrButtons['saveNcreate']);
        }

        if ('import_images' === Input::get('mode')) {
            // remove buttons
            unset($arrButtons['saveNclose'], $arrButtons['saveNcreate'], $arrButtons['uploadNback']);
        }

        return $arrButtons;
    }

    /**
     * Parse backend template hook.
     */
    public function setCorrectEnctype(string $strContent, string $strTemplate): string
    {
        if ('fileupload' === Input::get('mode') && 'be_main' === $strTemplate) {
            // form encode
            $strContent = str_replace('application/x-www-form-urlencoded', 'multipart/form-data', $strContent);
        }

        return $strContent;
    }

    public function ondeleteCb(DataContainer $dc): void
    {
        if ($dc->id && 'deleteAll' !== Input::get('act')) {
            $this->checkUserRole($dc->id);

            if ($this->restrictedUser) {
                $strText = "An attempt was made to delete record with ID '$dc->id' from tl_gallery_creator_albums by an unauthorized user.";
                $logger = System::getContainer()->get('monolog.logger.contao.error');
                $logger->error($strText);

                $this->redirect('contao?do=error');
            }
            // also delete the child element
            $arrDeletedAlbums = GalleryCreatorAlbumsModel::getChildAlbums((int) $dc->id);
            $arrDeletedAlbums = array_merge([$dc->id], $arrDeletedAlbums);

            foreach ($arrDeletedAlbums as $idDelAlbum) {
                $objAlbumModel = GalleryCreatorAlbumsModel::findByPk($idDelAlbum);

                if (null === $objAlbumModel) {
                    continue;
                }

                if ($this->user->admin || $objAlbumModel->owner === $this->user->id || Config::get('gc_disable_backend_edit_protection')) {
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
                    // remove the albums from tl_gallery_creator_albums
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
                    // do not delete child albums, which the user does not own
                    Database::getInstance()
                        ->prepare('UPDATE tl_gallery_creator_albums SET pid=? WHERE id=?')
                        ->execute(0, $idDelAlbum)
                    ;
                }
            }
        }
    }

    /**
     * checks availability of the upload-folder.
     */
    public function onloadCbCheckFolderSettings(DC_Table $dc): void
    {
        // create the upload directory if it doesn't already exist
        $objFolder = new Folder($this->uploadPath);
        $objFolder->unprotect();
        Dbafs::addResource($this->uploadPath, false);

        if (!is_writable(System::getContainer()->getParameter('kernel.project_dir').'/'.$this->uploadPath)) {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['dirNotWriteable'], $this->uploadPath));
        }
    }

    public function onloadCbFileUpload(DataContainer $dc): void
    {
        if (!$dc->id || 'fileupload' !== Input::get('mode')) {
            return;
        }

        // Load language file
        $this->loadLanguageFile('tl_files');

        // Album ID
        $intAlbumId = (int) $dc->id;

        // Save uploaded files in $_FILES['file']
        $strName = 'file';

        // Get the album object
        $blnNoAlbum = false;
        $objAlb = GalleryCreatorAlbumsModel::findById($intAlbumId);

        if (null === $objAlb) {
            Message::addError('Album with ID '.$intAlbumId.' does not exist.');
            $blnNoAlbum = true;
        }

        // Check for a valid upload directory
        $blnNoUploadDir = false;
        $objUploadDir = FilesModel::findByUuid($objAlb->assignedDir);

        if (null === $objUploadDir || !is_dir(System::getContainer()->getParameter('kernel.project_dir').'/'.$objUploadDir->path)) {
            Message::addError('No upload directory defined in the album settings!');
            $blnNoUploadDir = true;
        }

        // Exit if there is no upload or the upload directory is missing
        if (!isset($_FILES[$strName]) || !\is_array($_FILES[$strName]) || $blnNoUploadDir || $blnNoAlbum) {
            return;
        }

        // Call the uploader script
        $arrUpload = GalleryCreatorUtil::uploadFile($intAlbumId, $strName);

        foreach ($arrUpload as $strFileSrc) {
            // Add  new datarecords into tl_gallery_creator_pictures
            GalleryCreatorUtil::createNewImage($objAlb->id, $strFileSrc);
        }

        // Do not exit script if html5_uploader is selected and Javascript is disabled
        if (!Input::post('submit')) {
            $response = new Response('Enable Javascript inyour browser to run the html image uploader.');

            throw new ResponseException($response);
        }
    }

    /**
     * import images from an external directory to an existing album.
     */
    public function onloadCbImportFromFilesystem(DataContainer $dc): void
    {
        if (!$dc->id || 'import_images' !== Input::get('mode')) {
            return;
        }
        // load language file
        $this->loadLanguageFile('tl_content');

        if (!$this->Input->post('FORM_SUBMIT')) {
            return;
        }
        $intAlbumId = (int) $dc->id;

        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intAlbumId);

        if (null !== $objAlbum) {
            $objAlbum->preserve_filename = Input::post('preserve_filename');
            $objAlbum->save();
            // comma separated list with folder uuid's => 10585872-5f1f-11e3-858a-0025900957c8,105e9de0-5f1f-11e3-858a-0025900957c8,105e9dd6-5f1f-11e3-858a-0025900957c8
            $strMultiSRC = $this->Input->post('multiSRC');

            if (\strlen(trim($strMultiSRC))) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserve_filename']['eval']['submitOnChange'] = false;
                // import Images from filesystem and write entries to tl_gallery_creator_pictures
                GalleryCreatorUtil::importFromFilesystem($intAlbumId, $strMultiSRC);
                $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');
                $this->redirect('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.$intAlbumId.'&ref='.$refererId.'&filesImported=true');
            }
        }

        $this->redirect('contao?do=gallery_creator');
    }

    public function onloadCbSetUpPalettes(DataContainer $dc): void
    {
        // global_operations for admin only
        if (!$this->user->admin) {
            unset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['all'], $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']);
        }

        // for security reasons give only readonly rights to these fields
        $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['id']['eval']['style'] = '" readonly="readonly';
        $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['owners_name']['eval']['style'] = '" readonly="readonly';

        // create the image uploader palette
        if ('fileupload' === Input::get('mode')) {
            if ('no_scaling' === $this->user->gc_img_resolution) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload'] = str_replace(',img_quality', '', $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload']);
            }

            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload'];

            return;
        }

        // create the import_images palette
        if ('import_images' === Input::get('mode')) {
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['import_images'];
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserve_filename']['eval']['submitOnChange'] = false;

            return;
        }

        // the palette for admins
        if ($this->user->admin) {
            $objAlb = Database::getInstance()
                ->prepare('SELECT id FROM tl_gallery_creator_albums')
                ->limit(1)
                ->execute()
            ;

            if ($objAlb->next()) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']['href'] = 'act=edit&table&mode=revise_tables&id='.$objAlb->id;
            } else {
                unset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']);
            }

            if ('revise_tables' === Input::get('mode')) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['revise_tables'];

                return;
            }
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['owner']['eval']['doNotShow'] = false;
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['protected']['eval']['doNotShow'] = false;
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['groups']['eval']['doNotShow'] = false;

            return;
        }

        if (!empty($dc->id)) {
            $objAlb = Database::getInstance()
                ->prepare('SELECT id, owner FROM tl_gallery_creator_albums WHERE id=?')
                ->execute($dc->id)
            ;

            // only admins and album-owners obtain writing-access to these fields
            $this->checkUserRole($dc->id);

            if ($objAlb->owner !== $this->user->id && $this->restrictedUser) {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['restricted_user'];
            }
        }
    }

    /**
     * Input field callback for the album preview thumb select
     * list each image of the album (and child-albums).
     */
    public function inputFieldCbThumb(DataContainer $dc): string
    {
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($dc->id);

        // Save input
        if ('tl_gallery_creator_albums' === Input::post('FORM_SUBMIT')) {
            if (null === GalleryCreatorPicturesModel::findByPk(Input::post('thumb'))) {
                $objAlbum->thumb = 0;
            } else {
                $objAlbum->thumb = Input::post('thumb');
            }
            $objAlbum->save();
        }

        // Generate picture list
        $html = '<div class="widget long preview_thumb">';
        $html .= '<h3><label for="ctrl_thumb">'.$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb']['0'].'</label></h3>';
        $html .= '<p>'.$GLOBALS['TL_LANG']['MSC']['dragItemsHint'].'</p>';

        $html .= '<ul id="previewThumbList">';

        $objPicture = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? ORDER BY sorting')
            ->execute($dc->id)
        ;
        $arrData = [];

        while ($objPicture->next()) {
            $arrData[] = ['uuid' => $objPicture->uuid, 'id' => $objPicture->id];
        }

        // Get all child albums
        $arrSubalbums = GalleryCreatorAlbumsModel::getChildAlbums((int) $dc->id);

        if (\count($arrSubalbums)) {
            $arrData[] = ['uuid' => 'beginn_childalbums', 'id' => ''];
            $objPicture = Database::getInstance()
                ->execute('SELECT * FROM tl_gallery_creator_pictures WHERE pid IN ('.implode(',', $arrSubalbums).') ORDER BY id')
            ;

            while ($objPicture->next()) {
                $arrData[] = ['uuid' => $objPicture->uuid, 'id' => $objPicture->id];
            }
        }

        foreach ($arrData as $arrItem) {
            $uuid = $arrItem['uuid'];
            $id = $arrItem['id'];

            if ('beginn_childalbums' === $uuid) {
                $html .= '</ul><ul id="childAlbumsList">';
                continue;
            }
            $objFileModel = FilesModel::findByUuid($uuid);

            if (null !== $objFileModel) {
                if (file_exists(System::getContainer()->getParameter('kernel.project_dir').'/'.$objFileModel->path)) {
                    $objFile = new File($objFileModel->path);
                    $src = 'placeholder.png';

                    if ($objFile->height <= Config::get('gdMaxImgHeight') && $objFile->width <= Config::get('gdMaxImgWidth')) {
                        $src = Image::get($objFile->path, 80, 60, 'center_center');
                    }
                    $checked = $objAlbum->thumb === $id ? ' checked' : '';
                    $class = '' !== $checked ? ' class="checked"' : '';
                    $html .= '<li'.$class.' data-id="'.$id.'" title="'.StringUtil::specialchars($objFile->name).'"><input type="radio" name="thumb" value="'.$id.'"'.$checked.'>'.Image::getHtml($src, $objFile->name).'</li>'."\r\n";
                }
            }
        }

        $html .= '</ul>';
        $html .= '</div>';

        // Add javascript
        $script = '
<script>
	window.addEvent("domready", function() {
		$$(".preview_thumb input").addEvent("click", function(){
		    $$(".preview_thumb li").removeClass("checked");
		    this.getParent("li").addClass("checked");
		});

		/** sort album with drag and drop */
		new Sortables("#previewThumbList", {
            onComplete: function(){
                let ids = [];
                $$("#previewThumbList > li").each(function(el){
                    ids.push(el.getProperty("data-id"));
                });
                // ajax request
                if(ids.length > 0){
                    let myRequest = new Request({
                    url: document.URL + "&isAjaxRequest=true&pictureSorting=" + ids.join(),
                    method: "get"
                });
                // fire request (resort album)
                myRequest.send();
                }
            }
		});
	});
</script>
';

        // Return html
        return $html.$script;
    }

    public function saveCbValidateFilePrefix(string $strPrefix, DataContainer $dc): string
    {
        $i = 0;

        if ('' !== $strPrefix) {
            // >= php ver 5.4
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
                        if (is_file(System::getContainer()->getParameter('kernel.project_dir').'/'.$objFile->path)) {
                            $oFile = new File($objFile->path);
                            ++$i;

                            while (is_file($oFile->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($oFile->extension))) {
                                ++$i;
                            }
                            $oldPath = $oFile->dirname.'/'.$strPrefix.'_'.$i.'.'.strtolower($oFile->extension);
                            $newPath = str_replace(System::getContainer()->getParameter('kernel.project_dir').'/', '', $oldPath);
                            // rename file
                            if ($oFile->renameTo($newPath)) {
                                $objPicture->path = $oFile->path;
                                $objPicture->save();
                                Message::addInfo(sprintf('Picture with ID %s has been renamed to %s.', $objPicture->id, $newPath));
                            }
                        }
                    }
                }
                // Purge Image Cache to
                $objAutomator = new Automator();
                $objAutomator->purgeImageCache();
            }
        }

        return '';
    }

    /**
     * @throws \Exception
     */
    public function saveCbSortAlbum(string $varValue, DataContainer $dc): string
    {
        if ('' === $varValue) {
            return $varValue;
        }

        $objPictures = GalleryCreatorPicturesModel::findByPid($dc->id);

        if (null === $objPictures) {
            return '';
        }

        $files = [];
        $auxDate = [];

        while ($objPictures->next()) {
            $oFile = FilesModel::findByUuid($objPictures->uuid);
            $objFile = new File($oFile->path);
            $files[$oFile->path] = [
                'id' => $objPictures->id,
            ];
            $auxDate[] = $objFile->mtime;
        }

        switch ($varValue) {
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

        // return empty value
        return '';
    }

    /**
     * generate an album alias based on the album name and create a directory of the same name
     * and register the directory in tl files.
     */
    public function saveCbGenerateAlias(string $strAlias, DataContainer $dc): string
    {
        $blnDoNotCreateDir = false;

        // get current row
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($dc->id);

        if (null === $objAlbum) {
            throw new \Exception(sprintf('Album with alias "%s" not found', $strAlias));
        }

        // Save assigned Dir if it was defined.
        if ($this->Input->post('FORM_SUBMIT') && \strlen((string) $this->Input->post('assignedDir'))) {
            $objAlbum->assignedDir = $this->Input->post('assignedDir');
            $objAlbum->save();
            $blnDoNotCreateDir = true;
        }

        $strAlias = StringUtil::standardize($strAlias);
        // if there isn't an existing album alias generate one from the album name
        if (!\strlen($strAlias)) {
            $strAlias = StringUtil::standardize($dc->activeRecord->name);
        }

        // limit alias to 50 characters
        $strAlias = substr($strAlias, 0, 43);
        // remove invalid characters
        $strAlias = preg_replace('/[^a-z0-9_\\-]/', '', $strAlias);
        // if alias already exists add the album-id to the alias
        $objAlb = Database::getInstance()
            ->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id!=? AND alias=?')
            ->execute($dc->activeRecord->id, $strAlias)
        ;

        if ($objAlb->numRows) {
            $strAlias = 'id-'.$dc->id.'-'.$strAlias;
        }

        // Create default upload folder
        if (false === $blnDoNotCreateDir) {
            // create the new folder and register it in tl_files
            $objFolder = new Folder($this->uploadPath.'/'.$strAlias);
            $objFolder->unprotect();
            $oFolder = Dbafs::addResource($objFolder->path);
            $objAlbum->assignedDir = $oFolder->uuid;
            $objAlbum->save();
            // Important
            Input::setPost('assignedDir', StringUtil::binToUuid($objAlbum->assignedDir));
        }

        return $strAlias;
    }

    /**
     * save_callback for the uploader.
     *
     * @param $value
     */
    public function saveCbSaveUploader($value): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gc_be_uploader_template=? WHERE id=?')
            ->execute($value, $this->user->id)
        ;
    }

    public function saveCbSaveImageQuality(string $value): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gc_img_quality=? WHERE id=?')
            ->execute((int) $value, $this->user->id)
        ;
    }

    public function saveCbSaveImageResolution(string $value): void
    {
        Database::getInstance()
            ->prepare('UPDATE tl_user SET gc_img_resolution=? WHERE id=?')
            ->execute((int) $value, $this->user->id)
        ;
    }

    /**
     * Check if album contains subalbums.
     */
    private function isNode(int $id): bool
    {
        $objAlbums = GalleryCreatorAlbumsModel::findByPid($id);

        if (null !== $objAlbums) {
            return true;
        }

        return false;
    }
}
