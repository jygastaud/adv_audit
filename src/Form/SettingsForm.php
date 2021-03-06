<?php

namespace Drupal\adv_audit\Form;

use Drupal\adv_audit\Plugin\AuditPluginsManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\RedirectDestinationInterface;

/**
 * Settings page for Advanced Audit.
 */
class SettingsForm extends ConfigFormBase {

  protected $auditPluginManager;

  protected $configCategories;

  protected $state;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Use DI to work with config.
   * @param \Drupal\adv_audit\Plugin\AuditPluginsManager $advAuditCheckListManager
   *   Use DI to work with services.
   * @param \Drupal\Core\State\StateInterface $state
   *   Use DI to work with state.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   Use DI to work with redirect destination.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AuditPluginsManager $advAuditCheckListManager, StateInterface $state, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($config_factory);
    $this->configCategories = $config_factory->get('adv_audit.settings');
    $this->auditPluginManager = $advAuditCheckListManager;
    $this->state = $state;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'adv-audit-settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.adv_audit_check'),
      $container->get('state'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    $current_url = $this->redirectDestination->get();
    $categories = $this->getCategories();
    $plugin_list = $this->auditPluginManager->getPluginsByCategory();

    $form['categories'] = [
      '#type' => 'container',
    ];
    foreach ($categories as $category_id => $category) {
      $form['categories'][$category_id] = [
        '#type' => 'fieldset',
        '#title' => Link::createFromRoute($category['label'], 'adv_audit.category.settings_form', ['category_id' => $category_id])->toString(),
        $category_id . '_status' => [
          '#type' => 'checkbox',
          '#default_value' => $category['status'],
          '#attributes' => [
            'class' => ['category-status'],
            'title' => 'Disable the whole category',
          ],
        ],
        '#attributes' => [
          'class' => ['category-wrapper'],
        ],
      ];
      if (!isset($plugin_list[$category_id])) {
        continue;
      }

      foreach ($plugin_list[$category_id] as $plugin) {
        /** @var \Drupal\adv_audit\Plugin\AuditBasePlugin $plugin_instance */
        $plugin_id = $plugin['id'];
        $plugin_instance = $this->auditPluginManager->createInstance($plugin_id);

        $form['categories'][$category_id][$plugin_id] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['plugin-wrapper'],
          ],
        ];

        $form['categories'][$category_id][$plugin_id][$plugin_id] = [
          '#type' => 'checkbox',
          '#title' => $plugin['label'],
          '#default_value' => $plugin_instance->getStatus(),
        ];
        $plugin_edit_url = Url::fromRoute('adv_audit.plugin.settings',
          ['plugin_id' => $plugin_id],
          ['query' => ['destination' => $current_url]]);
        $form['categories'][$category_id][$plugin_id]['edit'] = [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => $plugin_edit_url,
          '#attributes' => [
            'class' => ['edit', 'edit-checkpoint'],
          ],
        ];
      }
    }

    $form['#attached']['library'][] = 'adv_audit/adv_audit.admin';
    return $form;
  }

  /**
   * Return list categories from config.
   *
   * @return mixed
   *   Array categories.
   */
  protected function getCategories() {
    return $this->configCategories->get('categories');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['adv_audit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Update plugins status.
    $values = $form_state->getValues();
    $categories = $this->getCategories();
    $plugin_list = $this->auditPluginManager->getPluginsByCategory();

    foreach ($categories as $key => $category) {
      if (!isset($plugin_list[$key])) {
        continue;
      }

      foreach ($plugin_list[$key] as $plugin) {
        $plugin_instance = $this->auditPluginManager->createInstance($plugin['id']);
        if (isset($values['categories'][$key][$plugin['id']][$plugin['id']])) {
          $plugin_instance->setPluginStatus($values['categories'][$key][$plugin['id']][$plugin['id']]);
        }
      }
    }

    // Save categories status.
    $config = $this->configFactory->getEditable('adv_audit.settings');
    $config_categories = $config->get('categories');
    foreach ($config_categories as $key => &$category) {
      $category['status'] = $values['categories'][$key][$key . '_status'];
    }

    $config->set('categories', $config_categories);
    $config->save();
  }

}
