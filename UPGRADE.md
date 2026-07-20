# Upgrade from 3.2.x to 3.3.0

Version 3.3.0 introduces **breaking changes** for the frontend Twig templates and
**removes** two legacy hooks (`galleryCreatorGenerateFrontendTemplate` and
`galleryCreatorImagePostInsert`), which are replaced by Symfony events.

Please read the sections below before upgrading — especially if you have **copied or
overridden** any of the bundle's frontend templates, or if you registered a listener
for one of the removed hooks.

If you never customized a template and never used one of these hooks, you can upgrade
without any changes.

---

## 1. Frontend Twig template changes (breaking)

The data passed to the frontend templates was restructured. The album is now provided
as a `Markocupic\GalleryCreatorBundle\Dto\GalleryAlbumDto` and each picture as a
`Markocupic\GalleryCreatorBundle\Dto\GalleryPictureDto`. As a result, some of the
variables used inside the templates changed.

The templates shipped with the bundle already use the new variables. **If you copied
one of the templates below into your project to customize it, you must update your
copy**, otherwise the gallery will either render incorrectly or throw a Twig error.

Affected templates:

- `contao/templates/twig/component/_album.html.twig`
- `contao/templates/twig/component/_album_detail_view.html.twig`
- `contao/templates/twig/component/_album_list_view.html.twig`
- `contao/templates/twig/content_element/gallery_creator.html.twig`
- `contao/templates/twig/content_element/gallery_creator_news.html.twig`

### 1.1 The figure is now already built (breaking)

Previously the template received a `FigureBuilder` and had to call `.build`. The DTO
now exposes the already-built `Figure` object directly, so `.build` must be removed.

**Album figure** (`component/_album.html.twig`):

```twig
{# 3.2.x (old) #}
{% if album.figure.build|default %}
    {% with {figure: album.figure.build} %}{{ block('figure_component') }}{% endwith %}
{% endif %}

{# 3.3.0 (new) #}
{% if album.figure|default %}
    {% with {figure: album.figure} %}{{ block('figure_component') }}{% endwith %}
{% endif %}
```

**Picture figure** (in the `list_item` block of `content_element/gallery_creator.html.twig`
and `content_element/gallery_creator_news.html.twig`):

```twig
{# 3.2.x (old) #}
{% if showAlbumDetail and item.figure.build|default %}
    {% with {figure: item.figure.build} %}{{ block('figure_component') }}{% endwith %}
{% endif %}

{# 3.3.0 (new) #}
{% if showAlbumDetail and item.figure|default %}
    {% with {figure: item.figure} %}{{ block('figure_component') }}{% endwith %}
{% endif %}
```

### 1.2 `album.meta` renamed to `album.metadata` (breaking)

The metadata object is now exposed as `metadata` instead of `meta`. Using `album.meta`
will fail because the property no longer exists.

```twig
{# 3.2.x (old) #}
{{ album.meta.getTitle() }}

{# 3.3.0 (new) #}
{{ album.metadata.getTitle() }}
```

### 1.3 Album fields are now grouped under `album.data`

All scalar album fields are now provided through the DTO's `data` property, and the
shipped templates were updated accordingly:

| 3.2.x (old)             | 3.3.0 (new)                  |
| ----------------------- | ---------------------------- |
| `album.href`            | `album.data.href`            |
| `album.name`            | `album.data.name`            |
| `album.id`              | `album.data.id`              |
| `album.cssClass`        | `album.data.cssClass`        |
| `album.dateFormatted`   | `album.data.dateFormatted`   |
| `album.datimFormatted`  | `album.data.datimFormatted`  |
| `album.location`        | `album.data.location`        |
| `album.photographer`    | `album.data.photographer`    |
| `album.teaser`          | `album.data.teaser`          |
| `album.caption`         | `album.data.caption`         |
| `album.captionType`     | `album.data.captionType`     |
| `album.markdownCaption` | `album.data.markdownCaption` |
| `album.pictureCount`    | `album.data.pictureCount`    |
| `album.visitors`        | `album.data.visitors`        |
| `album.hasChildAlbums`  | `album.data.hasChildAlbums`  |
| `album.childAlbumCount` | `album.data.childAlbumCount` |

