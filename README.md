# Gallery Creator

## Frontend Modul für Contao >=4.3

Mit dem Modul lassen sich Alben verwalten und erstellen. Das Modul ist sehr flexibel und bietet eine Albenübersicht und eine Detailansicht.

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
                     $objPage->pageTitle = specialchars($objAlbum->name);
                     // display the album comment in the head section of your page (description tag)
                     $objPage->description = specialchars(strip_tags($objAlbum->comment));
                     // add the album name to the keywords in the head section of your page (keywords tag)
                     $GLOBALS['TL_KEYWORDS'] .= ',' . specialchars($objAlbum->name) . ',' . specialchars($objAlbum->event_location);
              }
              return $objModule->Template;
       }
}
```


Viel Spass mit Gallery Creator!!!

