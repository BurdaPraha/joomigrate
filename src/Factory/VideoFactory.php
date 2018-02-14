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

            if($mp4)
            {
                // {mp4}29660{/mp4}
                $file   = FileFactory::make('media/k2/videos/' . $mp4 . '/', $mp4 . '.mp4');
                $video_paragraph = Paragraph::create([
                    'id'          => NULL,
                    'type'        => 'video',
                    'uid'         => $user_id,
                    'field_file'  => [
                        'target_id'   => $file->id()
                    ],
                ]);
                $video_paragraph->isNew();
                $video_paragraph->save();

            }
            if($youtube)
            {
                $youtube_id = Helper::parseYoutube($video);
                if($youtube_id)
                {
                    $video_paragraph = Paragraph::create([
                        'id'                => NULL,
                        'type'              => 'video_youtube',
                        'uid'               => $user_id,
                        'field_youtube_id'  => $youtube_id
                    ]);
                    $video_paragraph->isNew();
                    $video_paragraph->save();
                }
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

}