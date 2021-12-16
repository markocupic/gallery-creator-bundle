![Logo](https://github.com/markocupic/markocupic/blob/main/logo.png)

# Gallery Creator Bundle

## Frontend Modul für [Contao CMS](https://www.contao.org) >=4.11

Mit dieser Erweiterung für Contao CMS lassen sich Alben erstellen, verwalten und anzeigen.
 Gallery Creator Bundle bietet eine Albenauflistung und eine Alben-Detailansicht.
 Als Lightbox wird die [Glightbox](https://biati-digital.github.io/glightbox/) empfohlen, welche mit `composer require inspiredminds/contao-glightbox` onstalliert werden kann.
 Im Theme muss dann nur noch das Template aktiviert werden.

## "gc_generateFrontendTemplate"-Hook
Mit dem "gc_generateFrontendTemplate"-Hook lässt sich die Frontend-Ausgabe anpassen.
Der "gc_generateFrontendTemplate"-Hook wird vor der Aufbereitung des Gallery-Creator-Frontend-Templates ausgeführt. Er übergibt das Modul-Objekt und in der Detailansicht das aktuelle Album-Objekt. Als Rückgabewert wird das Template-Objekt erwartet.


```php
<?php

namespace App\GalleryCreatorBundle;

use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\StringUtil;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;


/**
 * @Hook("gc_generateFrontendTemplate")
 */
class MyGalleryCreatorClass
{
       public function doSomething(AbstractContentElementController $controller, Template $template, ?GalleryCreatorAlbumsModel $objAlbum=null): Template
       {
              $objPage = $controller->pageModel;
              $objPage->pageTitle = 'Bildergalerie';

              if ($objAlbum !== null)
              {
                     // display the album name in the head section of your page (title tag)
                     $objPage->pageTitle = StringUtil::specialchars($objAlbum->name);

                     // display the album comment in the head section of your page (description tag)
                     $objPage->description = StringUtil::specialchars(strip_tags($objAlbum->comment));

                     // add the album name to the keywords in the head section of your page (keywords tag)
                     $GLOBALS['TL_KEYWORDS'] .= ',' . StringUtil::specialchars($objAlbum->name) . ',' . StringUtil::specialchars($objAlbum->event_location);
              }
              return $template;
       }
}
```


Viel Spass mit Gallery Creator!!!

