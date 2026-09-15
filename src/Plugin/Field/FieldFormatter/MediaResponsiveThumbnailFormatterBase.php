<?php

declare(strict_types=1);

namespace Drupal\bigger_picture\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base implementation of the 'media_responsive_thumbnail' formatter.
 *
 * Renders the thumbnail of a referenced media item as a responsive image.
 *
 * This class was absorbed from the Media Responsive Thumbnail module
 * (https://www.drupal.org/project/media_responsive_thumbnail), which this
 * module no longer depends on. It carries no plugin attribute on purpose so
 * that it is never discovered on its own.
 */
abstract class MediaResponsiveThumbnailFormatterBase extends ResponsiveImageFormatter {

  /**
   * Constructs a MediaResponsiveThumbnailFormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityStorageInterface $responsive_image_style_storage
   *   The responsive image style storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityStorageInterface $responsive_image_style_storage,
    EntityStorageInterface $image_style_storage,
    LinkGeneratorInterface $link_generator,
    AccountInterface $current_user,
    FileUrlGeneratorInterface $file_url_generator,
    protected RendererInterface $renderer,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $responsive_image_style_storage,
      $image_style_storage,
      $link_generator,
      $current_user,
      $file_url_generator,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('link_generator'),
      $container->get('current_user'),
      $container->get('file_url_generator'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $link_types = [
      'content' => $this->t('Linked to content'),
      'media' => $this->t('Linked to media item'),
    ];
    // Display this setting only if image is linked.
    $image_link_setting = $this->getSetting('image_link');
    if (is_string($image_link_setting) && array_key_exists($image_link_setting, $link_types)) {
      $summary[] = $link_types[$image_link_setting];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $files = $this->getEntitiesToView($items, $langcode);
    $attributes = [];
    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    // Collect cache tags to be added for each item in the field.
    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    $image_styles_to_load = [];
    $cache_tags = [];

    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this->imageStyleStorage->loadMultiple($image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }

    $image_loading_settings = $this->getSetting('image_loading');
    if (is_array($image_loading_settings) && array_key_exists('attribute', $image_loading_settings)) {
      $attributes['loading'] = $image_loading_settings['attribute'];
    }

    /** @var \Drupal\media\MediaInterface[] $files */
    foreach ($files as $delta => $file) {
      $elements[$delta] = [
        '#theme' => 'responsive_image_formatter',
        '#item' => $file->get('thumbnail')->first(),
        '#attributes' => $attributes,
        '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : '',
        '#url' => $this->getMediaThumbnailUrl($file, $items->getEntity(), $langcode),
        '#cache' => ['tags' => $cache_tags],
      ];

      // Add cacheability of each item in the field.
      $this->renderer->addCacheableDependency($elements[$delta], $file);
    }

    // Add cacheability of the image style setting.
    if ($this->getSetting('image_link') && ($image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style')))) {
      $this->renderer->addCacheableDependency($elements, $image_style);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that reference
    // media items.
    return ($field_definition->getFieldStorageDefinition()
      ->getSetting('target_type') == 'media');
  }

  /**
   * Get the URL for the media thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that the field belongs to.
   * @param string $langcode
   *   The language that should be used to render the field. Defaults
   *   to the current content language.
   *
   * @return \Drupal\Core\Url|null
   *   The URL object for the media item or null if we don't want to add
   *   a link.
   */
  protected function getMediaThumbnailUrl(MediaInterface $media, EntityInterface $entity, $langcode) {
    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      if ($langcode && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($image_link_setting === 'media') {
      $url = $media->toUrl();
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view', NULL, TRUE)
      ->andIf(parent::checkAccess($entity));
  }

}
