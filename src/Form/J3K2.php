<?php

namespace Drupal\joomigrate\Form;

use Drupal\node\Entity\Node;

use Drupal\joomigrate\Factory\Helper;
use Drupal\joomigrate\Factory\UserFactory;
use Drupal\joomigrate\Factory\TermFactory;
use Drupal\joomigrate\Factory\MediaFactory;
use Drupal\joomigrate\Factory\ParagraphFactory;
use Drupal\joomigrate\Factory\VideoFactory;

/**
 * Import articles from Joomla 3.x with K2 plugin - custom columns
 *
 * Class J3K2
 * @package Drupal\joomigrate\Form
 */
class J3K2 extends ExampleForm
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'joomigrate_form_j3k2';
    }


    /**
     * Strict header columns
     * @return array
     */
    public function getCsvHeaders()
    {
        return [
            'ID',
            'Title',
            'Alias',
            'Introtext',
            'Perex',
            'Tags',
            'Published',
            'Publish Up',
            'Publish Down',
            'Access',
            'Trash',
            'Created',
            'User ID',
            'Hits',
            'Language',
            'Video',
            'Ordering',
            'Featured',
            'Featured ordering',
            'Image',
            'Image caption',
            'Image credits',
            'Video caption',
            'Video credits',
            'Gallery Name',
            'Images for the Gallery',
            'Meta Description',
            'Meta Data',
            'Meta Keywords',
            'Teaser image',
            'Item Plugins',
            'Category Name',
            'Category Description',
            'Category Access',
            'Category Trash',
            'Category Plugins',
            'Category Image',
            'Category Language',
            'Comments'
        ];
    }


    /**
     * Processes the article synchronization batch.
     *
     * @param $data
     * @param $context
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function processBatch($data, &$context)
    {
        // can be saved or skipp it?
        if('0' == $data['Trash'])
        {
            // get db instance
            $db = \Drupal::database();

            // load node by joomla id
            $nodes = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties(['field_joomla_id' => $data['ID']]);


            // get one entity from array
            $node = end($nodes);

            $paragraphs = [];
            $created    = new \DateTime($data['Created']);
            $publish    = new \DateTime($data['Publish Up']);
            $down       = new \DateTime($data['Publish Down']);


            // if not been manually edited
            if(null == $node || $node->changed->value == $created->getTimestamp())
            {

                // find or create author
                $user_id    = UserFactory::make($data['User ID']);


                // Promotion - todo: move parameters to form input / database, out of the script
                $promotion = Helper::checkPromotionArticle([
                    $data['Title'],
                    $data['Category Name'],
                    $data['Meta Description'],
                    $data['Perex'],
                ],
                    [
                        'Promotion',
                        'Komerční',
                        'Reklama',
                        'Advertisment'
                    ]
                );


                // Find ugly articles - todo: move parameters to form input / database, out of the script
                $draft = Helper::checkDraftArticle([
                    $data['Title'],
                    $data['Category Name'],
                    $data['Meta Description'],
                    $data['Perex'],
                ],
                    [
                        'Test',
                        'Testovací',
                        'empty',
                        'empty category',
                        'Testing',
                        'Koncept'
                    ]
                );


                // is article public?
                $status = ('0' == trim($data['Trash']) && '1' == trim($data['Published']) ? 1 : 0);


                // video or text
                $article_type = (!empty($data['Category Name']) && 'Video' == $data['Category Name'] ? 4 : 3);


                // setup basic values
                $values = [
                    'field_joomla_id'   => $data['ID'],
                    'type'              => 'article',
                    'langcode'          => 'cs',
                    "promote"           => 1,

                    // visible?
                    "status"            => $status,

                    // titles
                    'title'             => Helper::entityToString($data['Title']),
                    'field_seo_title'   => Helper::entityToString($data['Title']),

                    // times
                    'created'           => $created->getTimestamp(),
                    'changed'           => $created->getTimestamp(),
                    'publish_on'        => $status ? $publish->getTimestamp() : null,
                    'publish_down'      => $down->getTimestamp(),

                    // category
                    'field_channel'     => [
                        'target_id' => TermFactory::channel($data['Category Name'])
                    ],

                    // set as text article
                    'field_article_type'  => [
                        'target_id' => $article_type
                    ],

                    // author
                    "uid"                 => $user_id,

                    // meta tags
                    "description"         => $data['Meta Description'],

                    // perex
                    'field_teaser_text'   => strip_tags($data['Perex']),

                    // read counter
                    'field_read_count'    => strlen($data['Hits']) > 0 ? trim((int) $data['Hits']) : 0,
                ];


                if(true == $promotion)
                {
                    $values['field_channel'] = [
                        'target_id' => TermFactory::channel('PR článek')
                    ];
                }


                if(true == $draft)
                {
                    $values['status'] = 0;
                    $values['field_channel'] = [
                        'target_id' => TermFactory::channel('--- Check this! ---')
                    ];
                }


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
                $path = Helper::articleAlias($data['Alias'], $node->id(), 'cs');
                array_merge($values, $path);


                // main content
                $perex = ParagraphFactory::createText($data['Introtext'], $user_id, $data['ID']);
                if($perex){
                    $paragraphs[] = $perex;
                }


                // have a gallery?
                if(!empty($data['Images for the Gallery']) && strlen($data['Images for the Gallery']) > 20)
                {
                    $gallery = MediaFactory::gallery($data['Title'], $data['Images for the Gallery'], $data['Alias'], $data['ID'], $user_id);
                    $paragraphs[] = $gallery;

                    // change to gallery type
                    $node->set('field_article_type', ['target_id' => 5]);
                }


                // have a video?
                if(!empty($data['Video']))
                {
                    // all is for now array - one format
                    $encoded = json_decode($data['Video']);
                    if(!isset($encoded[0]))
                    {
                        $encoded = [$data['Video']];
                    }

                    $videos = VideoFactory::make($encoded, $user_id);
                    $paragraphs[] = $videos;

                    if(count($videos) > 0)
                    {
                        $node->set('field_article_type', ['target_id' => 5]);
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

        }
        else
        {
            // send messsage about skipped article
            drupal_set_message($data['ID'] . ' - skipped because is in trash');
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
