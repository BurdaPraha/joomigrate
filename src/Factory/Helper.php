<?php

namespace Drupal\joomigrate\Factory;

use Drupal\Core\Field\BaseFieldDefinition;

class Helper
{
    /**
     * @param $headers_data
     * @return bool
     */
    public static function validCsv($headers_data) {
        $is_valid = FALSE;
        foreach ($headers_data as $key => $header) {
            $is_valid = $key == $header;
        }
        return $is_valid;
    }


    /**
     * Put article fields and searched keywords in array, result is bool
     *
     * @param array $article_data
     * @param array $language_keys
     * @return bool
     */
    public static function checkEasyMatch(array $article_data, array $language_keys)
    {
        // data columns
        foreach($article_data as $i)
        {
            // find match in language keywords
            foreach($language_keys as $k)
            {
                $kLow = strtolower($k);
                if (preg_match("/{$k}/i", $i) || preg_match("/{$kLow}/i", $i))
                {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Shortcode content
     *
     * @param $string
     * @param $tag
     * @return null
     */
    public static function parseShortCode($string, $tag)
    {
        $regex = '#{'.$tag.'}(.*?){/'.$tag.'}#';
        preg_match($regex, $string, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


    /**
     * Find just YoutubeId, can be in format: "https://www.youtube.com/watch?v=20RoyFU4mjg" or "20RoyFU4mjg", ...
     *
     * @param $string
     * @return string
     */
    public static function parseYoutube($string)
    {
        // ["{YouTube}a0a6Y9JvPqo{\/YouTube}"]
        // ["{YouTube}http:\/\/ti.me\/1NxWIZZ{\/YouTube}","{YouTube}http:\/\/ti.me\/1Pk2QdH{\/YouTube}"]
        // ["{YouTube}https:\/\/www.youtube.com\/watch?v=20RoyFU4mjg{\/YouTube}"]

        preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $string, $matches);
        if(isset($matches[1]))
        {
            return $matches[1];
        }
        return null;
    }


    /**
     * Is it absolute url?
     *
     * @param $url
     * @return bool
     */
    public static function is_absolute($url)
    {
        $pattern = "/^(?:ftp|https?|feed):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
    (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
    (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
    (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

        return (bool) preg_match($pattern, $url);
    }


    /**
     * Convert entity to characters
     *
     * @param $v
     * @return mixed
     */
    public static function entityToString($v)
    {
        return is_string($v) ? preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $v) : $v;
    }


    /**
     * @todo: create some string cleaner for embeded javascripts etc
     *
     * @param $string
     * @return array
     */
    public static function possibleToReplace($string)
    {
        $words = [
            'adform.com'
        ];

        return array_keys($words, $string);
    }


    /**
     * Create new article alias, using pathauto module
     *
     * Using pathauto module
     * @param $string
     * @param $node_id
     * @param $lang_code
     * @return array
     */
    public static function articleAlias($string, $node_id, $lang_code)
    {
        $values = [];

        if(!empty($string) && strlen($string) > 5)
        {
            $path = \Drupal::service('path.alias_storage')->save('/node/' . $node_id, '/' . $string, $lang_code);
            $values['path'] = [
                'pathauto'  => 0,
                'alias'     => $path['alias']
            ];
        }

        return $values;
    }


    /**
     * Create new alias for channel, using pathauto module
     *
     * @param $string
     * @param $tax_id
     * @param $lang_code
     * @return array
     */
    public static function channelAlias($string, $tax_id, $lang_code)
    {
        $values = [];

        if(!empty($string) && strlen($string) > 5)
        {
            $path = \Drupal::service('path.alias_storage')->save('/taxonomy/' . $tax_id, '/' . $string, $lang_code);
            $values['path'] = [
                'pathauto'  => 0,
                'alias'     => $path['alias']
            ];
        }

        return $values;
    }


    /**
     * Check if article as promoted or not
     *
     * @param array $article_data columns which we will check to contain language_keys
     * @param array $language_keys simple array with have keys as 'Promotion', 'Advertisment' etc
     * @return bool
     */
    public static function checkPromotionArticle(array $article_data, array $language_keys)
    {
        return Helper::checkEasyMatch($article_data, $language_keys);
    }


    /**
     * Check if article is just concept or testing stuff
     *
     * @param array $article_data associative array with keys as kind
     * @param array $language_keys words which says that article is not for public use
     * @return bool
     */
    public static function checkDraftArticle(array $article_data, array $language_keys)
    {
        return Helper::checkEasyMatch($article_data, $language_keys);
    }


    /**
     * @param $data
     * @param $className
     * @return string
     */
    public static function getDivContent($data, $className)
    {
        $data = html_entity_decode($data);
        $data = mb_convert_encoding($data, 'HTML-ENTITIES', "UTF-8");

        $page = new \DOMDocument();
        @$page->loadHTML($data);
        $x = new \DOMXPath($page);
        $nodes = $x->query("//*[contains(@class, '$className')]");

        $d = new \DOMDocument();
        foreach ($nodes as $node)
        {
            $d->appendChild($d->importNode($node,true));
        }


        return $d->saveHTML();
    }


    /**
     * @param $string
     * @return null
     */
    public static function getVideoJSPath($string)
    {
        $page = new \DOMDocument();
        @$page->loadHTML($string);
        $x = new \DOMXPath($page);

        $scripts = $x->query("//script");
        foreach ($scripts as $s) {
            if (preg_match("#'src': '(.*?)'#", $s->nodeValue, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }


    /**
     * @param $string
     * @return string
     */
    public static function findGalleryImagesInString($string)
    {
        $gallery = [];
        $slides = 0;

        $page = new \DOMDocument();
        @$page->loadHTML($string);
        $x = new \DOMXPath($page);


        // how many slides?
        $n = $x->query("//ul[contains(@id, 'gallerynav')]");
        foreach ($n as $node){
            $slides = count($node->childNodes);
        }

        // get original images
        $a = $x->query("//*[contains(@rel, 'rokbox')]");
        foreach ($a as $slide => $node)
        {
            foreach($node->attributes as $attribute)
            {
                if('href' === $attribute->name){
                    $gallery[$slide]['filename'] = $attribute->textContent;
                }
            }
        }

        // get images description
        $d = $x->query("//*[contains(@class, 'gallery-image-description')]");
        foreach ($d as $slide => $node)
        {
            $e = $node->nodeValue;
            $d = new \DOMDocument();

            $d->appendChild($d->importNode($node, true));
            $d->replaceChild($d->firstChild->firstChild, $d->firstChild);

            $gallery[$slide]['description'] = $d->saveHTML();
        }


        return json_encode($gallery);
    }
}