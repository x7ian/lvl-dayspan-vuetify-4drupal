<?php

namespace Drupal\dayspan_vuetify\Controller;

use Drupal\views\Views;
use Drupal\user\Entity\User;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for DB log UI routes.
 */
class DayspanVuetifyJsonData extends ControllerBase {

  /**
   * list User Roles TODO: Move to rest resource plugin
   */
  public function getRoleUsers($role) {
    $ids = \Drupal::entityQuery('user')
                    ->condition('status', 1)
                    ->condition('roles', $role)
                    ->execute();
    $users = User::loadMultiple($ids);
    $results = [];
    //var_dump($users);die();
    foreach($users as $user) {
      $uid = $user->get('uid')->value;
      $name = $user->get('field_name')->value;
      $surname = $user->get('field_surname')->value;
      $results[] = [
        'value' => $uid,
        'text' => $name . ' ' . $surname,
      ];
    }
    return new JsonResponse($results);
  }

  function getUserData($userid) {
    $user = User::load($userid);
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
    return new JsonResponse($userData);
  }

  function getUserRoles($userid) {
    $user = \Drupal\user\Entity\User::load($userid);
    $roles = $user->getRoles();
    return new JsonResponse($roles);
  }

}
