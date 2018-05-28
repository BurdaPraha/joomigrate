<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;
use Drupal\joomigrate\Entity\Article;

/**
 * Class ArticleFactory
 * @package Drupal\joomigrate\Factory
 */
class ArticleFactory
{
    public $entity_type = 'node';
    public $bundle      = 'article';

  /**
     * @param $id
     * @return \Drupal\Core\Entity\EntityInterface|mixed
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function loadArticleBySyncID($id)
    {
        $nodes = \Drupal::entityTypeManager()->getStorage($this->entity_type)->loadByProperties([
          Article::$sync_field => $id
        ]);

        $node = end($nodes);


        return $node;
    }
}
