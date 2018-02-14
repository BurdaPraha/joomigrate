<?php

namespace Drupal\joomigrate\Factory;

use Drupal\taxonomy\Entity\Term;

class TermFactory
{
    /**
     * Create channel for article or use existing by name
     *
     * @param $name
     * @param int $joomla_id
     * @return int|null|string
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function channel($name, $joomla_id = 1)
    {
        $channelExisting = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $name, 'vid' => 'channel']);

        if($channelExisting)
        {
            // use existing
            return end($channelExisting)->id();
        }

        // not exist
        $term = Term::create([
            'vid'             => 'channel',
            'name'            => $name,
            'field_joomla_id' => $joomla_id
        ]);
        $term->save();

        return $term->id();
    }

    /**
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
}