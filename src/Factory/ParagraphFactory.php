<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class ParagraphFactory
{
    /**
     * Convert base text to associative array of text elements and image (text, image, text)
     * in correct order as in input string. To be used as each paragraph types;
     * @param $string
     * @return array
     */
    public static function parseTypes($string): ?array
    {
      $t = [];

      $chars = preg_split('/(<[^>]*[^\/]>)/i', $string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
      foreach ($chars as $key => $val)
      {
        // default
        $element = [
          'type'  => 'text',
          'val'   => $val
        ];

        // inline-image
        if(strpos($val, '<img') !== false)
        {
          $element['type'] = 'image';
        }

        // embeds
        if(strpos($val, '<iframe') !== false)
        {
          // youtube / vimeo
          if(strpos($val, 'youtube') !== false || strpos($val, 'vimeo') !== false)
          {
              $element['type'] = 'video';
          }
        }

        // merge with the last paragraph if there isn't necessary creating new type
        $keys       = array_keys($t);
        $last_key   = end($keys);

        if(
          count($t) > 0 &&
          'text' == $t[$last_key]['type'] &&
          'text' == $element['type'])
        {
          $key            = $last_key;
          $element['val'] = $t[$last_key]['val'] . $element['val'];
        }

        // store
        $t[$key] = $element;
      }


      return $t;
    }


  /**
   * make paragraphs array
   * @param $data
   * @param int $user_id
   * @param int $article_id
   * @return array|null
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public static function make($data, $user_id = 1, $article_id = 1): ?array
    {
      $p = [];
      $types = self::parseTypes($data);

      foreach ($types as $p)
      {
        switch ($p['type'])
        {
          case 'text':
            $p[] = self::createText($p['val'], $user_id);
            break;

          case 'image':
            $i = MediaFactory::replaceInlineMedia($p['val'], $user_id, $article_id);
            array_merge($p, $i);
            break;

          case 'video':
            if (preg_match('#src="(.*?)"#', $p['val'], $matches)) {
                $i = VideoFactory::createEmbedFromSrc($matches[1], $user_id);
                array_merge($p, $i);
            }
            break;
        }

      }


      return $p;
    }


  /**
   * @param $string
   * @param $user_id
   * @return array|null
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createText($string, $user_id): ?array
  {
    $p = [];
    if(!empty($string) && strlen(strip_tags($string)) > 10)
    {
      // save
      $paragraph = Paragraph::create([
        'id'          => NULL,
        'type'        => 'text',
        'uid'         => $user_id,
        'field_text'  => [
            'value'   => $string,
            'format' => 'full_html',
        ],
      ]);
      $paragraph->isNew();
      $paragraph->save();


      $p = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId()
      ];
    }


    return $p;
  }


  /**
   * @param int $id
   * @return bool
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function remove(int $id): bool
  {
    $p = Paragraph::load($id);
    if($p instanceof Paragraph)
    {
      $p->delete();
      return true;
    }
  }

  /**
   * @param Node $node
   * @return Node
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function removeFromNode(Node $node): Node
  {
    $paragraphs = $node->get('field_paragraphs')->getValue();
    foreach ($paragraphs as $n => $i)
    {
      self::remove($i['target_id']);
    }


    return $node;
  }

}