Any other custom album field follows the same rule: prefix it with `data.`.

> **Note:** `GalleryAlbumDto` provides a magic accessor that maps `album.<field>` to
> `album.data.<field>`, so direct access to scalar fields (e.g. `album.name`) still
> resolves to the same value and existing custom templates will keep working for those
> fields. We nevertheless recommend aligning your templates with the new
> `album.data.<field>` form to match the shipped templates and avoid ambiguity.
> This backward compatibility does **not** apply to the two breaking changes above
> (`.figure.build` and `album.meta`).

---

## 2. Removed hook: `galleryCreatorGenerateFrontendTemplate`

The legacy Contao hook `galleryCreatorGenerateFrontendTemplate` has been **removed**.
It is replaced by the Symfony event
`Markocupic\GalleryCreatorBundle\Event\GenerateFrontendTemplateEvent`, which is
dispatched right before the gallery frontend template is rendered.

If you registered a listener for the old hook, migrate it to an event listener.

### Before (3.2.x) — hook

```php
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;

#[AsHook('galleryCreatorGenerateFrontendTemplate')]
class MyGalleryTemplateListener
{
    public function __invoke(AbstractContentElementController $contentElement, FragmentTemplate $template, GalleryCreatorAlbumsModel|null $activeAlbum = null): void
    {
        $template->set('foo', 'bar');
    }
}
```

### After (3.3.0) — event listener

```php
use Markocupic\GalleryCreatorBundle\Event\GenerateFrontendTemplateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MyGalleryTemplateListener
{
    public function __invoke(GenerateFrontendTemplateEvent $event): void
    {
        $template = $event->getTemplate();
        $activeAlbum = $event->getAlbumsModel();
        $contentElement = $event->getContentElement();
        $request = $event->getRequest();

        $template->set('foo', 'bar');
    }
}
```

The event exposes the following getters:

- `getContentElement(): Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController`
- `getTemplate(): Contao\CoreBundle\Twig\FragmentTemplate`
- `getRequest(): Symfony\Component\HttpFoundation\Request`
- `getAlbumsModel(): Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel|null`

As with the old hook, the listener mutates the template in place; no return value is
expected.

---

## 3. Removed hook: `galleryCreatorImagePostInsert`

The legacy Contao hook `galleryCreatorImagePostInsert` has been **removed**. It is replaced
by the Symfony event `Markocupic\GalleryCreatorBundle\Event\ImagePostInsertEvent`, which is
dispatched right after a new image has been uploaded and written to the database.

If you registered a listener for the old hook, migrate it to an event listener.

### Before (3.2.x) — hook

```php
use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Symfony\Bundle\SecurityBundle\Security;

#[AsHook('galleryCreatorImagePostInsert')]
class MyImagePostInsertListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function __invoke(GalleryCreatorPicturesModel $picturesModel): void
    {
        $user = $this->security->getUser();

        if ($user instanceof BackendUser && $user->name) {
            $picturesModel->caption = 'Holidays '.date('Y').', Photo: '.$user->name;
            $picturesModel->save();
        }
    }
}
```

### After (3.3.0) — event listener

```php
use Contao\BackendUser;
use Markocupic\GalleryCreatorBundle\Event\ImagePostInsertEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MyImagePostInsertListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function __invoke(ImagePostInsertEvent $event): void
    {
        $picturesModel = $event->getPicturesModel();
        $user = $this->security->getUser();

        if ($user instanceof BackendUser && $user->name) {
            $picturesModel->caption = 'Holidays '.date('Y').', Photo: '.$user->name;
            $picturesModel->save();
        }
    }
}
```

The event exposes a single getter:

- `getPicturesModel(): Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel`

As with the old hook, the listener mutates the picture model in place; no return value is
expected.
