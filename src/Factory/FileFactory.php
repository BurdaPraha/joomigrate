<?php

namespace Drupal\joomigrate\Factory;

use Drupal\joomigrate\Factory\Helper;
use Drupal\file\Entity\File;

class FileFactory
{
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
        $is_absolute    = Helper::is_absolute($path);
        $entity_id      = null == $entity_id || 1 == $entity_id ? null : $entity_id;
        $prefix_id      = $entity_id ? 'entity_id: ' . $entity_id . ' - ' : '';
        $normal_name    = strlen($file_name) >= 50 ? md5($file_name) . '.' . pathinfo($file_name, PATHINFO_EXTENSION) : $file_name;

        if(!$is_absolute)
        {
            $full_path = \Drupal::root() . "/sites/default/files/joomigrate/{$path}";

            // png quick fix
            $full_path_png = str_replace(".jpg", ".png", $full_path);
            if(file_exists($full_path_png))
            {
                $full_path = $full_path_png;
            }

            if(file_exists($full_path))
            {
                $file_data  = file_get_contents($full_path);
                $file       = file_save_data($file_data, 'public://'.date("Y-m").'/' . $normal_name, FILE_EXISTS_REPLACE);

                if($file)
                {
                    return $file;
                }
                else
                {
                    drupal_set_message($prefix_id . 'Problem with file_save_data, file: "' . $full_path . '"', 'warning');
                }
            }
            else
            {
                drupal_set_message($prefix_id . 'File: "' . $full_path . '" not exist!', 'warning');
            }

        }else
        {
            $file_data  = file_get_contents($path);
            $file       = file_save_data($file_data, 'public://'.date("Y-m").'/' . $normal_name, FILE_EXISTS_REPLACE);

            if($file)
            {
                return $file;
            }
            else
            {
                drupal_set_message($prefix_id . 'Problem with file_save_data, file: "' . $path . '"', 'warning');
            }
        }


        return null;
    }
}