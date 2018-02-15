<?php

namespace Drupal\joomigrate\Factory;

use Drupal\taxonomy\Entity\Term;

/**
 * @todo: create instance for sync id
 *
 * Class TermFactory
 * @package Drupal\joomigrate\Factory
 */
class TermFactory
{
    /**
     * Create channel for article or use existing by name and return object with "is_new" flag and this entity
     *
     * @param $name
     * @param int $joomla_id
     * @param null $parent_id
     * @return \stdClass
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function channel($name, $joomla_id = 0, $parent_id = null)
    {
        $result = new \stdClass();
        $result->is_new = false;
        $result->entity = null;

        $props = ['name' => $name, 'vid' => 'channel'];

        $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($props);
        if($existing)
        {
            // use existing
            $result->entity = end($existing);
        }
        else
        {
            // not exist
            if($joomla_id > 0){
                $props['field_joomla_id'] = $joomla_id;
            }

            $term = Term::create($props);
            $term->save();

            $result->entity = $term;
            $result->is_new = true;
        }


        return $result;
    }

    /**
     * Create new tag term for using in articles
     *
     * @param $name
     * @param $pair_id
     * @return int|null|string
     */
    public static function tag($name, $pair_id)
    {
        // not exist
        $term = Term::create([
            'vid'                   => 'tags',
            'name'                  => $name,
            'field_tag_joomla_id'   => $pair_id
        ]);
        $term->save();


        return $term->id();
    }

    /**
     * Serialized keywords (can be used old meta keywords tag data) by "," to tags taxonomy terms
     *
     * @param $string
     * @param $node_id
     * @return array
     */
    public static function keywordsToTags($string, $node_id)
    {
        $variables = [];

        // tags
        if(!empty($string) && strlen($string) > 5)
        {
            $tags       = [];
            $keywords   = explode(',', $string);

            foreach($keywords as $k => $tag)
            {
                // tag name
                $name = trim($tag);

                // check existing id
                $tagExist = \Drupal::entityQuery('taxonomy_term')
                    ->condition('vid', 'tags')
                    ->condition('name', $name, 'CONTAINS')
                    ->execute();

                if($tagExist)
                {
                    // use existing
                    $term_id = end($tagExist);
                }
                else
                {
                    // not exist
                    $term_id = TermFactory::tag($name, $node_id);
                }

                // store
                $tags['target_id'] = $term_id;
            }

            // return var
            if(count($tags) > 1)
            {
                $variables['field_tags'] = $tags;
            }
        }


        return $variables;
    }
}