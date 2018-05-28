<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;

use Drupal\media_entity\Entity\Media;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\Paragraph;

class MediaFactory
{
  /**
   * Gallery array with objects
   * @param $name
   * @param $pseudoJson
   * @param $alias
   * @param $article_id
   * @param int $user_id
   * @return array|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public static function gallery($name, $pseudoJson, $alias, $article_id, $user_id = 1): ?array
    {
        /*** Check existing gallery ****/
        $galleryExisting = \Drupal::entityQuery('media')
            ->condition('bundle', 'gallery')
            ->condition(\Drupal\joomigrate\Entity\Gallery::$sync_field, $article_id)
            ->condition('name', $name)
            ->execute();

        if(end($galleryExisting))
        {
            $gallery_paragraph = \Drupal::entityTypeManager()
                ->getStorage('paragraph')
                ->loadByProperties(['field_media.target_id' => end($galleryExisting)]);

            $p = end($gallery_paragraph);
            if($p)
            {
                return ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
            }
        }

        /*** create new gallery ****/
        // fix json from CSV import
        $string   = str_replace("'", '"', $pseudoJson);
        $gallery  = json_decode($string);
        $images   = [];


        if(count($gallery) > 0)
        {
            foreach($gallery as $key => $image)
            {
                // if image don't have any id, create pseudo sync number
                $img_sync_id = isset($image->dirId) ? $image->dirId : ($article_id * 33 + $key);

                // create
                $media = self::image($image->filename, (!empty($image->description) ? $image->description : $image->title), '', $user_id, $img_sync_id);

                if($media)
                {
                    $images[] = [ // $image->ordering
                        'target_id' => $media->id()
                    ];
                }

            }

            // create gallery
            $gallery_media = Media::create([
              'bundle'              => 'gallery',
              'uid'                 => $user_id,
              'status'              => Media::PUBLISHED,
              'name'                => $name,
              'field_media_images'  => $images,
              'path' => [
                  'pathauto'  => 0,
                  'alias'     => '/gallery/' . $alias
              ],
              \Drupal\joomigrate\Entity\Gallery::$sync_field => $article_id,
            ]);


            $gallery_media->setQueuedThumbnailDownload();
            $gallery_media->save();

            // create gallery paragraph
            $gallery_paragraph = Paragraph::create([
                'type'        => 'gallery',
                'uid'         => $user_id,
                'field_media' => [
                    'target_id' => $gallery_media->id()
                ]
            ]);
            $gallery_paragraph->isNew();
            $gallery_paragraph->save();

            // @todo: gallery path auto alias
            return ['target_id' => $gallery_paragraph->id(), 'target_revision_id' => $gallery_paragraph->getRevisionId()];
        }


        return null;
    }


  /**
   * Create file from existing source and media picture or use existing by name
   * @param $path
   * @param string $description
   * @param string $credits
   * @param int $user
   * @param int $import_id
   * @return Media
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public static function image($path, $description = "", $credits = "", $user = 1, $import_id = 1): Media
    {
        $image_name     = explode("/", $path);
        $image_name     = end($image_name);
        $description    = !empty($description) && null !== $description ? $description : '';


        // check if not already exist
        $file_exist = \Drupal::entityQuery('file')
            ->condition('filename', $image_name, 'LIKE')
            ->execute();

        if(end($file_exist))
        {
            $media_exist = \Drupal::entityQuery('media')
                ->condition('field_image.target_id', end($file_exist))
                ->condition(\Drupal\joomigrate\Entity\Image::$sync_field, $import_id, '=')
                ->execute();

            if(end($media_exist))
            {
                $media_id = end($media_exist);
                return Media::load($media_id);
            }
        }

        // create new
        $file = FileFactory::make($path, $image_name, $import_id);
        if($file)
        {
          $params = [
            'bundle'                => 'image',
            'uid'                   => $user,
            'status'                => Media::PUBLISHED,
            'field_description'     => $description,
            'field_source'          => $credits,
            'field_image'           => [
              'target_id' => $file->id(),
              //'alt'       => t('@alt', ['@alt' => substr($description, 0, 155)]),
            ],
            \Drupal\joomigrate\Entity\Image::$sync_field => substr((string) $import_id, 0, 7),
          ];

          $image_media = Media::create($params);
          $image_media->setQueuedThumbnailDownload();
          $image_media->save();
          return $image_media;
        }


        return null;
    }


  /**
   * @param $data
   * @param $user_id
   * @param $article_id
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public static function replaceInlineMedia($data, $user_id, $article_id): ?array
    {
      $paragraphs = [];

      foreach (Helper::imagesFromString($data) as $img)
      {
        $media = self::image($img['src'], $img['alt'], "", $user_id, $article_id);
        $p = Paragraph::create([
          'id'          => NULL,
          'type'        => 'image',
          'uid'         => $user_id,
          'field_image'  => [
            'target_id'   => $media->id(),
          ],
        ]);
        $p->isNew();
        $p->save();

        $paragraphs[] = [
          'target_id' => $p->id(),
          'target_revision_id' => $p->getRevisionId()
        ];
      }


      return $paragraphs;
    }
}
