<?php

namespace Drupal\joomigrate\Factory;

use Drupal\media_entity\Entity\Media;
use Drupal\image\Entity\ImageStyle;
use Drupal\joomigrate\Factory\FileFactory;

class MediaFactory
{
    /**
     * Gallery array with objects
     *
     * @param $name
     * @param $pseudoJson
     * @param $alias
     * @param $article_id
     * @param int $user_id
     * @return array|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function gallery($name, $pseudoJson, $alias, $article_id, $user_id = 1)
    {
        /*** Check existing gallery ****/
        $galleryExisting = \Drupal::entityQuery('media')
            ->condition('bundle', 'gallery')
            ->condition('uid', $user_id)
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
                $media = self::image($image->filename, (!empty($image->description) ? $image->description : $image->title), '', $user_id, $image->dirId);
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
                'field_gallery_joomla_id' => $article_id,
                'path' => [
                    'pathauto'  => 0,
                    'alias'     => '/galerie/' . $alias
                ]
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

            // todo: gallery path auto alias
            return ['target_id' => $gallery_paragraph->id(), 'target_revision_id' => $gallery_paragraph->getRevisionId()];
        }


        return null;
    }


    /**
     * Create file from existing source and media picture or use existing by name
     *
     * @param $path
     * @param $description string
     * @param $credits string
     * @param $user int
     * @param int $import_id int
     * @return int|mixed|null|string
     */
    public static function image($path, $description = "", $credits = "", $user = 1, $import_id = 1)
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
            $image_media = Media::create([
                'bundle'            => 'image',
                'uid'               => $user,
                'status'            => Media::PUBLISHED,
                'field_joomla_id'   => substr($import_id, 0, 7),
                'field_description' => $description,
                'field_source'      => $credits,
                'field_image'       => [
                    'target_id' => $file->id(),
                    //'alt'       => t('@alt', ['@alt' => substr($description, 0, 155)]),
                ],
            ]);
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
     * @return string
     */
    public static function replaceInlineMedia($data, $user_id, $article_id)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8'));
        $images = $doc->getElementsByTagName('img');

        if($images)
        {
            foreach ($images as $img)
            {
                // get original url
                $url = $img->getAttribute('src');
                $alt = $img->getAttribute('alt') ? $img->getAttribute('alt') : '';

                // create media
                // todo !
                $media = self::image($url, $alt, "", $user_id, $article_id);

                // replace path if media exist
                if($media->field_image->entity)
                {
                    $src = ImageStyle::load('large')->buildUrl($media->field_image->entity->getFileUri());
                    $img->setAttribute('src', $src);
                }

            }

            $data = $doc->saveHTML();
        }


        return $data;
    }
}