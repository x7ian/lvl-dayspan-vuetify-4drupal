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
   * list
   */
  public function getViewDataCounselor($viewname, $displayid, $userid) {
    $values = [];
    $fields = [];
    var_dump($viewname);
    $view = views::getview($viewname);
    if (is_object($view)) {
      $view->setArguments([$userid]);
      $view->setDisplay($displayid);
      //$displayObj = $view->getDisplay();
      $view->execute();
      $included_views = [$view];
      $stylePlugin = $view->getStyle();
      $add_views = $stylePlugin->options['add_views'];
      if (reset($add_views)!="") {
        foreach($add_views as $vw) {
          list($vwname, $dispid) = explode(':', $vw);

          $other_view = views::getView($vwname);
          $other_view->setDisplay($dispid);
          $other_view->preExecute();
          $other_view->execute();
          $included_views[] = $other_view;
        }
      }
      foreach($included_views as $vid => $vw) {
        foreach ($vw->result as $rid => $row) {
          //ksm($vw->result);
          foreach ($vw->field as $fid => $field ) {
            /*if (count($fields) != count($vw->field)) {
              $fields[] = $fi
            }*/
            ksm($fid);
            ksm($fid == 'field_counselor');
            if ($fid == 'field_counselor') {
              var_dump(get_class($field));die();
            }
            $plugin = null;
            if (method_exists($field, 'getItems')) {
              $fld_items = $field->getItems($row);
              if (count($fld_items)) {
                $plugin = $fld_items[0]['raw'];
              }
            }
            //var_dump($fld_items);die();
            // Si es de tipo imagen
            switch(get_class($plugin)) {
              case 'Drupal\image\Plugin\Field\FieldType\ImageItem':
              case 'Drupal\file\Plugin\Field\FieldType\FileItem':
                $target_id = $field->getValue($row);
                $file = \Drupal\file\Entity\File::load($target_id);
                $value = file_create_url($file->getFileUri());
              break;
              default:
                $value = $field->advancedRender($row);
                $value = $value;
            }
            $values[$vid.'_'.$rid][$fid] = $value;
          }
        }
      }
    }
    $response = [];
    foreach($values as $row) {
      $response[] = $this->buildDSEvent($row);
    }
    return new JsonResponse($response);
  }


  public function getViewDataPatient($viewname, $displayid, $userid, $counselorid) {
    $values = [];
    $fields = [];
    var_dump($viewname);
    $view = views::getview($viewname);
    if (is_object($view)) {
      $view->setArguments([$userid, $counselorid]);
      $view->setDisplay($displayid);
      //$displayObj = $view->getDisplay();
      $view->execute();
      $included_views = [$view];
      $stylePlugin = $view->getStyle();
      $add_views = $stylePlugin->options['add_views'];
      if (reset($add_views)!="") {
        foreach($add_views as $vw) {
          list($vwname, $dispid) = explode(':', $vw);

          $other_view = views::getView($vwname);
          $other_view->setDisplay($dispid);
          $other_view->preExecute();
          $other_view->execute();
          $included_views[] = $other_view;
        }
      }
      foreach($included_views as $vid => $vw) {
        foreach ($vw->result as $rid => $row) {
          //ksm($vw->result);
          foreach ($vw->field as $fid => $field ) {
            /*if (count($fields) != count($vw->field)) {
              $fields[] = $fi
            }*/
            ksm($fid);
            ksm($fid == 'field_counselor');
            if ($fid == 'field_counselor') {
              var_dump(get_class($field));die();
            }
            $plugin = null;
            if (method_exists($field, 'getItems')) {
              $fld_items = $field->getItems($row);
              if (count($fld_items)) {
                $plugin = $fld_items[0]['raw'];
              }
            }
            //var_dump($fld_items);die();
            // Si es de tipo imagen
            switch(get_class($plugin)) {
              case 'Drupal\image\Plugin\Field\FieldType\ImageItem':
              case 'Drupal\file\Plugin\Field\FieldType\FileItem':
                $target_id = $field->getValue($row);
                $file = \Drupal\file\Entity\File::load($target_id);
                $value = file_create_url($file->getFileUri());
              break;
              default:
                $value = $field->advancedRender($row);
                $value = $value;
            }
            $values[$vid.'_'.$rid][$fid] = $value;
          }
        }
      }
    }
    $response = [];
    foreach($values as $row) {
      $response[] = $this->buildDSEvent($row);
    }
    return new JsonResponse($response);
  }

  /**
   * list
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
    //$uid = \Drupal::currentUser()->id();
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

  function buildDSEvent($row) {
    //var_dump($row);die();
    //$node = \Drupal::entityTypeManager()->getStorage('node')->load($row['nid']);
    $color = '#9FA8DA';
    $color = '#cc0000';

    $nid = $row['nid']->__toString();
    //var_dump($nid);
    if (isset($row['field_time'])) {
      $dateTime = $row['field_time']->__toString();
      $schedule = $this->buildScheduleFromDate($dateTime);
    } else {
      $schedule = [
        'month' => [(int)$row['field_month']->__toString()],
        'dayOfMonth' => [(int)$row['field_day']->__toString()],
      ];
    }
    $type = '';
    if (isset($row['type'])) {
      $type = $row['type']->__toString();
    }
    //ksm($row['field_counselor']);
    return [
      'data' => [
        'nid' => $nid,
        'title' => strip_tags($row['title']->__toString()),
       // 'color' => $color,
        'type' => strtolower($type),
       // 'counselor' => $row['field_counselor']->__toString(),
      ],
      'schedule' => $schedule
    ];
  }

  function buildScheduleFromDate($dateTime) {
    // 2019-02-27T01:05:19
    list($date, $time) = explode('T', $dateTime);
    list($yyyy, $mm, $dd) = explode('-', $date);
    list($hh, $min, $sec) = explode(':', $time);
    return [
      'month' => [(int)$mm-1],
      'dayOfMonth' => [(int)$dd],
      'times' => [ $hh . ':' . $min ],
      'duration' => 60,
      'durationUnit' => 'minutes'
    ];
  }

  function getUserRoles($userid) {
    $user = \Drupal\user\Entity\User::load($userid);
    $roles = $user->getRoles();
    return new JsonResponse($roles);
  }

}
