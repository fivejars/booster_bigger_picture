<?php

declare(strict_types=1);

namespace Drupal\bigger_picture\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\File;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\media\Plugin\media\Source\OEmbed;
use Drupal\media\Plugin\media\Source\VideoFile;
use Drupal\media_responsive_thumbnail\Plugin\Field\FieldFormatter\MediaResponsiveThumbnailFormatter as OriginalFormatter;

/**
 * Extended plugin implementation of the 'media_responsive_thumbnail' formatter.
 *
 * @FieldFormatter(
 *   id = "media_responsive_thumbnail",
 *   label = @Translation("Responsive thumbnail"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaResponsiveThumbnailFormatter extends OriginalFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'image_link_style' => NULL,
      'image_link_styles_by_size' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['image_link']['#options']['lightbox'] = $this->t('Lightbox: Bigger picture');

    $options = [];
    foreach ($this->imageStyleStorage->loadMultiple() as $image_style) {
      $options[$image_style->id()] = $image_style->label();
    }

    $elements['image_link_style'] = [
      '#title' => $this->t('Image style for the image link'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $options,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][image_link]"]' => ['value' => 'lightbox'],
        ],
      ],
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Image Styles'), new Url('entity.image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer image styles'),
      ],
    ];

    $sizes = (array) $this->getSetting('image_link_styles_by_size');
    if (empty($sizes)) {
      return $elements;
    }

    $elements['image_link_styles_by_size'] = [
      '#type' => 'details',
      '#title' => $this->t('Image styles by screen size'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][image_link]"]' => ['value' => 'lightbox'],
        ],
      ],
    ];

    foreach ($sizes as $size => $image_link_style_by_size) {
      $elements['image_link_styles_by_size'][$size] = [
        '#title' => $this->t('Image style for @size screen', ['@size' => $size]),
        '#type' => 'select',
        '#default_value' => $image_link_style_by_size,
        '#empty_option' => $this->t('None (original image)'),
        '#options' => $options,
        '#description' => [
          '#markup' => $this->linkGenerator->generate($this->t('Configure Image Styles'), new Url('entity.image_style.collection')),
          '#access' => $this->currentUser->hasPermission('administer image styles'),
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('image_link') !== 'lightbox') {
      return $summary;
    }

    $summary[] = $this->t('Linked using Lightbox');

    if ($image_link_style = $this->getSetting('image_link_style')) {
      $summary[] = $this->t('Image style for the link: @name', ['@name' => $image_link_style]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $link_type = $this->getSetting('image_link');

    if ($link_type !== 'lightbox') {
      return $elements;
    }

    $owner = $items->getEntity();

    foreach ($elements as &$element) {
      $item = $element['#item'] ?? NULL;

      if (!($item instanceof FileItem)) {
        continue;
      }

      $media = $item->getEntity();
      if (!($media instanceof MediaInterface)) {
        continue;
      }

      $element['#attached']['library'][] = 'bigger_picture/init';
      $element['#attributes']['data-lightbox-group'] = $owner->id();

      $source = $media->getSource();
      if ($source instanceof OEmbed) {
        /** @var string $value */
        $value = $source->getSourceFieldValue($media);
        $element['#url'] = $value;
        $element['#attributes']['data-type'] = 'iframe';
        $element['#attributes']['data-iframe'] = Url::fromRoute('media.oembed_iframe', [], [
          'absolute' => TRUE,
          'query' => [
            'url' => $value,
            'max_width' => 0,
            'max_height' => 0,
            'hash' => \Drupal::service('media.oembed.iframe_url_helper')->getHash($value, 0, 0),
          ],
        ])->toString();
        continue;
      }

      if (!($source instanceof File)) {
        continue;
      }

      /** @var \Drupal\media\MediaTypeInterface $media_type */
      $media_type = $media->get('bundle')->entity;
      $source_field_name = $source->getSourceFieldDefinition($media_type)?->getName();

      $file = $media->get($source_field_name)->entity;

      if (!($file instanceof FileInterface)) {
        continue;
      }

      if ($source instanceof Image) {
        /** @var string $file_uri */
        $file_uri = $file->getFileUri();

        $image_link_style = $this->getSetting('image_link_style');

        if ($image_link_style) {
          $image_link_style = $this->imageStyleStorage->load($image_link_style);
          $this->renderer->addCacheableDependency($elements, $image_link_style);
        }

        /** @var ?\Drupal\image\ImageStyleInterface $image_link_style */
        $element['#url'] = $image_link_style ? $image_link_style->buildUrl($file_uri) : $file->getFileUri();

        $value = (array) $media->get($source_field_name)->getValue();
        $value = reset($value);
        /** @var array{target_id: string, alt?: string, title?: string, width?: string, height?: string} $value */
        $element['#attributes']['data-width'] = $value['width'] ?? '';
        $element['#attributes']['data-height'] = $value['height'] ?? '';
        $element['#attributes']['data-alt'] = $value['alt'] ?? '';
        $element['#attributes']['data-caption'] = $value['title'] ?? '';

        /** @var ?array<string, string> $image_link_styles_by_size */
        if ($image_link_styles_by_size = $this->getSetting('image_link_styles_by_size')) {
          $sizes = [];
          foreach ($image_link_styles_by_size as $size => $image_link_style_by_size) {
            $image_link_style_by_size = $this->imageStyleStorage->load($image_link_style_by_size);
            /** @var \Drupal\image\ImageStyleInterface $image_link_style_by_size */
            $sizes[] = $image_link_style_by_size->buildUrl($file_uri) . ' ' . $size;
          }

          $element['#attributes']['data-type'] = 'image';
          $element['#attributes']['data-img'] = implode(', ', $sizes);
        }
        continue;
      }

      $url = $file->createFileUrl();
      $element['#url'] = $url;
      $element['#attributes']['data-type'] = $source instanceof VideoFile ? 'video' : 'audio';
      $element['#attributes']['data-sources'] = json_encode([
        [
          'src' => $url,
          'type' => $file->getMimeType(),
        ],
      ]);
    }

    return $elements;
  }

}
