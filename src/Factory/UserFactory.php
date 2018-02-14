<?php

namespace Drupal\joomigrate\Factory;

class UserFactory
{
    /**
     * Create user, author for imported article
     *
     * @param $JoomlaUserId
     * @return int|null|string
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     */
    public static function make($JoomlaUserId)
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
}