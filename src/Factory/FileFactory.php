<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;

use Drupal\joomigrate\Factory\Helper;
use Drupal\file\Entity\File;

class FileFactory
{

  public static $migrate_data_folder = "/sites/default/files/joomigrate/";

  /**
   * Create new drupal file entity
   * @param $path
   * @param $file_name
   * @param null $entity_id
   * @return \Drupal\file\FileInterface
   */
    public static function make($path, $file_name, $entity_id = null): \Drupal\file\FileInterface
    {
      $entity_id    = null == $entity_id || 1 == $entity_id ? null : $entity_id;
      $prefix_id    = $entity_id ? 'entity_id: ' . $entity_id . ' - ' : '';
      $normal_name  = strlen($file_name) >= 50 ? md5($file_name) . '.' . pathinfo($file_name, PATHINFO_EXTENSION) : $file_name;
      $new_file     = 'public://'.date("Y-m").'/' . $normal_name;

      // change path to absolute
      if(!Helper::is_absolute($path)) {
        $path = \Drupal::root() . self::$migrate_data_folder . $path;
      }

      // try existing same png file
      $path_png = str_replace(".jpg", ".png", $path);
      if(file_exists($path_png)) {
        $path = $path_png;
      }

      if(!file_exists($path)) {
        drupal_set_message(t("{$prefix_id} <strong>File not exist!</strong><br><code>{$path}</code>"), "warning");
      }

      $saved_file = file_save_data(file_get_contents($path), $new_file, FILE_EXISTS_REPLACE);
      if(!$saved_file) {
        drupal_set_message(t("{$prefix_id} <strong>Problem with file_save_data!</strong><br><pre><kbd>from:</kbd> {$path}<br><kbd>to:</kbd> {$new_file}</pre>"), "warning");
      }

      return $saved_file;
    }
}
