<?php

declare(strict_types=1);

namespace Drupal\bigger_picture\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\responsive_image\Hook\ResponsiveImageThemeHooks;

/**
 * Theme hooks for bigger_picture.
 */
final class BiggerPictureThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      // Extend the core theme hook with a separate attributes bag for the link
      // wrapping the image. Core owns 'attributes' and puts it on the <img>
      // since Drupal 11.4, so the lightbox data attributes need their own
      // variable to stay on the <a>.
      'responsive_image_formatter' => [
        'variables' => [
          'item' => NULL,
          'url' => NULL,
          'responsive_image_style_id' => NULL,
          'attributes' => [],
          'link_attributes' => [],
        ],
        // Re-declaring a theme hook from a module drops the initial preprocess
        // callback, and core then falls back to the deprecated
        // template_preprocess_responsive_image_formatter() shim on top of it,
        // preprocessing everything twice. Keep pointing at core's callback.
        // @see \Drupal\Core\Theme\Registry::processExtension()
        'initial preprocess' => ResponsiveImageThemeHooks::class . ':preprocessResponsiveImageFormatter',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for responsive_image_formatter.
   *
   * Core turns only 'attributes', 'title_attributes' and 'content_attributes'
   * into Attribute objects, so a custom bag arrives as a plain array and calls
   * such as link_attributes.setAttribute() silently resolve to NULL in Twig.
   *
   * @see \Drupal\Core\Theme\ThemeManager::render()
   */
  #[Hook('preprocess_responsive_image_formatter')]
  public function preprocessResponsiveImageFormatter(array &$variables): void {
    $link_attributes = $variables['link_attributes'] ?? [];

    if ($link_attributes instanceof Attribute) {
      return;
    }

    $variables['link_attributes'] = new Attribute(is_array($link_attributes) ? $link_attributes : []);
  }

}
