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
        'description'           => 'It will delete all imported articles from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-articles'],
    ];
    $items['delete-channels'] = [
        'description'           => 'It will delete all imported channels from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-channesl'],
    ];
    $items['delete-images'] = [
        'description'           => 'It will delete all imported images from database, files not',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-images'],
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
    $ids = \Drupal::entityQuery('node')
        ->condition('type', 'article')
        ->condition('field_joomla_id', 0, '>')
        ->execute();

    entity_delete_multiple('node', $ids);
    drush_print('All articles was deleted!');
}

/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_channels()
{
    $ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('field_joomla_id', 0, '>')
        ->exists('field_joomla_id')
        ->execute();

    entity_delete_multiple('taxonomy_term', $ids);
    drush_print('All channels with field_joomla_id was deleted!');
}

/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_images()
{
    $ids = \Drupal::entityQuery('media')
        ->condition('bundle', 'image')
        ->exists('field_joomla_id')
        ->condition('field_joomla_id', 0, '>')
        ->execute();

    entity_delete_multiple('media', $ids);
    drush_print('All pictures with field_joomla_id was deleted!');
}
