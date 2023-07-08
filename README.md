# Gallery Creator

## Frontend Modul für Contao 4.13

Mit dem Modul lassen sich Alben verwalten und erstellen. Das Modul ist sehr flexibel und bietet eine Albenübersicht und eine Detailansicht.

## Installation über composer
```php
    require markocupic/gallery-creator-bundle
```

Jetzt noch die Datenbank über das Installtool aktualisieren. Danach sollte Gallery Creator unter Contao 4 laufen.
## Migration von gallery_creator nach gallery-creator-bundle
Migration einer älteren gallery_creator Version für Contao 3.5 ist möglich.

## Zusätzliche Templates
Weitere Templates findest du unter: https://gist.github.com/markocupic
* Nur Albenauflistung ohne Detailseite. Mit Klick auf Vorschau-Thumbnail öffnet sich Colorbox und zeigt den Inhalt des Albums. https://gist.github.com/markocupic/327413038262b2f84171f8df177cf021

## "gc_generateFrontendTemplate"-Hook
Mit dem "gc_generateFrontendTemplate"-Hook lässt sich die Frontend-Ausgabe anpassen.
Der "gc_generateFrontendTemplate"-Hook wird vor der Aufbereitung des Gallery-Creator-Frontend-Templates ausgeführt. Er übergibt das Modul-Objekt und in der Detailansicht das aktuelle Album-Objekt. Als Rückgabewert wird das Template-Objekt erwartet.



```php
<?php
// config.php
$GLOBALS['TL_HOOKS']['gc_generateFrontendTemplate'][] = array('MyGalleryCreatorClass', 'doSomething');

// MyGalleryCreatorClass.php
class MyGalleryCreatorClass extends \System
{

       /**
        * Do some custom modifications
        * @param Module $objModule
        * @param null $objAlbum
        * @return mixed
        */
       public function doSomething(\Module $objModule, $objAlbum=null)
       {
              global $objPage;
              $objPage->pageTitle = 'Bildergalerie';
              if($objAlbum !== null)
              {
                     // display the album name in the head section of your page (title tag)
                     $objPage->pageTitle = \StringUtil::specialchars($objAlbum->name);
                     // display the album comment in the head section of your page (description tag)
                     $objPage->description = \StringUtil::specialchars(strip_tags($objAlbum->comment));
                     // add the album name to the keywords in the head section of your page (keywords tag)
                     $GLOBALS['TL_KEYWORDS'] .= ',' . \StringUtil::specialchars($objAlbum->name) . ',' . \StringUtil::specialchars($objAlbum->event_location);
              }
              return $objModule->Template;
       }
}
```


Viel Spass mit Gallery Creator!!!

