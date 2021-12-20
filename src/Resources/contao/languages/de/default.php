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

$GLOBALS['TL_LANG']['ERR']['fileNotFound'] = 'Die Datei "%s" wurde nicht auf dem Server gefunden!';
$GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'] = 'Der Datenbankeintrag zum Bild mit ID %s in "tl_gallery_creator_pictures" verweist auf eine nicht vorhandene Datei. Bitte bereinigen Sie die Datenbank oder überprüfen Sie die Existenz der Datei "%s" im Album mit dem Album-Alias: "%s".';
$GLOBALS['TL_LANG']['ERR']['uploadError'] = 'Die Datei "%s" konnte nicht hochgeladen werden!';
$GLOBALS['TL_LANG']['ERR']['dirNotWriteable'] = 'Das Verzeichnis "%s" ist nicht beschreibbar! Zugriffsrechte müssen manuell überprüft werden.';
$GLOBALS['TL_LANG']['ERR']['rejectWriteAccessToAlbum'] = 'Sie haben nicht die erforderlichen Rechte das Album "%s" zu bearbeiten.';
$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmAlbum'] = 'Soll das Album mit der ID %s gelöscht werden? \\r\\nAchtung! Es werden auch alle Bilddateien im zugeordneten Verzeichnis gelöscht!!!';
$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmPicture'] = 'Soll das Bild mit der ID %s gelöscht werden? \\r\\nAchtung! Es wird auch die Bilddatei aus dem Verzeichnis gelöscht!!!';


$GLOBALS['TL_LANG']['GALLERY_CREATOR']['backToOverview'] = 'zurück zur Übersicht';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbums'] = 'Unteralben';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbumsOf'] = 'Unteralben von';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['pictures'] = 'Bilder';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['contains'] = 'enthält';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['visitors'] = 'Aufrufe';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['photographerName'] = 'Fotograf';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['eventLocation'] = 'Location';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['feAuthentificationError'] = ['Authentifizierungsfehler', 'Der Zugriff auf dieses Album wurde verweigert. Bitte melden Sie sich als Frontend-User an oder überprüfen Sie Ihre Benutzerrechte.'];
