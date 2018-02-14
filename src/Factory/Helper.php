<?php

namespace Drupal\joomigrate\Factory;

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
     * Check if article as promoted or not
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
     * @param array $article_data associative array with keys as kind
     * @param array $language_keys words which says that article is not for public use
     * @return bool
     */
    public static function checkDraftArticle(array $article_data, array $language_keys)
    {
        return Helper::checkEasyMatch($article_data, $language_keys);
    }
}