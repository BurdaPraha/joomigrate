<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Form;

use Drupal\joomigrate\Entity\Article;
use Drupal\joomigrate\Factory\ArticleFactory;
use Drupal\node\Entity\Node;

use Drupal\joomigrate\Factory\Helper;
use Drupal\joomigrate\Factory\UserFactory;
use Drupal\joomigrate\Factory\TermFactory;
use Drupal\joomigrate\Factory\MediaFactory;
use Drupal\joomigrate\Factory\ParagraphFactory;
use Drupal\joomigrate\Factory\VideoFactory;

/**
 * Import articles from vanilla Joomla 3.5.1
 * Before this form you should submit form J3Categories
 *
 * Class J3Articles
 * @package Drupal\joomigrate\Form
 */
class J3Articles extends ExampleForm
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'joomigrate_joomla3_form';
    }

    /**
     * Strict header columns - copied structure of "#__content" table
     * @return array
     */
    public function getCsvHeaders()
    {
        return [
            'id',
            'asset_id',
            'title',
            'alias',
            'introtext',
            'fulltext',
            'state',
            'catid',
            'created',
            'created_by',
            'created_by_alias',
            'modified',
            'modified_by',
            'checked_out',
            'checked_out_time',
            'publish_up',
            'publish_down',
            'images',
            'urls',
            'attribs',
            'version',
            'ordering',
            'metakey',
            'metadesc',
            'access',
            'hits',
            'metadata',
            'featured',
            'language',
            'xreference',
        ];
    }


  /**
   * @param $data
   * @param $context
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public function processBatch($data, &$context)
    {
        $article = new ArticleFactory();
        $node = $article->loadArticleBySyncID($data['id']);

        $paragraphs = [];

        $created    = new \DateTime($data['created']);
        $publish    = new \DateTime($data['publish_up']);
        $down       = new \DateTime($data['publish_down']);
        $user       = new UserFactory();

        // if not been manually edited
        if(null == $node || true)
        {
            // find or create author
            $user_col = $data['created_by'];

            if(!empty(trim($data['created_by_alias'])))
            {
                $user_cleaner = explode('/', $data['created_by_alias']);
                $name = trim($user_cleaner[0]);
                if(!empty($name)){
                    $user_col = $user_cleaner[0];
                }
            }

            $user_id = $user->make($user_col);


            // Promotion - @todo: move parameters to form input / database, out of the script
            $promotion = Helper::checkPromotionArticle([
                $data['title'],
                $data['introtext'],
            ],
                [
                    'Promotion',
                    'Komerční',
                    'Reklama',
                    'Advertisment'
                ]
            );


            // is article public?
            $status         = trim($data['state']);
            $channel        = TermFactory::channel((int)$data['catid'], $data['catid']);
            $pre_perex      = Helper::getDivContent($data['fulltext'], 'article-perex');
            $perex          = strip_tags($pre_perex);

            // setup basic values
            $values = [
                'type'              => 'article',
                'langcode'          => 'cs',
                'promote'           => 1,
                "status"            => $status,
                'title'             => Helper::entityToString($data['title']),
                'field_seo_title'   => Helper::entityToString($data['title']),
                'created'           => $created->getTimestamp(),
                'publish_on'        => $status ? $publish->getTimestamp() : null,
                'publish_down'      => $down->getTimestamp(),
                //'changed'           => $created->getTimestamp(),
                'field_channel'     => [
                    'target_id' => $channel->id()
                ],
                'uid'                 => $user_id,
                'description'         => strip_tags($perex),
                'field_teaser_text'   => trim(html_entity_decode($perex))
            ];

            // sync
            $values[Article::$sync_field] = $data['id'];


            // main content
            $full_text = Helper::getDivContent($data['fulltext'], 'article-fulltext');
            $full = ParagraphFactory::make($full_text, $user_id, $data['id']);
            array_merge($paragraphs, $full);

            // have a gallery?
            $find_gallery = Helper::findGalleryImagesInString($data['fulltext']);

            // Teaser media
            if(count($find_gallery) >= 1) {
                $media = MediaFactory::image($find_gallery[0]['filename'], $data['title'], '', (int)$user_id, (int)$data['id']);
                if($media)
                {
                    $values['field_teaser_media'] = [
                        'target_id' => $media->id(),
                    ];
                }else {
                  drupal_set_message(t(
                    'CHECK nid: @nid without teaser media - title: <a href="@link">@name</a>',
                    [
                      "@nid" => $node->id(),
                      "@name" => $data['title'],
                      "@link" => \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(),
                    ]
                  ), 'warning');
                }
            }

            //
            // More than teaser photo? Create gallery
            //
            if (count($find_gallery) > 1)
            {
                // unset first photo (is already used as teaser)
                unset($find_gallery[0]);

                // respect pseudo-json format
                $string   = str_replace('"', "'", $find_gallery);
                $gallery  = json_encode($string);

                $gallery = MediaFactory::gallery($data['title'], $gallery, $data['alias'], (int)$data['id'], (int)$user_id);
                array_merge($paragraphs, $gallery);
            }


            // tags
            $tags = TermFactory::keywordsToTags($data['metakey'], $data['id']);
            array_merge($values, $tags);


            // it's a new article
            if (null == $node)
            {
                $node = Node::create($values);
                $node->save();

                drupal_set_message(t(
                  'NEW - nid: @nid, title: <a href="@link">@name</a>',
                  [
                    "@nid" => $node->id(),
                    "@name" => $data['title'],
                    "@link" => \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(),
                  ]
                ), 'success');
            }
            else
            {
                // update values for existing
                foreach($values as $key => $value)
                {
                    $node->{$key} = $value;
                }

                // remove all paragraphs for easy update
                $node = ParagraphFactory::removeFromNode($node);
            }


            // use existing alias, @todo: test it!
            //$path = Helper::articleAlias($data['alias'], $node->id(), 'cs');
            //$node->set('path', $path['path']);

            // save paragraphs
            $node->set('field_paragraphs', $paragraphs);

            // save updated node
            $node->save();
        }
        else
        {
          drupal_set_message(t(
            'SKIPPED: id: @id, nid: @nid because was changed manually: <a href="@link">@name</a>',
            [
              "@id" => $data['id'],
              "@nid" => $node->id(),
              "@name" => $data['title'],
              "@link" => \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(),
            ]
          ), 'warning');
        }

        // validate process errors
        /*
        if (!isset($context['results']['errors']))
        {
            $context['results']['errors'] = [];
        }
        else
        {
            var_dump($context['results']['errors']);
            die;

            // you can decide to create errors here comments codes below
            $context['results']['errors'][] = t('ERR: sync id: @id, article was not synchronized right', ['@id' => $data['id']]);
        }

        */
    }
}
