<?php

namespace Drupal\joomigrate\Form;

use Drupal\taxonomy\Entity\Term;

use Drupal\joomigrate\Factory\Helper;
use Drupal\joomigrate\Factory\TermFactory;

/**
 * Import categories from vanilla Joomla 3.5.1
 * After this form you can submit form J3Articles
 *
 * Class J3Categories
 * @package Drupal\joomigrate\Form
 */
class J3Categories extends ExampleForm
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'joomigrate_joomla3_form';
    }

    /**
     * Strict header columns - copied structure of "#__content" table
     * @return array
     */
    public function getCsvHeaders()
    {
        return [
            'id',
            'asset_id',
            'parent_id',
            'lft',
            'rgt',
            'level',
            'path',
            'extension',
            'title',
            'alias',
            'note',
            'description',
            'published',
            'checked_out',
            'checked_out_time',
            'access',
            'params',
            'metadesc',
            'metakey',
            'metadata',
            'created_user_id',
            'created_time',
            'modified_user_id',
            'modified_time',
            'hits',
            'language',
            'version',
        ];
    }


    /**
     * @param $data
     * @param $context
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function processBatch($data, &$context)
    {
        if('com_content' == $data['extension'])
        {
            $channel = TermFactory::channel($data['title']);
            if(true === $channel->is_new)
            {
                // @todo: check if parent exist before...
                //$channel->entity->set('parent', $data['parent_id']);

                $channel->entity->set('path', Helper::channelAlias($data['path'], $channel->entity->id(), 'cs'));
                $channel->entity->set('status', $data['published']);
                $channel->entity->save();
            }
        }
        else
        {
            drupal_set_message($data['title'] . ' - skipped because is not com_content extension');
        }

        // validate process errors
        if (!isset($context['results']['errors']))
        {
            $context['results']['errors'] = [];
        }
        else
        {
            // you can decide to create errors here comments codes below
            $message = t('Data with @id was not synchronized', ['@id' => $data['ID']]);
            $context['results']['errors'][] = $message;
        }
    }
}