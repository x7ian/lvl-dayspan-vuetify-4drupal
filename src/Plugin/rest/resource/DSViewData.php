<?php

namespace Drupal\dayspan_vuetify\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\views\Views;

/**
 * Provides a resource to fetch view results to be used by Dayspan Vuetify plugin.
 *
 * @RestResource(
 *   id = "ds_view_data",
 *   label = @Translation("Dayspan Vuetify fetch Data"),
 *   uri_paths = {
 *     "canonical" = "/api/view-data/{viewid}/{displayid}"
 *   }
 * )
 */
class DSViewData extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns list of view results.
   *
   * @param int $viewid
   *   The ID name of view.
   *
   * @param int $displayid
   *   The ID name of view diplay.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the view results formated to be used by dayspan
   * vuetify plugin.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the log entry was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when no log entry was provided.
   */
  public function get($viewid=NULL, $displayid=NULL) {
    if ($viewid && $displayid) {
      $records = $this->getViewData($viewid, $displayid);
      if (is_array($records)) {
        $build = array(
          '#cache' => array(
            'max-age' => 0,
          ),
        );
        $return = (new ResourceResponse($records))
                      ->addCacheableDependency($build);
        return $return;
      }
      throw new NotFoundHttpException(t('View @viewid or display @displayid was not found',
        ['@viewid' => $viewid, '@displayid' => $displayid]));
    }
    throw new BadRequestHttpException(t('A view ID and also a display ID needs to be provided'));
  }

  /**
   * listing view data
   */
  public function getViewData($viewname, $displayid) {
    $values = [];
    $fields = [];
    $view = views::getview($viewname);
    if (is_object($view)) {
      $view->setDisplay($displayid);
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
          foreach ($vw->field as $fid => $field ) {
            $plugin = null;
            if (method_exists($field, 'getItems')) {
              $fld_items = $field->getItems($row);
              if (count($fld_items)) {
                $plugin = $fld_items[0]['raw'];
              }
            }
            if ($plugin) {
              switch(get_class($plugin)) {
                case 'Drupal\image\Plugin\Field\FieldType\ImageItem':
                case 'Drupal\file\Plugin\Field\FieldType\FileItem':
                  $target_id = $field->getValue($row);
                  $file = \Drupal\file\Entity\File::load($target_id);
                  $value = file_create_url($file->getFileUri());
                break;
                default:
                  $value = $field->getValue($row);
                  $value = $value;
              }
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
    return $response;
  }

  /**
   * Helper function to format each event in the view listing.
   */
  function buildDSEvent($row) {
    $color = '#9FA8DA';
    $color = '#cc0000';
    $nid = $row['nid'];
    if (isset($row['field_time'])) {
      $dateTime = $row['field_time'];
      $dateTime = $this->convertToUserTimezone($dateTime);
      $schedule = $this->buildScheduleFromDate($dateTime);
    } else {
      $schedule = [
        'month' => [(int)$row['field_month']],
        'dayOfMonth' => [(int)$row['field_day']],
      ];
    }
    $type = '';
    if (isset($row['type'])) {
      $type = $row['type'];
    }
    return [
      'data' => [
        'nid' => $nid,
        'title' => $dateTime,
        'type' => strtolower($type),
      ],
      'schedule' => $schedule
    ];
  }

  /**
   * Helper funtion to format each schedule record for each event
   */
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

  /**
   * Convert a drupal date to the current user timezone date.
   */
  function convertToUserTimezone($date) {
    $date = new DrupalDateTime($date, 'UTC');
    $date->setTimezone(new \DateTimeZone(drupal_get_user_timezone()));
    $transformed = $date->format('Y-m-d\TH:i:s');
    return $transformed;
  }

}
