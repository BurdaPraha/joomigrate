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
     * Processes the article synchronization batch.
     *
     * @param array $data
     *   The data row.
     * @param array $context
     *   The batch context.
     */
    public static function processBatch($data, &$context)
    {

        // load node by joomla id
        $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties(['field_joomla_id' => $data['ID']]);

        // get result from array
        $node = end($nodes);

        // format dates
        $created      = new \DateTime($data['Created']);
        $user_id      = self::userJob($data['User ID']);

        $values = [
            'field_joomla_id'   => $data['ID'],
            'type'              => 'article',
            'title'             => t('@title', ['@title' => $data['Title']]),
            'field_seo_title'   => $data['Title'], //t('@title', ['@title' => $data['Title']]),
            "promote"           => 1,
            "status"            => 1,
            'langcode'          => 'cs',
            "created"           => $created->getTimestamp(),
            "changed"           => $created->getTimestamp(),
            'field_channel'     => [
                'target_id' => self::channelJob($data['Category Name'])
            ],

            // set as text article
            'field_article_type'  => [
                'target_id' => 3
            ],

            // author
            "uid"                 => $user_id,
            "description"         => $data['Meta Description'],

            // teaser
            'field_teaser_media'  => [
                'target_id' => self::mediaJob($data['Teaser image'], $data['Image caption'], $data['Image credits'], $data['User ID'], $data['ID']),
            ],

            // perex
            'field_teaser_text'   => $data['Perex'],
        ];

        /**
         **** CREATE NEW ARTICLE ****
         */
        if (false == $node)
        {
            // create new!
            $node = Node::create($values);
            $node->save();

            $paragraphs = $node->get('field_paragraphs')->getValue();
            $paragraphs[] = self::paragraphJob($data['Introtext'], $user_id);

            // Gallery
            if(!empty($data['Images for the Gallery']) && strlen($data['Images for the Gallery']) > 20)
            {
                $paragraphs[] = self::mediaGalleryJob((!empty($data['Gallery Name']) ? $data['Gallery Name'] : $data['Title']), $data['Images for the Gallery'], $data['ID'], $user_id);
            }

            // video paragraph, todo
            if(!empty($data['Video']))
            {
                //$paragraphs[] = self::videoJob($data['Video']);
            }

            // save paragraphs
            $node->set('field_paragraphs', $paragraphs);
            //$node->field_paragraphs = $paragraphs;


            // update article
            $node->save();

            // todo: create path auto alias
            //\Drupal::service('path.alias_storage')->save("/node/" . $node->id(), "/" . $data['Alias'], "cs");


        }
        else
        {
            /**
             **** UPDATE NEW ARTICLE ****
             */


            foreach($values as $key => $value)
            {
                //$node->set = array($key, $value);
            }


            // save updated node
            $node->save();

            // todo: url for node - update
        }


        // validate process errors
        if (!isset($context['results']['errors']))
        {
            $context['results']['errors'] = [];
        }
        else
        {
            // you can decide to create errors here comments codes below
            $message = t('Data with @title was not synchronized', ['@title' => $data['title']]);
            $context['results']['errors'][] = $message;
        }
    }


    private static function paragraphJob($data, $user_id = 1)
    {
        $paragraph = Paragraph::create([
            'id'          => NULL,
            'type'        => 'text',
            'uid'         => $user_id,
            'field_text'  => [
                'value'   => $data,
                'format' => 'full_html',
            ],
        ]);
        $paragraph->isNew();
        $paragraph->save();

        return ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
    }


    /**
     * Create youtube embed via paragraphs
     * @param $video string
     */
    private static function videoJob($video)
    {
        // todo - create paragraph with video
    }


    /**
     * Create file from existing source and media picture or use existing by name
     * @param $path
     * @param string $title
     * @param string $credits
     * @param $user
     * @param int $joomla_id
     * @return int|mixed|null|string
     */
    private static function mediaJob($path, $title = "", $credits = "", $user, $joomla_id = 1)
    {
        $image_name = explode("/", $path);
        $image_name = end($image_name);


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
                return end($media_exist);
            }
        }

        // create new
        $file_data  = file_get_contents(\Drupal::root() . "/sites/default/files/joomla/{$path}");
        $file       = file_save_data($file_data, 'public://'.date("Y-m").'/' . $image_name, FILE_EXISTS_REPLACE);

        $image_media = Media::create([
            'bundle'  => 'image',
            'uid'     => $user,
            'status'  => Media::PUBLISHED,
            'field_joomla_id'   => substr($joomla_id, 0, 7),
            'field_source'      => $credits,
            'field_description' => $title,
            'field_image' => [
                'target_id' => $file->id(),
                'alt'       => t('@title', ['@title' => $title]),
                'title'     => t('@title', ['@title' => $title]),
            ],
        ]);
        $image_media->setQueuedThumbnailDownload();
        $image_media->save();
        return $image_media->id();
    }

    /**
     * Gallery array with objects
     * @param $name
     * @param $pseudoJson
     * @param $article_id
     * @param int $user_id
     * @return array
     */
    private static function mediaGalleryJob($name, $pseudoJson, $article_id, $user_id = 1)
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

            if(end($gallery_paragraph))
            {
                return ['target_id' => $gallery_paragraph->id(), 'target_revision_id' => $gallery_paragraph->getRevisionId()];
            }
        }


        /*** create new gallery ****/
        // fix json from CSV import
        $string   = str_replace("'", '"', $pseudoJson);
        $gallery  = json_decode($string);
        $images   = [];
        $gallery_id = 0;

        foreach($gallery as $key => $image)
        {
            $images[] = [ // $image->ordering
                'target_id' => self::mediaJob($image->filename, $image->title, $image->description, $user_id, $image->dirId)
            ];
        }

        // create gallery
        $gallery_media = Media::create([
            'bundle'              => 'gallery',
            'uid'                 => $user_id,
            'status'              => Media::PUBLISHED,
            'name'                => $name,
            'field_media_images'  => $images,
            'field_gallery_joomla_id' => $article_id
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
            ->loadByProperties(['name' => $name]);

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

        // todo: make alias via path auto
        //\Drupal::service('path.alias_storage')->save("/taxonomy/term/" . $term->id(), "/tags/my-tag", "en");

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
        }
        else {
            // An error occurred.
            // $operations contains the operations that remained unprocessed.
            $error_operation = reset($operations);
            $message = \Drupal::translation()->translate('An error occurred while processing @error_operation with arguments: @arguments', [
                '@error_operation' => $error_operation[0],
                '@arguments' => print_r($error_operation[1], TRUE)
            ]);
            drupal_set_message($message, 'error');
        }
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

}
