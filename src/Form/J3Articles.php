<?php

namespace Drupal\joomigrate\Form;

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
            //'language',
            //'xreference',
        ];
    }


    /**
     * @param $data
     * @param $context
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
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
        if(null == $node || $node->changed->value == $created->getTimestamp())
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


            // Promotion - todo: move parameters to form input / database, out of the script
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
            $status = trim($data['state']);
            $channel = TermFactory::channel($data['catid'], $data['catid']);
            $perex = Helper::getDivContent($data['fulltext'], 'article-perex');
            $perex = strip_tags($perex);

            // setup basic values
            $values = [
                'type'              => 'article',
                //'langcode'          => 'cs',
                "promote"           => 1,

                // visible?
                "status"            => $status,

                // titles
                'title'             => Helper::entityToString($data['title']),
                'field_seo_title'   => Helper::entityToString($data['title']),

                // times
                'created'           => $created->getTimestamp(),
                'changed'           => $created->getTimestamp(),
                'publish_on'        => $status ? $publish->getTimestamp() : null,
                'publish_down'      => $down->getTimestamp(),

                // category
                'field_channel'     => [
                    'target_id' => $channel->entity->id()
                ],

                // author
                "uid"                 => $user_id,

                // meta tags
                "description"         => strip_tags($perex),

                // perex
                'field_teaser_text'   => trim(html_entity_decode($perex))
            ];

            // sync
            $values[$article->sync_field_name] = $data['id'];


            // have a gallery?
            $find_gallery = Helper::findGalleryImagesInString($data['fulltext']);

            if (count($find_gallery) > 1)
            {
                $gallery = MediaFactory::gallery($data['title'], $find_gallery, $data['alias'], $data['id'], $user_id);
                $paragraphs[] = $gallery;
            }


            // Teaser media
            if(count($find_gallery) == 1)
            {
                $media = MediaFactory::image($find_gallery[0]['filename'], $data['title'], '', $user_id, $data['id']);
                if($media)
                {
                    $values['field_teaser_media'] = [
                        'target_id' => $media->id(),
                    ];
                }
            }


            // tags
            $tags = TermFactory::keywordsToTags($data['metakey'], $data['id']);
            array_merge($values, $tags);


            // it's a new article
            if (false == $node)
            {
                $node = Node::create($values);
                drupal_set_message('Nid: '.$node->id().' - Created new article');
            }
            else
            {
                // update values for existing
                foreach($values as $key => $value)
                {
                    $node->{$key} = $value;
                }

                // remove all paragraphs for easy update
                ParagraphFactory::removeFromNode($node);
            }


            // use existing alias
            $path = Helper::articleAlias($data['alias'], $node->id(), 'cs');
            $node->set('path', $path['path']);


            // main content
            $full_text = Helper::getDivContent($data['fulltext'], 'article-fulltext');
            $text = ParagraphFactory::createText($full_text, $user_id, $data['id']);
            if($text){
                $paragraphs[] = $text;
            }


            // have a video?
            $about_video = Helper::checkEasyMatch([
                $data['title'],
                $data['introtext'],
                $data['fulltext']
            ], [
                'video',
                'Video',
                'mp4',
                'youtube'
            ]);

            if($about_video)
            {
                $mp4 = Helper::getVideoJSPath($data['fulltext']);
                if($mp4) {

                    $video = VideoFactory::createMp4($mp4, time() . '.mp4', $user_id);
                    $paragraphs[] = [
                        'target_id' => $video->id(),
                        'target_revision_id' => $video->getRevisionId()
                    ];

                }
            }


            // save paragraphs
            $node->set('field_paragraphs', $paragraphs);


            // save updated node
            $node->save();

        }else
        {
            drupal_set_message($data['ID'] . '(drupal nid: '.$node->id().') - skipped because was changed manually');
        }

        // validate process errors
        if (!isset($context['results']['errors']))
        {
            $context['results']['errors'] = [];
        }
        else
        {
            // you can decide to create errors here comments codes below
            $message = t('Data with @id was not synchronized', ['@id' => $data['id']]);
            $context['results']['errors'][] = $message;
        }
    }
}