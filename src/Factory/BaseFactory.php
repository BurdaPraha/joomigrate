<?php

namespace Drupal\joomigrate\Factory;

class BaseFactory
{
    /**
     * @var string
     */
    public $sync_field_name = 'field_joomigrate_id';

    /**
     * @var
     */
    public $entity_type;


    /**
     * BaseFactory constructor.
     */
    public function __construct()
    {
        $this->checkSyncFiled();
    }


    /**
     *
     */
    public function checkSyncFiled()
    {
        // $this->sync_field_name;
        // $this->createSyncField();
    }


    /**
     *
     */
    public function createSyncField()
    {
        //
    }


    /**
     * @param $content_type
     * @return array
     */
    public function getContentTypeFields($content_type)
    {
        /** @var $entityFieldManager \Drupal\Core\Entity\EntityFieldManager */
        $entityFieldManager = \Drupal::service('entity_field.manager');
        $fields = $entityFieldManager->getFieldStorageDefinitions($content_type);


        return $fields;
    }


    public function addContentTypeField($content_type, $bundle, $field = 'joomigrate_id')
    {
        /** @var $entityFieldManager \Drupal\Core\Entity\EntityFieldManager */
        $entityFieldManager = \Drupal::service('entity_field.manager');

        $fields = self::getContentTypeFields($content_type);


        //$fields[$field] = BaseFieldDefinition::create('integer')->setLabel('Joomigrate ID');
        //$entityFieldManager->setFieldMap($fields);
    }


    /**
     * @param $content_type
     * @param $field
     * @return bool
     */
    public function hasContentTypeFieldDefined($content_type, $field)
    {
        $fields = self::getContentTypeFields($content_type);
        return array_key_exists($fields, $field);
    }

}