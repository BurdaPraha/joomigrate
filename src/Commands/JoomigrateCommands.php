<?php

namespace Drupal\joomigrate\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
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
    $entity = 'node';
    $f = Article::$sync_field;
    $d = \Drupal::entityQuery($entity)->condition('type', 'article')->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }


  /**
   * Delete all imported channels from database
   *
   * @command joomigrate:delete:channels
   */
  public function channels() {
    $entity = 'taxonomy_term';
    $f = Channel::$sync_field;
    $d = \Drupal::entityQuery($entity)->condition('vid', 'channel')->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }


  /**
   * Delete all imported tags from database
   *
   * @command joomigrate:delete:tags
   */
  public function tags() {
    $entity = 'taxonomy_term';
    $f = Tag::$sync_field;
    $d = \Drupal::entityQuery($entity)->condition('vid', 'tags')->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }

  /**
   * Delete all imported images from database, files not
   *
   * @command joomigrate:delete:images
   */
  public function images() {
    $entity = 'media';
    $f = Image::$sync_field;
    $d = \Drupal::entityQuery($entity)->condition('bundle', 'image')->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }

  /**
   * Delete all imported galleries from database, files not
   *
   * @command joomigrate:delete:galleries
   */
  public function galleries() {
    $entity = 'media';
    $f = Gallery::$sync_field;
    $d = \Drupal::entityQuery($entity)->condition('bundle', 'gallery')->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }

  /**
   * Delete all imported authors from database
   *
   * @command joomigrate:delete:authors
   */
  public function authors() {
    $entity = 'user';
    $f = Author::$sync_field;
    $d = \Drupal::entityQuery($entity)->exists($f)->condition($f, 0, '>')->execute();
    $this->multipleDelete($d, $entity, $f);
  }


  /**
   * @param array $data
   * @param $entity_type
   * @param $sync_field
   */
  private function multipleDelete(array $data, $entity_type, $sync_field) {
    $c = count($data);
    $p = [];

    if(null == $c || $c == 0) {
      $this->io()->warning("There is not entity with `{$sync_field}` field now");
      return;
    }

    $this->io()->confirm("Delete {$c} items?");
    $this->io()->progressStart($c);

    foreach ($data as $key => $id) {

      /**
       * @todo: because \Drupal::entityTypeManager()->getStorage($entity_type)->load($id); return RedirectResponse on few articles
       */
      switch ($entity_type)
      {
        case 'media':
          $p['mid'] = $id;
          break;

        case 'user':
          $p['uid'] = $id;
          break;

        default:
          $p['nid'] = $id;
          break;
      }

      try {
        \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties($p);
        $this->io()->progressAdvance();

      } catch(InvalidPluginDefinitionException $e) {
        $this->io()->error("Entity `{$entity_type}` not exist!");
      }

    }

    $this->io()->progressFinish();
    $this->io()->success("All items with `{$sync_field}` are deleted now");
  }

}
