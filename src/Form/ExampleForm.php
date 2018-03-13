<?php

namespace Drupal\joomigrate\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

use Drupal\joomigrate\Factory\Helper;
use Drupal\joomigrate\Factory\UserFactory;
use Drupal\joomigrate\Factory\TermFactory;
use Drupal\joomigrate\Factory\MediaFactory;
use Drupal\joomigrate\Factory\ParagraphFactory;
use Drupal\joomigrate\Factory\VideoFactory;

/**
 * Class ExampleForm
 * @package Drupal\joomigrate\Form
 */
class ExampleForm extends FormBase
{
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
    public function __construct
    (
        EntityStorageInterface $node_storage,
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
    public static function create(ContainerInterface $container)
    {
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
    public function getFormId()
    {
        return 'joomigrate_base_form';
    }


    /**
     * Form markup
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
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

        $form['header'] = [
            '#type' => 'checkbox',
            '#title' => t('Use first row as Header'),
        ];

        $form['header_skip'] = [
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
     * Upload validator
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
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
     * Strict header columns, can be extended or used from the first line of CSV file
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
        ];
    }


    /**
     * Validate form and create batch cycle
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        ini_set('auto_detect_line_endings', true);

        $error_msg      = '';
        $valid_csv      = false;
        $form_values    = $form_state->getValues();
        $file_path      = $form_values['file_upload']->getFileUri();
        $handle         = fopen($file_path, "r");

        if (!$handle) {
            $error_msg = $this->t('CSV file could not be open!');
        }

        $batch = [
            'operations'        => [],
            'finished'          => [get_class($this), 'finishBatch'],
            'title'             => $this->t('CSV File upload synchronization'),
            'init_message'      => $this->t('Starting csv file upload synchronization.'),
            'progress_message'  => t('Completed step @current of @total.'),
            'error_message'     => t('CSV file upload synchronization has encountered an error.'),
            'file'              => __DIR__ . '/../../config.admin.inc',
        ];

        $headers = (int)$form_values['header'] === 0 ? $this->getCsvHeaders() : fgetcsv($handle, 1000, ';');

        $counter = 0;
        while ($row = fgetcsv($handle, 1000, ','))
        {
            // checking if column from csv and row match
            if (count($row) > 0 && (count($headers) == count($row)))
            {
                $data = array_combine($headers, $row);

                // add row to be processed during batch run
                $valid_csv = true;
                $batch['operations'][] = [[get_class($this), 'processBatch'], [$data]];
            }
            else
            {
                $error_msg = $this->t("CSV columns don't match expected headers columns! Try skip first row if CSV contain header.");
            }

            ++$counter;
        }

        if ($valid_csv) {
            batch_set($batch);
        }

        if ($error_msg) {
            drupal_set_message($error_msg, 'error');
        }
    }


    /**
     * Processes the article synchronization batch.
     *
     * @param $data
     * @param $context
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function processBatch($data, &$context)
    {
        // load node by joomla id
        $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties(['field_joomigrate_id' => $data['ID']]);


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

            // is article public?
            $status = ('0' == trim($data['Trash']) && '1' == trim($data['Published']) ? 1 : 0);

            $channel = TermFactory::channel($data['Category Name']);

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
                    'target_id' => $channel['id']
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


    /**
     * Finish batch.
     *
     * This function is a static function to avoid serializing the ConfigSync
     * object unnecessarily.
     *
     * @param $success
     * @param $results
     * @param $operations
     */
    public static function finishBatch($success, $results, $operations)
    {
        if ($success)
        {
            if (!empty($results['errors']))
            {
                foreach ($results['errors'] as $error)
                {
                    drupal_set_message($error, 'error');
                    \Drupal::logger('joomigrate')->error($error);
                }
                drupal_set_message(\Drupal::translation()->translate('The csv data parser was synchronized with errors.'), 'warning');
            }
            else
            {
                drupal_set_message(\Drupal::translation()->translate('The csv data parser was synchronized successfully. Run cron for publish scheduled articles.'));
            }

            // todo: find way how it can be run without Internal server error
            //\Drupal::service('cron')->run();
        }
        else
        {
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
