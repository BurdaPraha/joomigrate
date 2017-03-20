<?php

namespace Drupal\joomigrate\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\media_entity\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Class ImportForm
 * @package Drupal\joomigrate\Form
 */
class ImportForm extends FormBase {


    /**
     * The node storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $nodeStorage;

    /**
     * The node type storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $nodeTypeStorage;

    /**
     * The language manager.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;

    /**
     * The url generator service.
     *
     * @var \Drupal\Core\Routing\UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatter
     */
    protected $dateFormatter;

    /**
     * @param EntityStorageInterface $node_storage
     * @param EntityStorageInterface $node_type_storage
     * @param LanguageManagerInterface $language_manager
     * @param UrlGeneratorInterface $url_generator
     * @param DateFormatterInterface $date_formatter
     */
    public function __construct(EntityStorageInterface $node_storage,
                                EntityStorageInterface $node_type_storage,
                                LanguageManagerInterface $language_manager,
                                UrlGeneratorInterface $url_generator,
                                DateFormatterInterface $date_formatter)
    {
        $this->nodeStorage = $node_storage;
        $this->nodeTypeStorage = $node_type_storage;
        $this->languageManager = $language_manager;
        $this->urlGenerator = $url_generator;
        $this->dateFormatter = $date_formatter;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        $entity_type_manager = $container->get('entity_type.manager');
        return new static(
            $entity_type_manager->getStorage('node'),
            $entity_type_manager->getStorage('node_type'),
            $container->get('language_manager'),
            $container->get('url_generator'),
            $container->get('date.formatter')
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'joomigrate_form';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['browser'] = [
            '#type'        =>'fieldset',
            '#title'       => $this->t('Browser Upload'),
            '#collapsible' => TRUE,
            '#description' => $this->t("Upload a CSV file."),
        ];

        $form['browser']['file_upload'] = [
            '#type'        => 'file',
            '#title'       => $this->t('CSV File'),
            '#size'        => 40,
            '#description' => $this->t('Select the CSV file to be imported. Maximum file size: !size MB.', [
                '@size' => file_upload_max_size()
            ]),
        ];

        $form['copy'] = [
            '#type' => 'checkbox',
            '#title' => t('Skip first row'),
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => t('Save'),
        ];

        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        // Adding csv extension to validators - attempt to save the uploaded file
        $validators = ['file_validate_extensions' => ['csv']];
        $file = file_save_upload('file_upload', $validators);
        $file = reset($file);

        // check if file uploaded OK
        if (!$file) {
            $form_state->setErrorByName('file_upload', $this->t('A file must be uploaded or selected from FTP updates.'));
        }
        else if($file->getMimeType() != 'text/csv') {
            $form_state->setErrorByName('file_upload', $this->t('Only CSV file are allowed.'));
        }
        else {
            // set files to form_state, to process when form is submitted
            $form_state->setValue('file_upload', $file);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $form_values = $form_state->getValues();
        ini_set('auto_detect_line_endings', true);

        $filepath = $form_values['file_upload']->getFileUri();
        $handle = fopen($filepath, "r");

        $error_msg = '';

        if ($handle) {
            // counter to skip line one cause considered as csv header
            $counter = 0;
            $batch = [
                'operations'        => [],
                'finished'          => [get_class($this), 'finishBatch'],
                'title'             => $this->t('CSV File upload synchronization'),
                'init_message'      => $this->t('Starting csv file upload synchronization.'),
                'progress_message'  => t('Completed step @current of @total.'),
                'error_message'     => t('CSV file upload synchronization has encountered an error.'),
                'file'              => __DIR__ . '/../../config.admin.inc',
            ];
            $valid_csv = FALSE;
            $headers = $this->getCsvHeaders();

            while ($row = fgetcsv($handle, 1000, ',')) {
                // checking if column from csv and row match
                if (count($row) > 0 && (count($headers) == count($row))) {
                    $data = array_combine($headers, $row);
                    // validating if the csv has the exact same headers
                    // @todo maybe move this logic in form validate
                    if ($counter == 0 && !$valid_csv = $this->validCsv($data)) {
                        break;
                    }
                    elseif ($counter > 0) {
                        // add row to be processed during batch run
                        $batch['operations'][] = [[get_class($this), 'processBatch'], [$data]];
                    }
                }
                else {
                    $error_msg = $this->t("CSV columns don't match expected headers columns!");
                }

                $counter++;
            }

            if ($valid_csv) {
                batch_set($batch);
            }
        }
        else {
            $error_msg = $this->t('CSV file could not be open!');
        }

        if ($error_msg) {
            drupal_set_message($error_msg, 'error');
        }
    }


    /**
     * @param $headers_data
     * @return bool
     */
    public function validCsv($headers_data) {
        $is_valid = FALSE;
        foreach ($headers_data as $key => $header) {
            $is_valid = $key == $header;
        }
        return $is_valid;
    }


    /**
     * Strict header columns
     * @return array
     */
    public function getCsvHeaders() {
        return array(
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
        );
    }


    /**
     * Processes the article synchronization batch.
     *
     * @param array $data
     *   The data row.
     * @param array $context
     *   The batch context.
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
                $user_id    = self::userJob($data['User ID']);


                // Promotion - todo: move parameters to form input / database, out of the script
                $promotion = self::checkPromotionArticle([
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
                $draft = self::checkDraftArticle([
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
                    'title'             => self::string($data['Title']),
                    'field_seo_title'   => self::string($data['Title']),

                    // times
                    'created'           => $created->getTimestamp(),
                    'changed'           => $created->getTimestamp(),
                    'publish_on'        => $status ? $publish->getTimestamp() : null,
                    'publish_down'      => $down->getTimestamp(),

                    // category
                    'field_channel'     => [
                        'target_id' => self::channelJob($data['Category Name'])
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
                    $variables['field_channel'] = [
                        'target_id' => self::channelJob('PR článek')
                    ];
                }


                if(true == $draft)
                {
                    $variables['status'] = 0;
                    $variables['field_channel'] = [
                        'target_id' => self::channelJob('--- Check this! ---')
                    ];
                }


                // Teaser media
                if(!empty($data['Teaser image']) && strlen($data['Teaser image']) > 10)
                {
                    $media = self::mediaJob($data['Teaser image'], $data['Image caption'], $data['Image credits'], $data['User ID'], $data['ID']);
                    if($media)
                    {
                        $values['field_teaser_media'] = [
                            'target_id' => $media->id(),
                        ];
                    }
                }

                // tags
                if(!empty($data['Meta Keywords']) && strlen($data['Meta Keywords']) > 5)
                {
                    $tags       = [];
                    $keywords   = explode(',', $data['Meta Keywords']);

                    foreach($keywords as $k => $tag)
                    {
                        // tag name
                        $name = trim($tag);

                        // check existing id
                        $tagExist = \Drupal::entityQuery('taxonomy_term')
                            ->condition('vid', 'tags')
                            ->condition('name', $name, 'CONTAINS')
                            ->execute();

                        if($tagExist)
                        {
                            // use existing
                            $term_id = end($tagExist);
                        }
                        else
                        {
                            // not exist
                            $term = Term::create([
                                'vid'                   => 'tags',
                                'name'                  => $name,
                                'field_tag_joomla_id'   => $data['ID']
                            ]);
                            $term->save();
                            $term_id = $term->id();
                        }

                        // store
                        $tags['target_id'] = $term_id;
                    }

                    // return var
                    if(count($tags) > 1)
                    {
                        $variables['field_tags'] = $tags;
                    }
                }


                // it's a new article
                if (false == $node)
                {
                    $node = Node::create($values);
                }
                else
                {
                    // update values for existing
                    foreach($values as $key => $value)
                    {
                        $node->{$key} = $value;
                    }

                    // remove all paragraphs for easy update
                    $paragraphs = $node->get('field_paragraphs')->getValue();
                    foreach ($paragraphs as $n => $i)
                    {
                        $p = Paragraph::load($i['target_id']);
                        if($p){ $p->delete(); }
                    }
                }


                // use existing alias
                if(!empty($data['Alias']) && strlen($data['Alias']) > 5)
                {
                    $path = \Drupal::service('path.alias_storage')->save('/node/' . $node->id(), '/' . $data['Alias'], 'cs');
                    $values['path'] = [
                        'pathauto'  => 0,
                        'alias'     => $path['alias']
                    ];
                }


                // main content
                if(!empty($data['Introtext']) && strlen(strip_tags($data['Introtext'])) > 10)
                {
                    $paragraphs[] = self::paragraphJob($data['Introtext'], $user_id, $data['ID']);
                }


                // have a gallery?
                if(!empty($data['Images for the Gallery']) && strlen($data['Images for the Gallery']) > 20)
                {
                    $gallery = self::mediaGalleryJob($data['Title'], $data['Images for the Gallery'], $data['Alias'], $data['ID'], $user_id);
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

                    $videos = self::videoJob($encoded, $user_id);
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


    private static function hackStatsModule()
    {
        db_truncate('search_index');
        db_truncate('search_dataset');
        db_truncate('search_total');
    }

    /**
     * Check if article as promoted or not
     * @param array $article_data columns which we will check to contain language_keys
     * @param array $language_keys simple array with have keys as 'Promotion', 'Advertisment' etc
     * @return bool
     */
    private static function checkPromotionArticle(array $article_data, array $language_keys)
    {
        return self::checkEasyMatch($article_data, $language_keys);
    }


    /**
     * Check if article is just concept or testing stuff
     * @param array $article_data associative array with keys as kind
     * @param array $language_keys words which says that article is not for public use
     * @return bool
     */
    private static function checkDraftArticle(array $article_data, array $language_keys)
    {
        return self::checkEasyMatch($article_data, $language_keys);
    }


    /**
     * @param array $article_data
     * @param array $language_keys
     * @return bool
     */
    private static function checkEasyMatch(array $article_data, array $language_keys)
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
     * @param $data
     * @param $user_id
     * @param $article_id
     * @return string
     */
    private static function replaceInlineMedia($data, $user_id, $article_id)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8'));
        $images = $doc->getElementsByTagName('img');

        if($images)
        {
            foreach ($images as $img)
            {
                // get original url
                $url = $img->getAttribute('src');
                $alt = $img->getAttribute('alt') ? $img->getAttribute('alt') : '';

                // create media
                // todo !
                $media = self::mediaJob($url, $alt, "", $user_id, $article_id);

                // replace path if media exist
                if($media->field_image->entity)
                {
                    $src = ImageStyle::load('large')->buildUrl($media->field_image->entity->getFileUri());
                    $img->setAttribute('src', $src);
                }

            }

            $data = $doc->saveHTML();
        }

        return $data;
    }


    /**
     * @param $data
     * @param int $user_id
     * @return array
     */
    private static function paragraphJob($data, $user_id = 1, $article_id)
    {
        //{{contest}}18{{/contest}} Otestujte revoluční novinku na omlazení pleti!


        // http://marianne-thunder.dev:8888/clanek/5-vanocnich-pisnicek-se-kterymi-si-vykouzlite-ty-nejkrasnejsi-svatky

        // inline images replacing
        $data = self::replaceInlineMedia($data, $user_id = 1, $article_id);

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
     * @return bool
     */
    private static function possibleToReplace($string)
    {
        $words = [
            'adform.com'
        ];

        return array_keys($words, $string);
    }


    /**
     * Shortcode content
     * @param $string
     * @param $tag
     * @return null
     */
    private static function parseShortCode($string, $tag)
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
    private static function parseYoutube($string)
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
     * @param $video array
     * @param $user_id int
     * @return array
     */
    private static function videoJob($video, $user_id)
    {
        $paragraphs = [];
        $video_paragraph = null;

        foreach($video as $k => $s)
        {
            $mp4        = self::parseShortCode($video, 'mp4');
            $youtube    = self::parseShortCode($video, 'YouTube');

            if($mp4)
            {
                // {mp4}29660{/mp4}
                $file   = self::fileJob('media/k2/videos/' . $mp4 . '/', $mp4 . '.mp4');
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
                $youtube_id = self::parseYoutube($video);
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
     * @param $path - full path of image
     * @param $file_name - original name of image
     * @param null $entity_id int - just for loging
     * @return null
     */
    private static function fileJob($path, $file_name, $entity_id = null)
    {
        $is_absolute    = self::is_absolute($path);
        $entity_id      = null == $entity_id || 1 == $entity_id ? null : $entity_id;
        $prefix_id      = $entity_id ? 'entity_id: ' . $entity_id . ' - ' : '';
        $normal_name    = strlen($file_name) >= 50 ? md5($file_name) . '.' . pathinfo($file_name, PATHINFO_EXTENSION) : $file_name;

        if(!$is_absolute)
        {
            $full_path = \Drupal::root() . "/sites/default/files/joomla/{$path}";

            // png quick fix
            $full_path_png = str_replace(".jpg", ".png", $full_path);
            if(file_exists($full_path_png))
            {
                $full_path = $full_path_png;
            }

            if(file_exists($full_path))
            {
                $file_data  = file_get_contents($full_path);
                $file       = file_save_data($file_data, 'public://'.date("Y-m").'/' . $normal_name, FILE_EXISTS_REPLACE);

                if($file)
                {
                    return $file;
                }
                else
                {
                    drupal_set_message($prefix_id . 'Problem with file_save_data, file: "' . $full_path . '"', 'warning');
                }
            }
            else
            {
                drupal_set_message($prefix_id . 'File: "' . $full_path . '" not exist!', 'warning');
            }

        }else
        {
            $file_data  = file_get_contents($path);
            $file       = file_save_data($file_data, 'public://'.date("Y-m").'/' . $normal_name, FILE_EXISTS_REPLACE);

            if($file)
            {
                return $file;
            }
            else
            {
                drupal_set_message($prefix_id . 'Problem with file_save_data, file: "' . $path . '"', 'warning');
            }
        }

        return null;
    }

    /**
     * Create file from existing source and media picture or use existing by name
     * @param $path
     * @param $description string
     * @param $credits string
     * @param $user int
     * @param int $import_id int
     * @return int|mixed|null|string
     */
    private static function mediaJob($path, $description = "", $credits = "", $user = 1, $import_id = 1)
    {
        $image_name     = explode("/", $path);
        $image_name     = end($image_name);
        $description    = !empty($description) && null !== $description ? $description : '';


        // check if not already exist
        $file_exist = \Drupal::entityQuery('file')
            ->condition('filename', $image_name, 'LIKE')
            ->execute();

        if(end($file_exist))
        {
            $media_exist = \Drupal::entityQuery('media')
                ->condition('field_image.target_id', end($file_exist))
                ->execute();

            if(end($media_exist))
            {
                $media_id = end($media_exist);
                return Media::load($media_id);
            }
        }

        // create new
        $file = self::fileJob($path, $image_name, $import_id);
        if($file)
        {
            $image_media = Media::create([
                'bundle'            => 'image',
                'uid'               => $user,
                'status'            => Media::PUBLISHED,
                'field_joomla_id'   => substr($import_id, 0, 7),
                'field_description' => $description,
                'field_source'      => $credits,
                'field_image'       => [
                    'target_id' => $file->id(),
                    //'alt'       => t('@alt', ['@alt' => substr($description, 0, 155)]),
                ],
            ]);
            $image_media->setQueuedThumbnailDownload();
            $image_media->save();
            return $image_media;
        }

        return null;
    }


    /**
     * Gallery array with objects
     * @param $name
     * @param $pseudoJson
     * @param $alias
     * @param $article_id
     * @param int $user_id
     * @return array
     */
    private static function mediaGalleryJob($name, $pseudoJson, $alias, $article_id, $user_id = 1)
    {
        /*** Check existing gallery ****/
        $galleryExisting = \Drupal::entityQuery('media')
            ->condition('bundle', 'gallery')
            ->condition('uid', $user_id)
            ->condition('name', $name)
            ->execute();

        if(end($galleryExisting))
        {
            $gallery_paragraph = \Drupal::entityTypeManager()
                ->getStorage('paragraph')
                ->loadByProperties(['field_media.target_id' => end($galleryExisting)]);

            $p = end($gallery_paragraph);
            if($p)
            {
                return ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()];
            }
        }


        /*** create new gallery ****/
        // fix json from CSV import
        $string   = str_replace("'", '"', $pseudoJson);
        $gallery  = json_decode($string);
        $images   = [];

        if(count($gallery) > 0)
        {
            foreach($gallery as $key => $image)
            {
                $media = self::mediaJob($image->filename, (!empty($image->description) ? $image->description : $image->title), '', $user_id, $image->dirId);
                if($media)
                {
                    $images[] = [ // $image->ordering
                        'target_id' => $media->id()
                    ];
                }

            }

            // create gallery
            $gallery_media = Media::create([
                'bundle'              => 'gallery',
                'uid'                 => $user_id,
                'status'              => Media::PUBLISHED,
                'name'                => $name,
                'field_media_images'  => $images,
                'field_gallery_joomla_id' => $article_id,
                'path' => [
                    'pathauto'  => 0,
                    'alias'     => '/galerie/' . $alias
                ]
            ]);


            $gallery_media->setQueuedThumbnailDownload();
            $gallery_media->save();

            // create gallery paragraph
            $gallery_paragraph = Paragraph::create([
                'type'        => 'gallery',
                'uid'         => $user_id,
                'field_media' => [
                    'target_id' => $gallery_media->id()
                ]
            ]);
            $gallery_paragraph->isNew();
            $gallery_paragraph->save();

            // todo: gallery path auto alias
            return ['target_id' => $gallery_paragraph->id(), 'target_revision_id' => $gallery_paragraph->getRevisionId()];
        }

        return null;
    }


    /**
     * Create channel for article or use existing by name
     * @param $name
     * @param int $joomla_id
     * @return int
     */
    private static function channelJob($name, $joomla_id = 1)
    {
        $channelExisting = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $name, 'vid' => 'channel']);

        if($channelExisting)
        {
            // use existing
            return end($channelExisting)->id();
        }

        // not exist
        $term = Term::create([
            'vid'             => 'channel',
            'name'            => $name,
            'field_joomla_id' => $joomla_id
        ]);
        $term->save();

        return $term->id();
    }


    /**
     * Create user, author for imported article
     * @param $JoomlaUserId
     * @return mixed
     */
    private static function userJob($JoomlaUserId)
    {
        if(empty($JoomlaUserId) || null == $JoomlaUserId) $JoomlaUserId = 1;

        $findUser = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->loadByProperties(['field_joomla_id' => $JoomlaUserId]);

        if($findUser)
        {
            return end($findUser)->id();
        }

        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $user = \Drupal\user\Entity\User::create();

        // Mandatory.
        $user->setPassword(time());
        $user->enforceIsNew();
        $user->setEmail(time() . "@studioart.cz");
        $user->setUsername($JoomlaUserId);

        // Optional.
        $user->set('field_joomla_id', $JoomlaUserId);
        $user->set('init', 'email');
        $user->set('langcode', $language);
        $user->set('preferred_langcode', $language);
        $user->set('preferred_admin_langcode', $language);
        $user->addRole('editor');
        $user->activate();
        $user->save();

        return $user->id();
    }


    /**
     * Convert entity to characters
     * @param $v
     * @return mixed
     */
    public static function string($v)
    {
        return is_string($v) ? preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $v) : $v;
    }


    /**
     * Finish batch.
     *
     * This function is a static function to avoid serializing the ConfigSync
     * object unnecessarily.
     */
    public static function finishBatch($success, $results, $operations) {
        if ($success) {
            if (!empty($results['errors'])) {
                foreach ($results['errors'] as $error) {
                    drupal_set_message($error, 'error');
                    \Drupal::logger('joomigrate')->error($error);
                }
                drupal_set_message(\Drupal::translation()->translate('The csv data parser was synchronized with errors.'), 'warning');
            }
            else {
                drupal_set_message(\Drupal::translation()->translate('The csv data parser was synchronized successfully.'));
            }

            // call cron for node scheduler
            //\Drupal::service('cron')->run(); // todo: find way how it can be run without Internal server error
        }
        else {
            // An error occurred.
            // $operations contains the operations that remained unprocessed.
            $error_operation = reset($operations);
            $message = \Drupal::translation()->translate('An error occurred while processing @error_operation with arguments: <pre>@arguments</pre>', [
                '@error_operation' => $error_operation[0],
                '@arguments' => print_r($error_operation[1], TRUE)
            ]);
            drupal_set_message($message, 'error');
        }
    }
}
