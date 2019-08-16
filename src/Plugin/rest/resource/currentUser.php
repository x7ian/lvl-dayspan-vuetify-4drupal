<?php

namespace Drupal\dayspan_vuetify\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource to fetch current user data.
 *
 * @RestResource(
 *   id = "current_user",
 *   label = @Translation("Current User data"),
 *   uri_paths = {
 *     "canonical" = "/api/current-user"
 *   }
 * )
 */
class CurrentUser extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing current user info.
   *
   */
  public function get() {
    $userCurrent = \Drupal::currentUser();
    $user = \Drupal\user\Entity\User::load($userCurrent->id());
    $userData = [
      'uid' => $user->get('uid')->value,
      'name' => $user->get('name')->value,
      'langcode' => $user->get('langcode')->value,
      'pass' => $user->get('pass')->value,
      'mail' => $user->get('mail')->value,
      'timezone' => $user->get('timezone')->value,
      'field_name' => $user->get('field_name')->value,
      'field_surname' => $user->get('field_surname')->value,
      'roles' => $user->getRoles(),
    ];
    return new ResourceResponse($userData);
  }

}
