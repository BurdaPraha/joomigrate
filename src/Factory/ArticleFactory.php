<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;
use Drupal\joomigrate\Entity\Article;
use Drupal\node\Entity\Node;

/**
 * Class ArticleFactory
 * @package Drupal\joomigrate\Factory
 */
class ArticleFactory
{
  public $entity_type = 'node';
  public $bundle      = 'article';

  public function loadArticleBySyncID($id): ?Node
  {
    $nodes = \Drupal::entityTypeManager()->getStorage($this->entity_type)->loadByProperties([
      Article::$sync_field => $id
    ]);
    $node = end($nodes);

    return $node;
  }
}
