<?php

namespace Drupal\drupal_task\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;

/**
 * Provides a REST resource to get node content along with its referenced paragraphs.
 *
 * @RestResource(
 *   id = "custom_node_resource",
 *   label = @Translation("Custom Node Resource"),
 *   uri_paths = {
 *     "canonical" = "/custom_endpoint/node/{node}"
 *   }
 * )
 */
class CustomNodeResource extends ResourceBase {

  /**
   * A resource entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CustomNodeResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('custom_node_service'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $node
   *   The node ID.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get(Request $request, $node) {
    // Load the node
    $node = $this->entityTypeManager->getStorage('node')->load($node);
    if (!$node || $node->isPublished() === FALSE) {
      throw new NotFoundHttpException($this->t('Node with ID @node was not found.', ['@node' => $node]));
    }

    // Load referenced paragraphs
    // $paragraphs = [];
    // if ($node->hasField('field_card')) {
    //   foreach ($node->get('field_card')->referencedEntities() as $paragraph) {
    //     $paragraphs[] = $paragraph->toArray();
    //   }
    // }

    $paragraphs = [];
    if ($node->hasField('field_card')) {
      foreach ($node->get('field_card')->referencedEntities() as $paragraph) {
        $paragraph_data = $paragraph->toArray();

        // Check if the paragraph has field_card_image
        if ($paragraph->hasField('field_card_image')) {
          foreach ($paragraph->get('field_card_image')->referencedEntities() as $image) {
            $file_url = '';
            if ($image instanceof \Drupal\file\FileInterface) {
              $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image->getFileUri());
            }
            $paragraph_data['image_url'] = $file_url;
          }
        }

        $paragraphs[] = $paragraph_data;
      }
    }

    // Prepare data to be returned as JSON
    $data = [
      'node' => $node->toArray(),
      'paragraphs' => $paragraphs,
    ];

    // Return as JSON response
    return new ModifiedResourceResponse($data);
  }

}
