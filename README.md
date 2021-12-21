<p align="center"><a href="https://github.com/markocupic"><img src="docs/logo.png" width="200"></a></p>

# Gallery Creator Bundle

## Frontend and backend extension for [Contao CMS](https://www.contao.org)

Use this extension to create, display and manage photo albums in your [Contao](https://www.contao.org) installation.
 The Gallery Creator Bundle offers an album listing and an album detail view.

## Installation
Please run the `composer require markocupic/gallery-creator-bundle` in your CLI to install the extension.

## Lightbox
 As a lightbox we strongly recommend [Glightbox](https://biati-digital.github.io/glightbox/).
 Simply run the `composer require inspiredminds/contao-glightbox` command in your CLI.
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
  valid_extensions: ['jpg', 'jpeg', 'gif', 'png', 'tif', 'tiff', 'bmp', 'svg', 'svgz', 'webp']
  
# Contao configuration
contao:
 url_suffix: ''
 #....
```

## "gc_generateFrontendTemplate"-Hook
Use the "gc_generateFrontendTemplate" hook to adapt the frontend output.
The "gc_generateFrontendTemplate" hook is triggered before the gallery creator front end template is parsed.
 It passes the content element object, the template object and the album object.
 The "gc_generateFrontendTemplate" hook expects no return value.

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
