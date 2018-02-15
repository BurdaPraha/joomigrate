<?php

namespace Drupal\joomigrate\Factory;

/**
 * Class UserFactory
 * @package Drupal\joomigrate\Factory
 */
class UserFactory extends BaseFactory
{
    /**
     * Create user, author for imported article
     *
     * @param $cms_user_id
     * @return int|null|string
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public function make($cms_user_id)
    {
        if(empty($cms_user_id) || null == $cms_user_id){
            $cms_user_id = 1;
        }

        $findUser = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
            $this->sync_field_name => $cms_user_id
        ]);

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
        $user->setUsername($cms_user_id);

        // Optional.
        $user->set($this->sync_field_name, $cms_user_id);

        $user->set('init', 'email');
        $user->set('langcode', $language);
        $user->set('preferred_langcode', $language);
        $user->set('preferred_admin_langcode', $language);
        $user->addRole('editor');
        $user->activate();
        $user->save();

        return $user->id();
    }
}