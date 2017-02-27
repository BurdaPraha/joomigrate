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
    $items['delete-all'] = [
        'description'           => 'It will delete all imported stuffs',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-all'],
    ];
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
    $items['delete-galleries'] = [
        'description'           => 'It will delete all imported galleries from database, files not',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-galleries'],
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
    $siz = \Drupal::entityQuery('node')
        ->condition('type', 'article')
        ->exists('field_joomla_id')
        ->condition('field_joomla_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('node')
            ->condition('type', 'article')
            ->exists('field_joomla_id')
            ->condition('field_joomla_id', 0, '>')
            ->range(0, 10)
            ->execute();

        entity_delete_multiple('node', $ids);
    }

    drush_print('All articles with `field_joomla_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_channels()
{
    $ids = \Drupal::entityQuery('taxonomy_term')
        ->exists('field_joomla_id')
        ->condition('field_joomla_id', 0, '>')
        ->execute();

    entity_delete_multiple('taxonomy_term', $ids);
    drush_print('All channels with `field_joomla_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_images()
{
    $siz = \Drupal::entityQuery('media')
        ->condition('bundle', 'image')
        ->exists('field_joomla_id')
        ->condition('field_joomla_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('media')
            ->condition('bundle', 'image')
            ->exists('field_joomla_id')
            ->condition('field_joomla_id', 0, '>')
            ->range(0, 10)
            ->execute();

        entity_delete_multiple('media', $ids);
    }

    drush_print('All pictures with `field_joomla_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_galleries()
{
    $siz = \Drupal::entityQuery('media')
        ->condition('bundle', 'gallery')
        ->exists('field_gallery_joomla_id')
        ->condition('field_gallery_joomla_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('media')
            ->condition('bundle', 'gallery')
            ->exists('field_gallery_joomla_id')
            ->condition('field_gallery_joomla_id', 0, '>')
            ->range(0, 10)
            ->execute();

        entity_delete_multiple('media', $ids);
    }

    entity_delete_multiple('media', $ids);
    drush_print('All galleries with `field_joomla_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_users()
{
    $ids = \Drupal::entityQuery('user')
        ->exists('field_joomla_id')
        ->condition('field_joomla_id', 0, '>')
        ->execute();

    entity_delete_multiple('media', $ids);
    drush_print('All users with `field_joomla_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_all()
{
    drush_joomigrate_post_delete_articles();
    drush_joomigrate_post_delete_channels();
    drush_joomigrate_post_delete_images();
    drush_joomigrate_post_delete_galleries();
    drush_joomigrate_post_delete_users();
}