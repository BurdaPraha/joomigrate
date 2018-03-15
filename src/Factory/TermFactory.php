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
    public static $sync_field_channel   = 'field_joomigrate_id';
    public static $sync_field_tag       = 'field_joomigrate_tag_id';

    /**
     * @param array $props
     * @return \Drupal\Core\Entity\EntityInterface[]
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function findChannel(array $props)
    {
        $q = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties($props);


        return $q;
    }

    /**
     * Create channel for article or use existing by name and return object
     * @param int $sync_field
     * @param $name
     * @param null $parent_id
     * @return \Drupal\Core\Entity\EntityInterface|mixed|null|static
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function channel($sync_field = 0, $name, $parent_id = null)
    {
        $existing = [];
        $term = null;

        $props = ['name' => $name, 'vid' => 'channel', self::$sync_field_channel => $sync_field];

        // sync ID has top priority (only categories import use-case)
        if($sync_field > 0 && is_int($sync_field))
        {
            // first try search by ID
            $existing = self::findChannel([self::$sync_field_channel => $sync_field]);
        }

        // if we can't find channel by sync id, try it again by name
        if(count($existing) <= 0)
        {
            $existing = self::findChannel(['name' => $name, 'vid' => 'channel']);
        }

        if(count($existing) > 0)
        {
            // use existing
            $term = end($existing);
            drupal_set_message('tid: ' . $term->id() . ', name: ' . $name . ' - Used existing channel');
        }
        else
        {
            $term = Term::create($props);
            $term->save();

            drupal_set_message('tid: ' . $term->id() . ', name: ' . $name . ' - Created new channel', 'success');
        }


        return $term;
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
            self::$sync_field_tag   => $pair_id
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