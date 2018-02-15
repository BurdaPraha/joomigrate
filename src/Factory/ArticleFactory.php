<?php

namespace Drupal\joomigrate\Factory;

/**
 * Class ArticleFactory
 * @package Drupal\joomigrate\Factory
 */
class ArticleFactory extends BaseFactory
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
            $this->sync_field_name => $id
        ]);

        $node = end($nodes);


        return $node;
    }
}