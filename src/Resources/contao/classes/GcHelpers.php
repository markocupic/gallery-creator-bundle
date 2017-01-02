<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2015 Leo Feyer
 *
 * @package Gallery Creator
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */
/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Markocupic\GalleryCreatorBundle;

use Patchwork\Utf8\Patchwork;


/**
 * Class GcHelpers
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2017
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */
class GcHelpers extends \System
{

	/**
	 * insert a new entry in tl_gallery_creator_pictures
	 *
	 * @param integer
	 * @param string
	 * $intAlbumId - albumId
	 * $strFilepath - filepath -> files/gallery_creator_albums/albumalias/filename.jpg
	 * @return bool
	 */
	public static function createNewImage($intAlbumId, $strFilepath)
	{
		//get the file-object
		$objFile = new \File($strFilepath);
		if (!$objFile->isGdImage)
		{
			return false;
		}

		//get the album-object
		$objAlbum = \GalleryCreatorAlbumsModel::findById($intAlbumId);

		// get the assigned album directory
		$objFolder = \FilesModel::findByUuid($objAlbum->assignedDir);
		$assignedDir = null;
		if ($objFolder !== null)
		{
			if (is_dir(TL_ROOT . '/' . $objFolder->path))
			{
				$assignedDir = $objFolder->path;
			}
		}
		if ($assignedDir == null)
		{
			die('Aborted Script, because there is no upload directory assigned to the Album with ID ' . $intAlbumId);
		}

		// Check if the file ist stored in the album-directory or if it is stored in an external directory
		$blnExternalFile = false;
		if (\Input::get('importFromFilesystem'))
		{
			$blnExternalFile = strstr($objFile->dirname, $assignedDir) ? false : true;
		}

		// Get the album object and the alias
		$strAlbumAlias = $objAlbum->alias;
		// Db insert
		$objImg = new \GalleryCreatorPicturesModel();
		$objImg->tstamp = time();
		$objImg->pid = $objAlbum->id;
		$objImg->externalFile = $blnExternalFile ? "1" : "";
		$objImg->save();


		$insertId = $objImg->id;
		// Get the next sorting index
		$objImg_2 = \Database::getInstance()->prepare('SELECT MAX(sorting)+10 AS maximum FROM tl_gallery_creator_pictures WHERE pid=?')->execute($objAlbum->id);
		$sorting = $objImg_2->maximum;

		// If filename should be generated
		if (!$objAlbum->preserve_filename && $blnExternalFile === false)
		{
			$newFilepath = sprintf('%s/alb%s_img%s.%s', $assignedDir, $objAlbum->id, $insertId, $objFile->extension);
			$objFile->renameTo($newFilepath);
		}


		if (is_file(TL_ROOT . '/' . $objFile->path))
		{
			// Get the userId
			$userId = '0';
			if (TL_MODE == 'BE')
			{
				$userId = \BackendUser::getInstance()->id;
			}
			// The album-owner is automaticaly the image owner, if the image was uploaded by a by a frontend user
			if (TL_MODE == 'FE')
			{
				$userId = $objAlbum->owner;
			}

			// Get the FilesModel
			$objFileModel = \FilesModel::findByPath($objFile->path);

			// Finally save the new image in tl_gallery_creator_pictures
			$objImg->uuid = $objFileModel->uuid;
			$objImg->owner = $userId;
			$objImg->date = $objAlbum->date;
			$objImg->sorting = $sorting;
			$objImg->save();

			\System::log('A new version of tl_gallery_creator_pictures ID ' . $insertId . ' has been created', __METHOD__, TL_GENERAL);

			// Check for a valid preview-thumb for the album
			$objAlbum = \GalleryCreatorAlbumsModel::findByAlias($strAlbumAlias);
			if ($objAlbum !== null)
			{
				if ($objAlbum->thumb == "")
				{
					$objAlbum->thumb = $insertId;
					$objAlbum->save();
				}
			}

			// GalleryCreatorImagePostInsert - HOOK
			// übergibt die id des neu erstellten db-Eintrages ($lastInsertId)
			if (isset($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert']) && is_array($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert']))
			{
				foreach ($GLOBALS['TL_HOOKS']['galleryCreatorImagePostInsert'] as $callback)
				{
					$objClass = self::importStatic($callback[0]);
					$objClass->$callback[1]($insertId);
				}
			}

			return true;
		}
		else
		{
			if ($blnExternalFile === true)
			{
				$_SESSION['TL_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file'], $strFilepath);
			}
			else
			{
				$_SESSION['TL_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['uploadError'], $strFilepath);
			}
			\System::log('Unable to create the new image in: ' . $strFilepath . '!', __METHOD__, TL_ERROR);
		}


