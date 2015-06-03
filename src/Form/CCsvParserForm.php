<?php

/**
 * @file
 * Contains \Drupal\c_csvparser\Form\CCsvParserForm.
 */

namespace Drupal\c_csvparser\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\field\Entity\FieldConfig;
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
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_type_storage
   *   The node type storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityStorageInterface $node_storage, EntityStorageInterface $node_type_storage, LanguageManagerInterface $language_manager, UrlGeneratorInterface $url_generator, DateFormatter $date_formatter) {
    $this->nodeStorage = $node_storage;
    $this->nodeTypeStorage = $node_type_storage;
    $this->languageManager = $language_manager;
    $this->urlGenerator = $url_generator;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Instantiate the entityfield query contatiner
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $entity_manager->getStorage('node'),
      $entity_manager->getStorage('node_type'),
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
    $form['browser'] = array(
      '#type'        =>'fieldset',
      '#title'       => t('Browser Upload'),
      '#collapsible' => TRUE,
      '#description' => t("Upload a CSV file."),
    );

    $file_size = t('Maximum file size: !size MB.', array('!size' => file_upload_max_size()));
    $form['browser']['file_upload'] = array(
      '#type'        => 'file',
      '#title'       => t('CSV File'),
      '#size'        => 40,
      '#description' => t('Select the CSV file to be imported. ') . $file_size,
    );

    $form['copy'] = array(
      '#type' => 'checkbox',
      '#title' => t('Skip first row'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Adding csv extension to validators - attempt to save the uploaded file
    $validators = array('file_validate_extensions' => array('csv'));
    $file = file_save_upload('file_upload', $validators);
    $file = reset($file);

    // check if file uploaded OK
    if (!$file) {
      $form_state->setErrorByName('file_upload', t('A file must be uploaded or selected from FTP updates.'));
    }
    else if($file->getMimeType() != 'text/csv') {
      $form_state->setErrorByName('file_upload', t('Only CSV file are allowed.'));
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
    $row_result = array();
    if ($handle) {
      // start count of imports for this upload
      $send_counter = 0;

      while ($row = fgetcsv($handle, 1000, ',')) {
        // $row is an array of elements in each row
        // Avoiding the first row because it contain the title.
        if ($send_counter != 0) {
          //Add your function create here
          $this->create($row, $send_counter);
        }
        $row_result[] = $row;
        $send_counter++;
      }
    }
    $tmp = $row_result;
  }

  /**
   * create one entity. Used by both batch and non-batch
   * @param $values
   */
  public function createEntity($values, $counter = 0) {
    $uid = 1;
    $node_type = 'page';
    $title = 'random title - '.$counter;

    $node = $this->nodeStorage->create(array(
      'nid' => NULL,
      'type' => $node_type,
      'title' => $title,
      'uid' => $uid,
      'revision' => 0,
      'status' => TRUE,
      'promote' => 0,
      'created' => REQUEST_TIME,
      'langcode' => 'en'
    ));

    $node->body = array(
      'value' => '<p>Vivamus suscipit tortor eget felis porttitor volutpat. Donec sollicitudin molestie malesuada.
Donec rutrum congue leo eget malesuada. Nulla quis lorem ut libero malesuada feugiat. Proin eget tortor risus.</p>',
      'format' => 'filtered_html'
    );
    $node->save();
  }
}