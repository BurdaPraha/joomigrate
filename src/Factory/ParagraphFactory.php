<?php

namespace Drupal\joomigrate\Factory;

use Drupal\paragraphs\Entity\Paragraph;

use Drupal\joomigrate\Factory\MediaFactory;

class ParagraphFactory
{

    /**
     * @param $data
     * @param int $user_id
     * @return array
     */
    public static function make($data, $user_id = 1, $article_id)
    {
        //{{contest}}18{{/contest}} Otestujte revoluční novinku na omlazení pleti!


        // http://marianne-thunder.dev:8888/clanek/5-vanocnich-pisnicek-se-kterymi-si-vykouzlite-ty-nejkrasnejsi-svatky

        // inline images replacing
        $data = MediaFactory::replaceInlineMedia($data, $user_id = 1, $article_id);

        // clear ugly code
        $value = str_replace("{{gallery}}", "", $data);

        // save
        $paragraph = Paragraph::create([
            'id'          => NULL,
            'type'        => 'text',
            'uid'         => $user_id,
            'field_text'  => [
                'value'   => $value,
                'format' => 'full_html',
            ],
        ]);
        $paragraph->isNew();
        $paragraph->save();

        return ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
    }
}