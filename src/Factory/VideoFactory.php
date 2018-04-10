<?php

namespace Drupal\joomigrate\Factory;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\video_embed_field\Plugin\Field\FieldFormatter\Video;

class VideoFactory
{
    /**
     * @param $video array
     * @param $user_id int
     * @return array
     */
    public static function make($video, $user_id)
    {
        $paragraphs = [];
        $video_paragraph = null;

        foreach($video as $k => $s)
        {
            $mp4        = Helper::parseShortCode($video, 'mp4');
            $youtube    = Helper::parseShortCode($video, 'YouTube');

            if($mp4) {
                // {mp4}29660{/mp4}
                $video_paragraph = self::createMp4('media/k2/videos/' . $mp4 . '/', $mp4 . '.mp4', $user_id);
            }

            if($youtube)
            {
                //self::createEmbed();
            }

            if($video_paragraph)
            {
                $paragraphs[] = [
                    'target_id' => $video_paragraph->id(),
                    'target_revision_id' => $video_paragraph->getRevisionId()
                ];
            }
        }

        return $paragraphs;
    }


    /**
     * @param $string
     * @param $user_id
     * @return \Drupal\Core\Entity\EntityInterface|null|static
     */
    public static function createEmbedFromSrc($string, $user_id)
    {
        $e = null;
        if($string)
        {
            $e = Paragraph::create([
                'id'    => NULL,
                'type'  => 'video',
                'uid'   => $user_id,
                'field_media_video_embed_field' => $string
            ]);

            $e->isNew();
            $e->save();
        }


        return $e;
    }


    /**
     * @param $path
     * @param null $file_name
     * @param $user_id
     * @return \Drupal\Core\Entity\EntityInterface|static
     */
    public static function createMp4($path, $file_name = null, $user_id)
    {
        $file = FileFactory::make($path, $file_name, $user_id);
        $p = Paragraph::create([
            'id'          => NULL,
            'type'        => 'video',
            'uid'         => $user_id,
            'field_file'  => [
                'target_id'   => $file->id()
            ],
        ]);
        $p->isNew();
        $p->save();


        return $p;
    }

}