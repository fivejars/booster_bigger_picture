# Booster Bigger Picture

The module provides integration with Bigger Picture library as solution
for Lightbox functionality.
Module extend formatters:
- Media responsive thumbnail formatter
- @todo ~~Image formatter~~
- @todo ~~Responsive image formatter~~

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- TODO list
- Maintainers

## Requirements
- [Media Responsive Thumbnail](https://www.drupal.org/project/media_responsive_thumbnail) -
For now module requires this module and works only with Responsive image and media modules.
@todo We need separate solutions using submodules in the future.

## Recommended modules
- [Media Thumbnails Video](https://www.drupal.org/project/media_thumbnails_video) - Recommended for correct work with local video media type.

## Installation

- Install as you would normally install a contributed Drupal distribution.
For further information, see [Installing Drupal](https://www.drupal.org/docs/getting-started/installing-drupal).
- Install Bigger picture library to 'libraries' directory (using composer.libraries.json or manual)

## Configuration

Go to the target entity type display settings and setup formatter configuration.

## TODO list
- Default configuration for image sizes and styles
- Extend Image formatter
- Extend Responsive image formatter

## Maintainers

Sponsored and developed by [Five Jars](https://www.drupal.org/five-jars).
