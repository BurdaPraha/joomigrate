<?php

namespace Drupal\joomigrate\Factory;

use Drupal\paragraphs\Entity\Paragraph;

class ParagraphFactory
{


    /**
     * Convert base text to associative array of text elements and image (text, image, text)
     * in correct order as in input string. To be used as each paragraph types;
     * @param $string
     * @return array
     */
    public static function parseStringToParagraphsTypes($string)
    {
        $paragraphs = [];

        $chars = preg_split('/(<[^>]*[^\/]>)/i', $string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($chars as $key => $val)
        {
            // default
            $element = [
                'type'  => 'text',
                'val'   => $val
            ];

            //
            // test image
            //
            if(strpos($val, '<img') !== false) {
                $element['type'] = 'image';
            }

            //
            // @todo: there can be video or another paragraph type ... or shortcode fabric
            //


            //
            // test embeds
            //
            if(strpos($val, '<iframe') !== false) {


                //
                // video
                //
                // <iframe frameborder="0" src="https://www.youtube.com/embed/1yFmYi7HdCo" width="600px" height="380px"></iframe>
                $element['type'] = 'youtube';


                //
                //
                //


            }

            //
            // test legacy youtube
            //
            if(strpos($val, '<object') !== false) {
                //<param name="movie" value="http://www.youtube.com/v/IpbDHxCV29A" />
            }



            //
            // merge with the last paragraph if there isn't necessary creating new type
            //
            $keys       = array_keys($paragraphs);
            $last_key   = end($keys);

            if(
                count($paragraphs) > 0 &&
                'text' == $paragraphs[$last_key]['type'] &&
                'text' == $element['type'])
            {
                $key            = $last_key;
                $element['val'] = $paragraphs[$last_key]['val'] . $element['val'];
            }

            //
            // store
            //
            $paragraphs[$key] = $element;
        }


        return $paragraphs;
    }

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


    /**
     * @param $string
     * @param $user_id
     * @param $node_id
     * @return array|null
     */
    public static function createText($string, $user_id, $node_id)
    {
        $p = null;

        if(!empty($string) && strlen(strip_tags($string)) > 10)
        {
            $p = self::make($string, $user_id, $node_id);
        }

        return $p;
    }


    /**
     * @param $id
     */
    public static function remove($id)
    {
        $p = Paragraph::load($id);
        if($p){
            $p->delete();
        }
    }

    /**
     * @param $node
     * @return mixed
     */
    public static function removeFromNode($node)
    {
        $paragraphs = $node->get('field_paragraphs')->getValue();
        foreach ($paragraphs as $n => $i)
        {
            self::remove($i['target_id']);
        }


        return $node;
    }
}