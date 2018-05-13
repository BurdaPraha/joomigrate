<?php

namespace Drupal\joomigrate\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\Entity;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;

use Drupal\joomigrate\Entity\Tag;
use Drupal\joomigrate\Entity\Article;
use Drupal\joomigrate\Entity\Author;
use Drupal\joomigrate\Entity\Channel;
use Drupal\joomigrate\Entity\Gallery;
use Drupal\joomigrate\Entity\Image;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class JoomigrateCommands extends DrushCommands {

  /**
   * It will delete all imported stuffs
   *
   * @command joomigrate:delete:all
   */
  public function all() {
    $this->articles();
    $this->authors();
    $this->channels();
    $this->tags();
    $this->images();
    $this->galleries();
  }

  /**
   * Delete all imported articles from database
   *
   * @command joomigrate:delete:articles
   */
  public function articles() {
    $this->multipleDelete('node', Article::$sync_field, ['type', 'article']);
  }


  /**
   * Delete all imported channels from database
   *
   * @command joomigrate:delete:channels
   */
  public function channels() {
    $this->multipleDelete('taxonomy_term', Channel::$sync_field, ['vid', 'channel']);
  }


  /**
   * Delete all imported tags from database
   *
   * @command joomigrate:delete:tags
   */
  public function tags() {
    $this->multipleDelete('taxonomy_term', Tag::$sync_field, ['vid', 'tags']);
  }

  /**
   * Delete all imported images from database, files not
   *
   * @command joomigrate:delete:images
   */
  public function images() {
    $this->multipleDelete('media', Image::$sync_field, ['bundle', 'image']);
  }

  /**
   * Delete all imported galleries from database, files not
   *
   * @command joomigrate:delete:galleries
   */
  public function galleries() {
    $this->multipleDelete('media', Gallery::$sync_field, 'bundle', 'gallery');
  }

  /**
   * Delete all imported authors from database
   *
   * @command joomigrate:delete:authors
   */
  public function authors() {
    $this->multipleDelete('user', Author::$sync_field, null);
  }


  /**
   * @param $entity_type
   * @param $sync_field
   * @param null $condition
   * @throws InvalidPluginDefinitionException
   */
  private function multipleDelete($entity_type, $sync_field, $condition = null)
  {
    $db = \Drupal::entityQuery($entity_type);
    if($condition) $db->condition($condition[0], $condition[1]);
    if($sync_field) $db->exists($sync_field)->condition($sync_field, 0, '>');

    $data = $db->execute();
    $sum = count($data);
    $deleted = 0;

    if(null == $sum || $sum == 0) {
      $this->io()->warning("There is not entity with `{$sync_field}` field now");
      return;
    }

    $this->io()->confirm("Delete {$sum} items?");
    $this->io()->progressStart($sum);

    foreach ($data as $key => $id)
    {
      //$eid = 'media' == $entity_type ? 'mid' : 'user' == $entity_type ? 'uid' : 'nid';
      /** @var Entity $entity */
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type); //->loadByProperties([$eid => $id])
      $item = $entity->load($id);
      if($item->delete()){
        ++$deleted;
        $this->io()->progressAdvance();
        $this->io()->comment("Deleting {$id}");
      }
    }

    if($sum !== $deleted){
      $this->io()->newLine(2);
      $this->io()->error("Deleted only {$deleted} from {$c}");
      return;
    }

    $this->io()->progressFinish();
    $this->io()->success("All items with `{$sync_field}` are deleted now");
    unset($data);
  }

}
