<?php
declare(strict_types=1);

namespace Drupal\joomigrate\Factory;
use Drupal\joomigrate\Entity\Author;

/**
 * Class UserFactory
 * @package Drupal\joomigrate\Factory
 */
class UserFactory
{
  /**
   * Create user, author for imported article
   * @param $cms_user_id
   * @return int|null|string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    public function make(int $cms_user_id): int
    {
        if(empty($cms_user_id) || null == $cms_user_id){
            $cms_user_id = 1;
        }

        $findUser = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties([Author::$sync_field => $cms_user_id]);

        if($findUser)
        {
          return end($findUser)->id();
        }

        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $user = \Drupal\user\Entity\User::create();

        // Mandatory.
        $user->setPassword(time());
        $user->enforceIsNew();

        /** @todo: think about setup to domain */
        $user->setEmail("joomigrate-id-{$cms_user_id}-" . time() . "@studioart.cz");
        $user->setUsername($cms_user_id);

        // Optional.
        $user->set(Author::$sync_field, $cms_user_id);

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
