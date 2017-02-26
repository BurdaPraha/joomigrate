<?php

/**
 * @file Contains the code to generate the custom drush commands.
 */
/**
 * Implements hook_drush_command().
 */
function joomigrate_drush_command()
{
    $items = [];
    $items['delete-articles'] = [
        'description'           => 'It will delete all articles from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-all-articles'],
    ];
    return $items;
}

/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_articles()
{
    $delete = \Drupal::entityQuery('node')
        ->condition('type', 'article')
        ->execute();

    entity_delete_multiple('node', $delete);
    drush_print('All articles was deleted!');
}