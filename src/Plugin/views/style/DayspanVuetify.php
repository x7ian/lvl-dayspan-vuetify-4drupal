<?php

namespace Drupal\dayspan_vuetify\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\Entity\View;

/**
 * Style plugin to render a list of years and months
 * in reverse chronological order linked to content.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "dayspan_vuetify",
 *   title = @Translation("Dayspan Vuetify Display"),
 *   help = @Translation("Display view as a dayapsn vuetify calendar."),
 *   theme = "views_view_dayspan_vuetify",
 *   display_types = { "normal" }
 * )
 */
class DayspanVuetify extends StylePluginBase {

  /**
   * Does this Style plugin allow Row plugins?
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * Does the Style plugin support grouping of rows?
   *
   * @var bool
   */
  protected $usesGrouping = FALSE;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * This option only makes sense on style plugins without row plugins, like
   * for example table.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['add_views'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $view_display = $this->view->storage->id() . ':' . $this->view->current_display;

    $options = ['' => $this->t('-Select & save to add-')];
    $options += $this->getDaySpanViewsAsOptions();
    //Views::getViewsAsOptions(FALSE, 'all', $view_display, FALSE, TRUE);
    $form['add_views'] = [
      '#type' => 'select',
      '#title' => $this->t('Add view'),
      '#default_value' => (isset($this->options['add_views'])) ?
        $this->options['add_views'] : '',
      '#description' => $this->t('A view to add to this dayspan vuetify instance.'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#size' => 12,
    ];

  }

  function getDaySpanViewsAsOptions() {
    $filter = ucfirst('all');
    $views = \Drupal::entityManager()
      ->getStorage('view')
      ->loadMultiple();

    $options = array();
    $a = 0;
    foreach ($views as $view) {
      $id = $view->id();
      foreach ($view->get('display') as $display_id => $display) {
        //  if ($display['display_options']['style']['type']=="dayspan_vuetify") {
          //dayspan_vuetify
          $options[$id . ':' . $display['id']] = t('View: @view - Display: @display', array(
            '@view' => $id,
            '@display' => $display['id'],
          ));
        //  }
      }
    }
    if ($sort) {
      ksort($options);
    }
    return $options;
  }

}