		return false;
	}

	/**
	 * move uploaded file to the album directory
	 *
	 * @param $intAlbumId
	 * @param string $strName
	 * @return array
	 */
	public static function fileupload($intAlbumId, $strName = 'file')
	{

		$blnIsError = false;

		// Get the album object
		$objAlb = \GalleryCreatorAlbumsModel::findById($intAlbumId);
		if ($objAlb === null)
		{
			$blnIsError = true;
			\Message::addError('Album with ID ' . $intAlbumId . ' does not exist.');
		}

		// Check for a valid upload directory
		$objUploadDir = \FilesModel::findByUuid($objAlb->assignedDir);
		if ($objUploadDir === null || !is_dir(TL_ROOT . '/' . $objUploadDir->path))
		{
			$blnIsError = true;
			\Message::addError('No upload directory defined in the album settings!');
		}

		// Check if there are some files in $_FILES
		if (!is_array($_FILES[$strName]))
		{
			$blnIsError = true;
			\Message::addError('No Files selected for the uploader.');
		}

		if ($blnIsError)
		{
			return [];
		}

		// Adapt $_FILES if files are loaded up by jumploader (java applet)
		if (!is_array($_FILES[$strName]['name']))
		{
			$arrFile = array(
				'name' => $_FILES[$strName]['name'],
				'type' => $_FILES[$strName]['type'],
				'tmp_name' => $_FILES[$strName]['tmp_name'],
				'error' => $_FILES[$strName]['error'],
				'size' => $_FILES[$strName]['size'],
			);

			unset($_FILES);

			// Rebuild $_FILES for the Contao FileUpload class
			$_FILES[$strName]['name'][0] = $arrFile['name'];
			$_FILES[$strName]['type'][0] = $arrFile['type'];
			$_FILES[$strName]['tmp_name'][0] = $arrFile['tmp_name'];
			$_FILES[$strName]['error'][0] = $arrFile['error'];
			$_FILES[$strName]['size'][0] = $arrFile['size'];
		}

		// Do not overwrite files of the same filename
		$intCount = count($_FILES[$strName]['name']);
		for ($i = 0; $i < $intCount; $i++)
		{
			if (strlen($_FILES[$strName]['name'][$i]))
			{
				// Generate unique filename
				$_FILES[$strName]['name'][$i] = basename(self::generateUniqueFilename($objUploadDir->path . '/' . $_FILES[$strName]['name'][$i]));
			}
		}

		// Resize image if feature is enabled
		if (\Input::post('img_resolution') > 1)
		{
			\Config::set('imageWidth', \Input::post('img_resolution'));
			\Config::set('jpgQuality', \Input::post('img_quality'));
		}
		else
		{
			\Config::set('maxImageWidth', 999999999);
		}

		// Call the Contao FileUpload class
		$objUpload = new \FileUpload();
		$objUpload->setName($strName);
		$arrUpload = $objUpload->uploadTo($objUploadDir->path);

		foreach ($arrUpload as $strFileSrc)
		{
			// Store file in tl_files
			\Dbafs::addResource($strFileSrc);
		}

		return $arrUpload;
	}

	/**
	 * generate a unique filepath for a new picture
	 * @param $strFilename
	 * @return bool|string
	 * @throws Exception
	 */
	public static function generateUniqueFilename($strFilename)
	{

		$strFilename = strip_tags($strFilename);
		$strFilename = \Patchwork\Utf8::toAscii($strFilename);
		$strFilename = str_replace('"', '', $strFilename);
		$strFilename = str_replace(' ', '_', $strFilename);

		if (preg_match('/\.$/', $strFilename))
		{
			throw new Exception($GLOBALS['TL_LANG']['ERR']['invalidName']);
		}
		$pathinfo = pathinfo($strFilename);
		$extension = $pathinfo['extension'];
		$basename = basename($strFilename, '.' . $extension);
		$dirname = dirname($strFilename);

		// Falls Datei schon existiert, wird hinten eine Zahl mit fuehrenden Nullen angehaengt -> filename0001.jpg
		$i = 0;
		$isUnique = false;
		do
		{
			$i++;
			if (!file_exists(TL_ROOT . '/' . $dirname . '/' . $basename . '.' . $extension))
			{
				// Exit loop when filename is unique
				return $dirname . '/' . $basename . '.' . $extension;
			}
			else
			{
				if ($i != 1)
				{
					$filename = substr($basename, 0, -5);
				}
				else
				{
					$filename = $basename;
				}
				$suffix = str_pad($i, 4, '0', STR_PAD_LEFT);
				// Integer mit fuehrenden Nullen an den Dateinamen anhaengen ->filename0001.jpg
				$basename = $filename . '_' . $suffix;

				// Break after 100 loops and generate random filename
				if ($i == 100)
				{
					return $dirname . '/' . md5($basename . microtime()) . '.' . $extension;
				}
			}
		} while ($isUnique === false);

		return false;
	}

	/**
	 * generate the jumploader applet
	 * @param string $uploader
	 * @return string
	 */
	public static function generateUploader($uploader = 'be_gc_html5_uploader')
	{

		// Create the template object
		$objTemplate = new \BackendTemplate($uploader);


		// MaxFileSize
		$objTemplate->maxFileSize = $GLOBALS['TL_CONFIG']['maxFileSize'];

		// $_FILES['file']
		$objTemplate->strName = 'file';

		// Parse the jumloader view and return it
		return $objTemplate->parse();
	}

	/**
	 * Returns the information-array about an album
	 *
	 * @param $intAlbumId
	 * @param $objContentElement
	 * @return array
	 */
	public static function getAlbumInformationArray($intAlbumId, $objContentElement)
	{
		global $objPage;
		// Get the page model
		$objPageModel = \PageModel::findByPk($objPage->id);

		$objAlbum = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')->execute($intAlbumId);

		// Anzahl Subalben ermitteln
		$objSubAlbums = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE published=? AND pid=? GROUP BY ?')->execute('1', $intAlbumId, 'id');

		$objPics = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? AND published=?')->execute($objAlbum->id, '1');

		// Array Thumbnailbreite
		$arrSize = unserialize($objContentElement->gc_size_albumlisting);

		$href = null;
		if (TL_MODE == 'FE')
		{
			// Generate the url as a formated string
			$href = $objPageModel->getFrontendUrl(($GLOBALS['TL_CONFIG']['useAutoItem'] ? '/%s' : '/items/%s'), $objPage->language);
			// Add albumAlias
			$href = sprintf($href, $objAlbum->alias);
		}

		$arrPreviewThumb = $objContentElement->getAlbumPreviewThumb($objAlbum->id);
		$strImageSrc = $arrPreviewThumb['path'];

		// Generate the thumbnails and the picture element
		try
		{
			$thumbSrc = \Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
			$picture = \Picture::create($strImageSrc, $arrSize)->getTemplateData();

			if ($thumbSrc !== $strImageSrc)
			{
				new \File(rawurldecode($thumbSrc), true);
			}
		} catch (\Exception $e)
		{
			\System::log('Image "' . $strImageSrc . '" could not be processed: ' . $e->getMessage(), __METHOD__, TL_ERROR);
		}

		$picture['alt'] = specialchars($objAlbum->name);
		$picture['title'] = specialchars($objAlbum->name);

		// CSS class
		$strCSSClass = \GalleryCreatorAlbumsModel::hasChildAlbums($objAlbum->id) ? 'has-child-album' : '';
		$strCSSClass .= $objPics->numRows < 1 ? ' empty-album' : '';

		$arrAlbum = array(
			'id' => $objAlbum->id,
			//[int] pid parent Album-Id
			'pid' => $objAlbum->pid,
			//[int] Sortierindex
			'sorting' => $objAlbum->sorting,
			//[boolean] veroeffentlicht (true/false)
			'published' => $objAlbum->published,
			//[int] id des Albumbesitzers
			'owner' => $objAlbum->owner,
			//[string] Benutzername des Albumbesitzers
			'owners_name' => $objAlbum->owners_name,
			//[int] Zeitstempel der letzten Aenderung
			'tstamp' => $objAlbum->tstamp,
			//[int] Event-Unix-timestamp (unformatiert)
			'event_tstamp' => $objAlbum->date,
			'date' => $objAlbum->date,
			//[string] Event-Datum (formatiert)
			'event_date' => \Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $objAlbum->date),
			//[string] Event-Location
			'event_location' => specialchars($objAlbum->event_location),
			//[string] Albumname
			'name' => specialchars($objAlbum->name),
			//[string] Albumalias (=Verzeichnisname)
			'alias' => $objAlbum->alias,
			//[string] Albumkommentar
			'comment' => $objPage->outputFormat == 'xhtml' ? \StringUtil::toXhtml(nl2br_xhtml($objAlbum->comment)) : \StringUtil::toHtml5(nl2br_html5($objAlbum->comment)),
			'caption' => $objPage->outputFormat == 'xhtml' ? \StringUtil::toXhtml(nl2br_xhtml($objAlbum->comment)) : \StringUtil::toHtml5(nl2br_html5($objAlbum->comment)),
			//[int] Albumbesucher (Anzahl Klicks)
			'visitors' => $objAlbum->visitors,
			//[string] Link zur Detailansicht
			'href' => $href,
			//[string] Inhalt fuer das title Attribut
			'title' => $objAlbum->name . ' [' . ($objPics->numRows ? $objPics->numRows . ' ' . $GLOBALS['TL_LANG']['gallery_creator']['pictures'] : '') . ($objContentElement->gc_hierarchicalOutput && $objSubAlbums->numRows > 0 ? ' ' . $GLOBALS['TL_LANG']['gallery_creator']['contains'] . ' ' . $objSubAlbums->numRows . '  ' . $GLOBALS['TL_LANG']['gallery_creator']['subalbums'] . ']' : ']'),
			//[int] Anzahl Bilder im Album
			'count' => $objPics->numRows,
			//[int] Anzahl Unteralben
			'count_subalbums' => count(\GalleryCreatorAlbumsModel::getChildAlbums($objAlbum->id)),
			//[string] alt Attribut fuer das Vorschaubild
			'alt' => $arrPreviewThumb['name'],
			//[string] Pfad zum Originalbild
			'src' => TL_FILES_URL . $arrPreviewThumb['path'],
			//[string] Pfad zum Thumbnail
			'thumb_src' => TL_FILES_URL . \Image::get($arrPreviewThumb['path'], $arrSize[0], $arrSize[1], $arrSize[2]),
			//[int] article id
			'insert_article_pre' => $objAlbum->insert_article_pre ? $objAlbum->insert_article_pre : null,
			//[int] article id
			'insert_article_post' => $objAlbum->insert_article_post ? $objAlbum->insert_article_post : null,
			//[string] css-Classname
			'class' => 'thumb',
			//[int] Thumbnailgrösse
			'size' => $arrSize,
			//[string] javascript-Aufruf
			'thumbMouseover' => $objContentElement->gc_activateThumbSlider ? "objGalleryCreator.initThumbSlide(this," . $objAlbum->id . "," . $objPics->numRows . ");" : "",
			//[array] picture
			'picture' => $picture,
			//[string] cssClass
			'cssClass' => trim($strCSSClass),
		);

		// Fuegt dem Array weitere Eintraege hinzu, falls tl_gallery_creator_albums erweitert wurde
		$objAlbum = \GalleryCreatorAlbumsModel::findByPk($intAlbumId);
		if ($objAlbum !== null)
		{
			$arrAlbum = array_merge($objAlbum->row(), $arrAlbum);
		}
		return $arrAlbum;
	}

	/**
	 * Returns the information-array about an album
	 *
	 * @param null $intPictureId
	 * @param $objContentElement
	 * @return array|null
	 */
	public static function getPictureInformationArray($intPictureId = null, $objContentElement)
	{

		if ($intPictureId < 1)
		{
			return null;
		}
		global $objPage;

		$hasCustomThumb = false;


		$defaultThumbSRC = $objContentElement->defaultThumb;
		if (\Config::get('gc_error404_thumb') !== '')
		{
			$objFile = \FilesModel::findByUuid(\Config::get('gc_error404_thumb'));
			if ($objFile !== null)
			{
				if (\Validator::isUuid(\Config::get('gc_error404_thumb')))
				{
					if (is_file(TL_ROOT . '/' . $objFile->path))
					{
						$defaultThumbSRC = $objFile->path;
					}
				}
			}
		}

		// Get the page model
		$objPageModel = \PageModel::findByPk($objPage->id);

		$objPicture = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE id=?')->execute($intPictureId);

		// Alle Informationen zum Album in ein array packen
		$objAlbum = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')->execute($objPicture->pid);
		$arrAlbumInfo = $objAlbum->fetchAssoc();

		// Bild-Besitzer
		$objOwner = \Database::getInstance()->prepare('SELECT * FROM tl_user WHERE id=?')->execute($objPicture->owner);
		$arrMeta = [];
		$objFileModel = \FilesModel::findByUuid($objPicture->uuid);
		if ($objFileModel == null)
		{
			$strImageSrc = $defaultThumbSRC;
		}
		else
		{
			$strImageSrc = $objFileModel->path;
			if (!is_file(TL_ROOT . '/' . $strImageSrc))
			{
				// Fallback to the default thumb
				$strImageSrc = $defaultThumbSRC;
			}

			// Meta
			$arrMeta = $objContentElement->getMetaData($objFileModel->meta, $objPage->language);
			// Use the file name as title if none is given
			if ($arrMeta['title'] == '')
			{
				$arrMeta['title'] = specialchars($objFileModel->name);
			}
		}


		// Get thumb dimensions
		$arrSize = unserialize($objContentElement->gc_size_detailview);

		// Generate the thumbnails and the picture element
		try
		{
			$thumbSrc = \Image::create($strImageSrc, $arrSize)->executeResize()->getResizedPath();
			// Overwrite $thumbSrc if there is a valid custom thumb
			if ($objPicture->addCustomThumb && !empty($objPicture->customThumb))
			{
				$customThumbModel = \FilesModel::findByUuid($objPicture->customThumb);
				if ($customThumbModel !== null)
				{
					if (is_file(TL_ROOT . '/' . $customThumbModel->path))
					{
						$objFileCustomThumb = new \File($customThumbModel->path, true);
						if ($objFileCustomThumb->isGdImage)
						{
							$thumbSrc = \Image::create($objFileCustomThumb->path, $arrSize)->executeResize()->getResizedPath();
							$hasCustomThumb = true;
						}
					}
				}
			}
			$thumbPath = $hasCustomThumb ? $objFileCustomThumb->path : $strImageSrc;
			$picture = \Picture::create($thumbPath, $arrSize)->getTemplateData();

		} catch (\Exception $e)
		{
			\System::log('Image "' . $strImageSrc . '" could not be processed: ' . $e->getMessage(), __METHOD__, TL_ERROR);
			$thumbSrc = '';
			$picture = array('img' => array('src' => '', 'srcset' => ''), 'sources' => []);
		}

		$picture['alt'] = $objPicture->title != '' ? specialchars($objPicture->title) : specialchars($arrMeta['title']);
		$picture['title'] = $objPicture->comment != '' ? ($objPage->outputFormat == 'xhtml' ? specialchars(\StringUtil::toXhtml($objPicture->comment)) : specialchars(\StringUtil::toHtml5($objPicture->comment))) : specialchars($arrMeta['caption']);

		$objFileThumb = new \File(rawurldecode($thumbSrc));
		$arrSize[0] = $objFileThumb->width;
		$arrSize[1] = $objFileThumb->height;
		$arrFile["thumb_width"] = $objFileThumb->width;
		$arrFile["thumb_height"] = $objFileThumb->height;

		// Get some image params
		if (is_file(TL_ROOT . '/' . $strImageSrc))
		{
			$objFileImage = new \File($strImageSrc);
			if (!$objFileImage->isGdImage)
			{
				return null;
			}
			$arrFile["path"] = $objFileImage->path;
			$arrFile["basename"] = $objFileImage->basename;
			// Filename without extension
			$arrFile["filename"] = $objFileImage->filename;
			$arrFile["extension"] = $objFileImage->extension;
			$arrFile["dirname"] = $objFileImage->dirname;
			$arrFile["image_width"] = $objFileImage->width;
			$arrFile["image_height"] = $objFileImage->height;
		}
		else
		{
			return null;
		}


		// Exif
		if ($GLOBALS['TL_CONFIG']['gc_read_exif'])
		{
			try
			{
				$exif = is_callable('exif_read_data') && TL_MODE == 'FE' ? exif_read_data($strImageSrc) : array('info' => "The function 'exif_read_data()' is not available on this server.");
			} catch (Exception $e)
			{
				$exif = array('info' => "The function 'exif_read_data()' is not available on this server.");
			}
		}
		else
		{
			$exif = array('info' => "The function 'exif_read_data()' has not been activated in the Contao backend settings.");
		}

		// Video-integration
		$strMediaSrc = trim($objPicture->socialMediaSRC) != "" ? trim($objPicture->socialMediaSRC) : "";
		if (\Validator::isUuid($objPicture->localMediaSRC))
		{
			// Get path of a local Media
			$objMovieFile = \FilesModel::findById($objPicture->localMediaSRC);
			$strMediaSrc = $objMovieFile !== null ? $objMovieFile->path : $strMediaSrc;
		}
		$href = null;
		if (TL_MODE == 'FE' && $objContentElement->gc_fullsize)
		{
			$href = $strMediaSrc != "" ? $strMediaSrc : TL_FILES_URL . \System::urlEncode($strImageSrc);
		}

		// CssID
		$cssID = deserialize($objPicture->cssID, true);

		// Build the array
		$arrPicture = array(
			'id' => $objPicture->id,
			//[int] pid parent Album-Id
			'pid' => $objPicture->pid,
			//[int] das Datum, welches fuer das Bild gesetzt werden soll (= in der Regel das Upload-Datum)
			'date' => $objPicture->date,
			//[int] id des Albumbesitzers
			'owner' => $objPicture->owner,
			//Name des Erstellers
			'owners_name' => $objOwner->name,
			//[int] album_id oder pid
			'album_id' => $objPicture->pid,
			//[string] name (basename/filename of the file)
			'name' => specialchars($arrFile["basename"]),
			//[string] filename without extension
			'filename' => $arrFile["filename"],
			//[string] Pfad zur Datei
			'uuid' => $objPicture->uuid,
			// uuid of the image
			'path' => $arrFile["path"],
			//[string] basename similar to name
			'basename' => $arrFile["basename"],
			//[string] dirname
			'dirname' => $arrFile["dirname"],
			//[string] file-extension
			'extension' => $arrFile["extension"],
			//[string] alt-attribut
			'alt' => $objPicture->title != '' ? specialchars($objPicture->title) : specialchars($arrMeta['title']),
			//[string] title-attribut
			'title' => $objPicture->title != '' ? specialchars($objPicture->title) : specialchars($arrMeta['title']),
			//[string] Bildkommentar oder Bildbeschreibung
			'comment' => $objPicture->comment != '' ? ($objPage->outputFormat == 'xhtml' ? specialchars(\StringUtil::toXhtml($objPicture->comment)) : specialchars(\StringUtil::toHtml5($objPicture->comment))) : specialchars($arrMeta['caption']),
			'caption' => $objPicture->comment != '' ? ($objPage->outputFormat == 'xhtml' ? specialchars(\StringUtil::toXhtml($objPicture->comment)) : specialchars(\StringUtil::toHtml5($objPicture->comment))) : specialchars($arrMeta['caption']),
			//[string] path to media (video, picture, sound...)
			'href' => $href,
			// single image url
			'single_image_url' => $objPageModel->getFrontendUrl(($GLOBALS['TL_CONFIG']['useAutoItem'] ? '/' : '/items/') . \Input::get('items') . '/img/' . $arrFile["filename"], $objPage->language),
			//[string] path to the image,
			'image_src' => $arrFile["path"],
			//[string] path to the other selected media
			'media_src' => $strMediaSrc,
			//[string] path to a media on a social-media-plattform
			'socialMediaSRC' => $objPicture->socialMediaSRC,
			//[string] path to a media stored on the webserver
			'localMediaSRC' => $objPicture->localMediaSRC,
			//[string] Pfad zu einem benutzerdefinierten Thumbnail
			'addCustomThumb' => $objPicture->addCustomThumb,
			//[string] Thumbnailquelle
			'thumb_src' => isset($thumbSrc) ? TL_FILES_URL . $thumbSrc : '',
			//[array] Thumbnail-Ausmasse Array $arrSize[Breite, Hoehe, Methode]
			'size' => $arrSize,
			//[int] thumb-width in px
			'thumb_width' => $arrFile["thumb_width"],
			//[int] thumb-height in px
			'thumb_height' => $arrFile["thumb_height"],
			//[int] image-width in px
			'image_width' => $arrFile["image_width"],
			//[int] image-height in px
			'image_height' => $arrFile["image_height"],
			//[int] das rel oder data-lightbox Attribut fuer das Anzeigen der Bilder in der Lightbox
			'lightbox' => $objPage->outputFormat == 'xhtml' ? 'rel="lightbox[lb' . $objPicture->pid . ']"' : 'data-lightbox="lb' . $objPicture->pid . '"',
			//[int] Zeitstempel der letzten Aenderung
			'tstamp' => $objPicture->tstamp,
			//[int] Sortierindex
			'sorting' => $objPicture->sorting,
			//[boolean] veroeffentlicht (true/false)
			'published' => $objPicture->published,
			//[array] Array mit exif metatags
			'exif' => $exif,
			//[array] Array mit allen Albuminformation (albumname, owners_name...)
			'albuminfo' => $arrAlbumInfo,
			//[array] Array mit Bildinfos aus den meta-Angaben der Datei, gespeichert in tl_files.meta
			'metaData' => $arrMeta,
			//[string] css-ID des Bildcontainers
			'cssID' => $cssID[0] != '' ? $cssID[0] : '',
			//[string] css-Klasse des Bildcontainers
			'cssClass' => $cssID[1] != '' ? $cssID[1] : '',
			//[bool] true, wenn es sich um ein Bild handelt, das nicht in files/gallery_creator_albums/albumname gespeichert ist
			'externalFile' => $objPicture->externalFile,
			// [array] picture
			'picture' => $picture,
		);

		// Fuegt dem Array weitere Eintraege hinzu, falls tl_gallery_creator_pictures erweitert wurde
		$objPicture = \GalleryCreatorPicturesModel::findByPk($intPictureId);
		if ($objPicture !== null)
		{
			$arrPicture = array_merge($objPicture->row(), $arrPicture);
		}

		return $arrPicture;
	}

	/**
	 * Returns the information-array about all subalbums ofd a certain parent album
	 *
	 * @param $intAlbumId
	 * @param $objContentElement
	 * @return array
	 */
	public static function getSubalbumsInformationArray($intAlbumId, $objContentElement)
	{

		$strSorting = $objContentElement->gc_sorting . ' ' . $objContentElement->gc_sorting_direction;
		$objSubAlbums = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE pid=? AND published=? ORDER BY ' . $strSorting)->execute($intAlbumId, '1');
		$arrSubalbums = [];
		while ($objSubAlbums->next())
		{
			// If it is a content element only
			if ($objContentElement->gc_publish_albums != '')
			{
				if (!$objContentElement->gc_publish_all_albums)
				{
					if (!in_array($objSubAlbums->id, deserialize($objContentElement->gc_publish_albums)))
					{
						continue;
					}
				}
			}

			$arrSubalbum = self::getAlbumInformationArray($objSubAlbums->id, $objContentElement);
			array_push($arrSubalbums, $arrSubalbum);
		}

		return $arrSubalbums;
	}

	/**
	 * @param string
	 * @param integer
	 * @return bool
	 * $imgPath - relative path to the filesource
	 * angle - the rotation angle is interpreted as the number of degrees to rotate the image anticlockwise.
	 * angle shall be 0,90,180,270
	 */
	public static function imageRotate($imgPath, $angle)
	{

		if ($angle == 0)
		{
			return false;
		}
		if ($angle % 90 !== 0)
		{
			return false;
		}
		if ($angle < 90 || $angle > 360)
		{
			return false;
		}
		if (!function_exists('imagerotate'))
		{
			return false;
		}

		// Chmod
		\Files::getInstance()->chmod($imgPath, 0777);

		// Load
		if (TL_MODE == 'BE')
		{
			$imgSrc = '../' . $imgPath;
		}
		else
		{
			$imgSrc = $imgPath;
		}
		$source = imagecreatefromjpeg($imgSrc);

		// Rotate
		$imgTmp = imagerotate($source, $angle, 0);

		// Output
		imagejpeg($imgTmp, $imgSrc);
		imagedestroy($source);

		// Chmod
		\Files::getInstance()->chmod($imgPath, 0644);

		return true;
	}

	/**
	 * @param integer
	 * @param string
	 * Bilder aus Verzeichnis auf dem Server in Album einlesen
	 */
	public static function importFromFilesystem($intAlbumId, $strMultiSRC)
	{

		$images = [];

		$objFilesModel = \FilesModel::findMultipleByUuids(explode(',', $strMultiSRC));
		if ($objFilesModel === null)
		{
			return;
		}

		while ($objFilesModel->next())
		{

			// Continue if the file has been processed or does not exist
			if (isset($images[$objFilesModel->path]) || !file_exists(TL_ROOT . '/' . $objFilesModel->path))
			{
				continue;
			}


			// If item is a file, then store it in the array
			if ($objFilesModel->type == 'file')
			{
				$objFile = new \File($objFilesModel->path);
				if ($objFile->isGdImage)
				{
					$images[$objFile->path] = array('uuid' => $objFilesModel->uuid, 'basename' => $objFile->basename, 'path' => $objFile->path);
				}
			}
			else
			{

				// If it is a directory, then store its files in the array
				$objSubfilesModel = \FilesModel::findMultipleFilesByFolder($objFilesModel->path);
				if ($objSubfilesModel === null)
				{
					continue;
				}


				while ($objSubfilesModel->next())
				{

					// Skip subfolders
					if ($objSubfilesModel->type == 'folder' || !is_file(TL_ROOT . '/' . $objSubfilesModel->path))
					{
						continue;
					}

					$objFile = new \File($objSubfilesModel->path);
					if ($objFile->isGdImage)
					{
						$images[$objFile->path] = array('uuid' => $objSubfilesModel->uuid, 'basename' => $objFile->basename, 'path' => $objFile->path);
					}
				}

			}
		}

		if (count($images))
		{
			$arrPictures = array(
				'uuid' => [],
				'path' => [],
				'basename' => [],
			);

			$objPictures = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=?')->execute($intAlbumId);
			$arrPictures['uuid'] = $objPictures->fetchEach('uuid');
			$arrPictures['path'] = $objPictures->fetchEach('path');
			foreach ($arrPictures['path'] as $path)
			{
				$arrPictures['basename'][] = basename($path);
			}

			$objAlb = \GalleryCreatorAlbumsModel::findById($intAlbumId);
			foreach ($images as $image)
			{
				// Prevent duplicate entries
				if (in_array($image['uuid'], $arrPictures['uuid']))
				{
					continue;
				}

				// Prevent duplicate entries
				if (in_array($image['basename'], $arrPictures['basename']))
				{
					continue;
				}

				\Input::setGet('importFromFilesystem', 'true');
				if ($GLOBALS['TL_CONFIG']['gc_album_import_copy_files'])
				{

					$strSource = $image['path'];

					// Get the album upload directory
					$objFolderModel = \FilesModel::findByUuid($objAlb->assignedDir);
					$errMsg = 'Aborted import process, because there is no upload folder assigned to the album with ID ' . $objAlb->id . '.';
					if ($objFolderModel === null)
					{
						die($errMsg);
					}
					if ($objFolderModel->type != 'folder')
					{
						die($errMsg);
					}
					if (!is_dir(TL_ROOT . '/' . $objFolderModel->path))
					{
						die($errMsg);
					}

					$strDestination = self::generateUniqueFilename($objFolderModel->path . '/' . basename($strSource));
					if (is_file(TL_ROOT . '/' . $strSource))
					{
						// Copy Image to the upload folder
						$objFile = new \File($strSource);
						$objFile->copyTo($strDestination);
						\Dbafs::addResource($strSource);
					}

					self::createNewImage($objAlb->id, $strDestination);
				}
				else
				{
					self::createNewImage($objAlb->id, $image['path']);
				}
			}
		}
	}

	/**
	 * revise tables
	 * @param $albumId
	 * @param bool $blnCleanDb
	 */
	public static function reviseTables($albumId, $blnCleanDb = false)
	{
		$_SESSION['GC_ERROR'] = [];

		// Upload-Verzeichnis erstellen, falls nicht mehr vorhanden
		new \Folder(GALLERY_CREATOR_UPLOAD_PATH);

		// Get album model
		$objAlbum = \GalleryCreatorAlbumsModel::findByPk($albumId);
		if ($objAlbum === null)
		{
			return;
		}

		// Check for valid album owner
		$objUser = \UserModel::findByPk($objAlbum->owner);
		if ($objUser !== null)
		{
			$owner = $objUser->name;
		}
		else
		{
			$owner = "no-name";
		}
		$objAlbum->owners_name = $owner;
		$objAlbum->save();

		// Check for valid pid
		if ($objAlbum->pid > 0)
		{
			$objParentAlb = $objAlbum->getRelated('pid');
			if ($objParentAlb === null)
			{
				$objAlbum->pid = null;
				$objAlbum->save();
			}
		}


		if (\Database::getInstance()->fieldExists('path', 'tl_gallery_creator_pictures'))
		{

			// Datensaetzen ohne gültige uuid über den Feldinhalt path versuchen zu "retten"
			$objPictures = \GalleryCreatorPicturesModel::findByPid($albumId);
			if ($objPictures !== null)
			{
				while ($objPictures->next())
				{
					// Get parent album
					$objFile = \FilesModel::findByUuid($objPictures->uuid);
					if ($objFile === null)
					{
						if ($objPictures->path != '')
						{
							if (is_file(TL_ROOT . '/' . $objPictures->path))
							{
								$objModel = \Dbafs::addResource($objPictures->path);
								if (\Validator::isUuid($objModel->uuid))
								{
									$objPictures->uuid = $objModel->uuid;
									$objPictures->save();
									continue;
								}
							}
						}
						if ($blnCleanDb !== false)
						{
							$msg = ' Deleted Datarecord with ID ' . $objPictures->id . '.';
							$_SESSION['GC_ERROR'][] = $msg;
							$objPictures->delete();
						}
						else
						{
							// Show the error-message
							$path = $objPictures->path != '' ? $objPictures->path : 'unknown path';
							$_SESSION['GC_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $path, $objAlbum->alias);
						}
					}
					elseif (!is_file(TL_ROOT . '/' . $objFile->path))
					{
						// If file has an entry in Dbafs, but doesn't exist on the server anymore
						if ($blnCleanDb !== false)
						{
							$msg = 'Deleted Datarecord with ID ' . $objPictures->id . '.';
							$_SESSION['GC_ERROR'][] = $msg;
							$objPictures->delete();
						}
						else
						{
							$_SESSION['GC_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'], $objPictures->id, $objFile->path, $objAlbum->alias);
						}
					}
					else
					{
						// Pfadangaben mit tl_files.path abgleichen (Redundanz)
						if ($objPictures->path != $objFile->path)
						{
							$objPictures->path = $objFile->path;
							$objPictures->save();
						}
					}
				}
			}
		}


		/**
		 * Sorgt dafuer, dass in tl_content im Feld gc_publish_albums keine verwaisten AlbumId's vorhanden sind
		 * Prueft, ob die im Inhaltselement definiertern Alben auch noch existieren.
		 * Wenn nein, werden diese aus dem Array entfernt.
		 */
		$objCont = \Database::getInstance()->prepare('SELECT * FROM tl_content WHERE type=?')->execute('gallery_creator');
		while ($objCont->next())
		{
			$newArr = [];
			$arrAlbums = unserialize($objCont->gc_publish_albums);
			if (is_array($arrAlbums))
			{
				foreach ($arrAlbums as $AlbumID)
				{
					$objAlb = \Database::getInstance()->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id=?')->limit('1')->execute($AlbumID);
					if ($objAlb->next())
					{
						$newArr[] = $AlbumID;
					}
				}
			}
			\Database::getInstance()->prepare('UPDATE tl_content SET gc_publish_albums=? WHERE id=?')->execute(serialize($newArr), $objCont->id);
		}
	}

	/**
	 * return the level of an album or subalbum (level_0, level_1, level_2,...)
	 * @param integer
	 * @return integer
	 */
	public static function getAlbumLevel($pid)
	{

		$level = 0;
		if ($pid == '0')
		{
			return $level;
		}
		$hasParent = true;
		while ($hasParent)
		{
			$level++;
			$objAlbum = \GalleryCreatorAlbumsModel::findByPk($pid);
			if ($objAlbum->pid < 1)
			{
				$hasParent = false;
			}
			$pid = $objAlbum->pid;
		}

		return $level;
	}
}
