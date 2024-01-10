<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

$GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file'] = "Die Datei \"%s\" wurde nicht auf dem Server gefunden!";
$GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'] = "Der Datenbankeintrag mit ID %s in \"tl_gallery_creator_pictures\" verweist auf eine nicht vorhandene Datei. Bitte räumen Sie die Datenbank auf oder überprüfen Sie die Existenz der Datei %s im Album mit dem Alias: %s!";
$GLOBALS['TL_LANG']['ERR']['uploadError'] = "Die Datei \"%s\" konnte nicht hochgeladen werden!";
$GLOBALS['TL_LANG']['ERR']['fileDontExist'] = "Die Datei \"%s\" existiert nicht!";
$GLOBALS['TL_LANG']['ERR']['fileNotReadable'] = "Die Datei \"%s\" ist nicht lesbar! Zugriffsrechte müssen überprüft werden.";
$GLOBALS['TL_LANG']['ERR']['dirNotWriteable'] = "Das Verzeichnis \"%s\" ist nicht beschreibbar! Zugriffsrechte müssen manuell überprüft werden.";
$GLOBALS['TL_LANG']['ERR']['accept_jpg'] = "Gallery Creator unterstützt nur jpeg/jpg-Dateien.";
$GLOBALS['TL_LANG']['gallery_creator']['back_to_general_view'] = "zurück zur Übersicht";
$GLOBALS['TL_LANG']['gallery_creator']['subalbums'] = "Unteralben";
$GLOBALS['TL_LANG']['gallery_creator']['subalbums_of'] = "Unteralben von";
$GLOBALS['TL_LANG']['gallery_creator']['pictures'] = "Bilder";
$GLOBALS['TL_LANG']['gallery_creator']['contains'] = "enthält";
$GLOBALS['TL_LANG']['gallery_creator']['visitors'] = "Aufrufe";
$GLOBALS['TL_LANG']['gallery_creator']['photographerName'] = 'Fotograf';
$GLOBALS['TL_LANG']['gallery_creator']['eventLocation'] = 'Veranstaltungsort';
$GLOBALS['TL_LANG']['gallery_creator']['fe_authentication_error']['0'] = "Authentifizierungsfehler";
$GLOBALS['TL_LANG']['gallery_creator']['fe_authentication_error']['1'] = "Der Zugriff auf dieses Album wurde verweigert. Bitte melden Sie sich als Frontend-User an oder überprüfen Sie Ihre Benutzerrechte.";
$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'] = "Soll das Album mit der ID %s gelöscht werden? \\r\\nAchtung! Es werden auch alle Bilddateien im zugeordneten Verzeichnis gelöscht!!!";
$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'] = "Soll das Bild mit der ID %s gelöscht werden? \\r\\nAchtung! Es wird auch die Bilddatei aus dem Verzeichnis gelöscht!!!";
