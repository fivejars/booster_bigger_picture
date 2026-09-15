# Booster Bigger Picture

The module provides integration with the Bigger Picture library as a solution
for Lightbox functionality.

It also provides the **Responsive thumbnail** field formatter for media
reference fields, which renders a referenced media item's thumbnail as a
responsive image. That formatter was absorbed from the
[Media Responsive Thumbnail](https://www.drupal.org/project/media_responsive_thumbnail)
module, which is no longer a dependency. The plugin ID is unchanged
(`media_responsive_thumbnail`), so existing displays keep working.

Formatters:
- Responsive thumbnail (media reference fields)
- @todo ~~Image formatter~~
- @todo ~~Responsive image formatter~~

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Attributes
- Migration path
- TODO list
- Maintainers

## Requirements

- Drupal core `^11.4`
- `media` and `responsive_image` (core modules)

Drupal 11.4 is the minimum because the formatter passes image attributes through
the `#attributes` property and the file URL generator through the parent
constructor, neither of which exists in earlier core.

## Recommended modules
- [Media Thumbnails Video](https://www.drupal.org/project/media_thumbnails_video) - Recommended for correct work with local video media type.

## Installation

- Install as you would normally install a contributed Drupal distribution.
For further information, see [Installing Drupal](https://www.drupal.org/docs/getting-started/installing-drupal).
- Install Bigger picture library to 'libraries' directory (using composer.libraries.json or manual)

## Configuration

Go to the target entity type display settings and setup formatter configuration.

## Attributes

The `responsive_image_formatter` theme hook carries two separate attribute bags:

- `attributes` — belongs to the `<img>` element. Core owns it and merges it into
  the image since Drupal 11.4 (see
  [change record 3554585](https://www.drupal.org/node/3554585)). Use it for
  classes, `loading`, `data-*` meant for the image itself.
- `link_attributes` — added by this module, belongs to the `<a>` wrapping the
  image. All lightbox `data-*` attributes (`data-lightbox-group`, `data-type`,
  `data-img`, `data-sources`, …) live here. The module casts it to an
  `Attribute` object in preprocess, because core only does that automatically
  for `attributes`, `title_attributes` and `content_attributes`.

## Migration path

Upgrading from a release that still depended on Media Responsive Thumbnail:

1. Update the code and run the database updates:

   ```bash
   drush updb
   drush cex
   ```

   `bigger_picture_update_11001()` re-saves every entity view display that used
   the formatter, so its dependency moves from `media_responsive_thumbnail` to
   `bigger_picture`, and then uninstalls the old module. If any display still
   depends on the old module after the re-save, the update aborts instead of
   letting the config manager delete it.

2. The formatter ID is unchanged, so no display needs reconfiguring and no
   content changes.

3. `drupal/media_responsive_thumbnail` is no longer required and drops out on
   the next `composer update`. Remove any site-level patch targeting it —
   in particular the workaround for
   [issue #3350081](https://www.drupal.org/project/media_responsive_thumbnail/issues/3350081),
   which is obsolete: the formatter now always renders the media thumbnail.

4. **Breaking change for themers.** Two render array properties changed:

   | Before | After |
   | --- | --- |
   | `#item_attributes` (image attributes) | `#attributes` |
   | `#attributes` (lightbox link attributes) | `#link_attributes` |

   Update any template that merges attributes into an image render array:

   ```twig
   {# Before #}
   {{ content.field_image.0|merge({'#item_attributes': {'class': ['w-full'], 'loading': 'lazy'}}) }}

   {# After #}
   {{ content.field_image.0|merge({'#attributes': {'class': ['w-full'], 'loading': 'lazy'}}) }}
   ```

   And any override of `responsive-image-formatter.html.twig` that renders the
   link must print `link_attributes` instead of `attributes` on the `<a>`.

5. Minimum core version is now 11.4.

## TODO list
- Default configuration for image sizes and styles
- Extend Image formatter
- Extend Responsive image formatter

## Maintainers

Sponsored and developed by [Five Jars](https://www.drupal.org/five-jars).
