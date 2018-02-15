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
            'language',
            'xreference',
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

        // if not been manually edited
        if(null == $node || $node->changed->value == $created->getTimestamp())
        {

            // find or create author
            $user = new UserFactory();
            $user_id = $user->make($data['created_by']);


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
            $channel = TermFactory::channel($data['catid']);
            $perex = Helper::getDivContent($data['introtext'], 'article-perex');

            // setup basic values
            $values = [
                'type'              => 'article',
                'langcode'          => 'cs',
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
                    'target_id' => $channel['id']
                ],

                // set as text article
                'field_article_type'  => [
                    'target_id' => 3
                ],

                // author
                "uid"                 => $user_id,

                // meta tags
                "description"         => strip_tags($perex),

                // perex
                'field_teaser_text'   => strip_tags($perex),
            ];

            // sync
            $values[$article->sync_field_name] = $data['id'];



            // Teaser media
            if(!empty($data['Teaser image']) && strlen($data['Teaser image']) > 10)
            {
                $media = MediaFactory::image($data['Teaser image'], $data['Image caption'], $data['Image credits'], $data['User ID'], $data['ID']);
                if($media)
                {
                    $values['field_teaser_media'] = [
                        'target_id' => $media->id(),
                    ];
                }
            }

            // tags
            $tags = TermFactory::keywordsToTags($data['Meta Keywords'], $data['ID']);
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
            array_merge($values, $path);


            // main content
            $full_text = Helper::getDivContent($data['fulltext'], 'article-fulltext');
            $text = ParagraphFactory::createText($full_text, $user_id, $data['ID']);
            if($text){
                $paragraphs[] = $text;
            }


            // have a gallery?
            $find_gallery = Helper::findGalleryImagesInString($data['fulltext']);

            if(strlen($find_gallery) > 20)
            {
                $gallery = MediaFactory::gallery($data['title'], $find_gallery, $data['alias'], $data['id'], $user_id);
                $paragraphs[] = $gallery;

                // change to gallery type
                $node->set('field_article_type', ['target_id' => 5]);
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
                $video = VideoFactory::createMp4($mp4, time() . '.mp4', $user_id);

                $paragraphs[] = [
                    'target_id' => $video->id(),
                    'target_revision_id' => $video->getRevisionId()
                ];

                $node->set('field_article_type', ['target_id' => 5]);
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
            $message = t('Data with @id was not synchronized', ['@id' => $data['ID']]);
            $context['results']['errors'][] = $message;
        }
    }
}