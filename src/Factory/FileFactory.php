<?php

namespace Drupal\joomigrate\Factory;

use Drupal\joomigrate\Factory\Helper;
use Drupal\file\Entity\File;

class FileFactory
{

  public static $migrate_data_folder = "/sites/default/files/joomigrate/";

    /**
     * Create new drupal file entity
     *
     * @param $path - full path of image
     * @param $file_name - original name of image
     * @param null $entity_id int - just for loging
     * @return null
     */
    public static function make($path, $file_name, $entity_id = null)
    {
      $result       = null;
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
      if(file_exists($path)) {
        $path = $path_png;
      }

      if(!file_exists($path)) {
        drupal_set_message("{$prefix_id} File not exist!<br><pre>{$path}</pre>", "warning");
      }

      if($result = file_save_data(file_get_contents($path), $new_file, FILE_EXISTS_REPLACE)){
        drupal_set_message("{$prefix_id} - Problem with file_save_data!<br><pre>to: {$new_file}<br>from: {$new_file}</pre>", "warning");
      }


      return $result;
    }
}
