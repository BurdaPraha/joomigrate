<?php

namespace Drupal\c_csvparser\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CCsvParserForm
 * @package Drupal\c_csvparser\Form
 */
class CCsvParserForm extends FormBase {

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
   * CCsvParserForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_type_storage
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   */
  public function __construct(EntityStorageInterface $node_storage, EntityStorageInterface $node_type_storage, LanguageManagerInterface $language_manager, UrlGeneratorInterface $url_generator, DateFormatterInterface $date_formatter) {
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
    return 'c_csvparser_form';
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
        '!size' => file_upload_max_size()
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
        'operations' => [],
        'finished' => [get_class($this), 'finishBatch'],
        'title' => $this->t('CSV File upload synchronization'),
        'init_message' => $this->t('Starting csv file upload synchronization.'),
        'progress_message' => t('Completed step @current of @total.'),
        'error_message' => t('CSV file upload synchronization has encountered an error.'),
        'file' => __DIR__ . '/../../config.admin.inc',
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
  public static function processBatch($data, &$context) {
    $node = Node::load($data['nid']);
  
    if ($node) {
      // update node
      
      // save updated node
      $node->save();
    }
    else {
      // decide to create new node here
      // $values = [
      //   'type' => 'article',
      //   'title' => t('@title', ['@title' => $data['title']]),
      //   'status' => is_numeric($data['status']) &&  (bool) $data['status'] ? TRUE : FALSE,
      // ];
      // $node = Node::create($values);
      
      // then update other field below by calling e.g.
      // $node->field_name->setValue($data['field_name']);
      
      // $node->save();
  
      if (!isset($context['results']['errors'])) {
        $context['results']['errors'] = [];
      }
      else {
        // you can decide to create errors here comments codes below
        $message = t('Data with @title was not synchronized', ['@title' => $data['title']]);
        $context['results']['errors'][] = $message;
      }
    }
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
          \Drupal::logger('c_csvparser')->error($error);
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
      $message = \Drupal::translation()->translate('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE)
      ]);
      drupal_set_message($message, 'error');
    }
  }

  public function getCsvHeaders() {
    return array(
      'nid', 'title', 'alias', 'entity_ref__paragraphs__text__field_text',
      'status', 'created', 'changed',
      'entity_ref__paragraphs__gallery__field_media'
    );
  }

  public function validCsv($headers_data) {
    $is_valid = FALSE;
    foreach ($headers_data as $key => $header) {
      $is_valid = $key == $header;
    }
    return $is_valid;
  }

}
