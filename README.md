<p align="center"><a href="https://github.com/markocupic"><img src="docs/logo.png" width="200"></a></p>

# Gallery Creator Bundle

## Frontend Modul für [Contao CMS](https://www.contao.org)

With this extension for [Contao CMS](https://www.contao.org) you can create,
 display and manage photo albums.
 The Gallery Creator Bundle offers an album listing and an album detail view.
 As a lightbox we recommend the [Glightbox](https://biati-digital.github.io/glightbox/) plugin,
 which you can install like this: `composer require inspiredminds/contao-glightbox`.
 Please ensure, that you have activated the lightbox template
 in the layout settings of your theme in the Contao backend.

## Configuration
This gallery extension is shipped with a default configuration.
 If you want to override these settings, you
 can do this in your common configuration file located in `config/config.yml`.

```yaml
# config/config.yml
# Gallery Creator (default settings)
markocupic_gallery_creator:
  upload_path: 'files/gallery_creator_albums'
  backend_write_protection: false
  copy_images_on_import: true
  read_exif_meta_data: false

# Contao configuration
contao:
 url_suffix: ''
 #....
```


## "gc_generateFrontendTemplate"-Hook
The "gc_generateFrontendTemplate" hook can be used to adapt the frontend output.
 The "gc_generateFrontendTemplate" hook is executed before the Gallery Creator frontend template
 is ready to be parsed. This hook requires the content element model the current album object in the detail view.
 There is no return value expected.


```php
<?php
// src/EventListener/GenerateGalleryCreatorFrontendTemplateListener.php
namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Template;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

/**
 * @Hook(GenerateGalleryCreatorFrontendTemplateListener::HOOK)
 */
class GenerateGalleryCreatorFrontendTemplateListener
{
    public const HOOK = 'gc_generateFrontendTemplate';

    public function __invoke(GalleryCreatorController $contentElement, Template $template, GalleryCreatorAlbumsModel $albumsModel)
    {
        $template->foo = 'bar';
    }
}

```


Have fun!
