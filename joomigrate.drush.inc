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
    $items['delete-authors'] = [
        'description'           => 'It will delete all imported authors from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-authors'],
    ];
    $items['delete-channels'] = [
        'description'           => 'It will delete all imported channels from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-channels'],
    ];
    $items['delete-tags'] = [
        'description'           => 'It will delete all imported tags from database',
        'drupal dependencies'   => ['joomigrate'],
        'aliases'               => ['joomigrate:delete-tags'],
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
        ->exists('field_joomigrate_id')
        ->condition('field_joomigrate_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('node')
            ->condition('type', 'article')
            ->exists('field_joomigrate_id')
            ->condition('field_joomigrate_id', 0, '>')
            ->range(0, 10)
            ->execute();

        if(count($ids) > 0){
            entity_delete_multiple('node', $ids);
        }
    }

    drush_print('All articles with `field_joomigrate_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_channels()
{
    $ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'channel')
        ->exists('field_joomigrate_id')
        ->condition('field_joomigrate_id', 0, '>')
        ->execute();

    if(count($ids) > 0){
        entity_delete_multiple('taxonomy_term', $ids);
    }
    drush_print('All channels with `field_joomigrate_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_tags()
{
    $ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'tags')
        ->exists('field_tag_joomigrate_id')
        ->condition('field_tag_joomigrate_id', 0, '>')
        ->execute();

    if(count($ids) > 0){
        entity_delete_multiple('taxonomy_term', $ids);
    }
    drush_print('All tags with `field_tag_joomigrate_id` was deleted!');
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
        ->exists('field_joomigrate_id')
        ->condition('field_joomigrate_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('media')
            ->condition('bundle', 'image')
            ->exists('field_joomigrate_id')
            ->condition('field_joomigrate_id', 0, '>')
            ->range(0, 10)
            ->execute();

        if(count($ids) > 0){
            entity_delete_multiple('media', $ids);
        }
    }

    drush_print('All images with `field_joomigrate_id` was deleted!');
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
        ->exists('field_gallery_joomigrate_id')
        ->condition('field_gallery_joomigrate_id', 0, '>')
        ->count()
        ->execute();

    for($i=0; $i < $siz / 10; $i++)
    {
        $ids = \Drupal::entityQuery('media')
            ->condition('bundle', 'gallery')
            ->exists('field_gallery_joomigrate_id')
            ->condition('field_gallery_joomigrate_id', 0, '>')
            ->range(0, 10)
            ->execute();

        if(count($ids) > 0){
            entity_delete_multiple('media', $ids);
        }
    }

    drush_print('All galleries with `field_joomigrate_id` was deleted!');
}


/**
 * Call back function drush_custom_drush_command_say_hello()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_joomigrate_post_delete_authors()
{
    $ids = \Drupal::entityQuery('user')
        ->exists('field_joomigrate_id')
        ->condition('field_joomigrate_id', 0, '>')
        ->condition('field_first_name', '')
        ->condition('field_last_name', '')
        ->execute();

    drush_print(var_export($ids));

    if(count($ids) > 0){
        entity_delete_multiple('media', $ids);
    }
    drush_print('All authors with `field_joomigrate_id` was deleted!');
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
    drush_joomigrate_post_delete_tags();
    drush_joomigrate_post_delete_images();
    drush_joomigrate_post_delete_galleries();
    drush_joomigrate_post_delete_authors();
}
