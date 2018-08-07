<?php

namespace Drupal\adv_audit\Form;

use Drupal\adv_audit\Message\AuditMessagesStorageInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides implementation for the Run form.
 */
class AdvAuditPluginSettings extends FormBase {

  /**
   * Advanced plugin manager.
   *
   * @var \Drupal\adv_audit\Plugin\AdvAuditCheckManager
   */
  protected $advAuditPluginManager;

  /**
   * The Messages storeage service.
   *
   * @var \Drupal\adv_audit\Message\AuditMessagesStorageInterface
   */
  protected $messageStorage;

  /**
   * THe current request object.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The plugin id.
   *
   * @var mixed
   */
  protected $plugin_id;

  /**
   * The plugin instance.
   *
   * @var \Drupal\adv_audit\Plugin\AdvAuditCheckBase
   */
  protected $pluginInstance;

  /**
   * AdvAuditPluginSettings constructor.
   */
  public function __construct(AdvAuditCheckManager $manager, AuditMessagesStorageInterface $storage_message, RequestStack $request_stack) {
    $this->advAuditPluginManager = $manager;
    $this->messageStorage = $storage_message;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->plugin_id = $request_stack->getCurrentRequest()->attributes->get('plugin_id');
    $this->pluginInstance = $this->advAuditPluginManager->createInstance($this->plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.adv_audit_check'),
      $container->get('adv_audit.messages'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced-audit-edit-plugin';
  }

  /**
   * Get title of config form page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getTitle() {
    return $this->t('Configure plugin @label form', ['@label' => $this->pluginInstance->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#default_value' => $this->pluginInstance->getStatus(),
    ];

    $form['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity'),
      '#options' => [
        AdvAuditCheckInterface::SEVERITY_CRITICAL => 'Critical',
        AdvAuditCheckInterface::SEVERITY_HIGH => 'High',
        AdvAuditCheckInterface::SEVERITY_LOW => 'Low',
      ],
      '#default_value' => $this->pluginInstance->getSeverityLevel(),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_DESCRIPTION] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->messageStorage->get($this->plugin_id, AuditMessagesStorageInterface::MSG_TYPE_DESCRIPTION),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_ACTIONS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Action'),
      '#description' => $this->t('What actions should be provided to fix plugin issue.'),
      '#default_value' => $this->messageStorage->get($this->plugin_id, AuditMessagesStorageInterface::MSG_TYPE_ACTIONS),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_IMPACTS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Impact'),
      '#description' => $this->t('Why this issue should be fixed.'),
      '#default_value' => $this->messageStorage->get($this->plugin_id, AuditMessagesStorageInterface::MSG_TYPE_IMPACTS),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_FAIL] = [
      '#type' => 'text_format',
      '#title' => $this->t('Fail message'),
      '#description' => $this->t('This text is used in case when verification was failed.'),
      '#default_value' => $this->messageStorage->get($this->plugin_id, AuditMessagesStorageInterface::MSG_TYPE_FAIL),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_SUCCESS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Success message'),
      '#description' => $this->t('This text is used in case when verification was failed.'),
      '#default_value' => $this->messageStorage->get($this->plugin_id, AuditMessagesStorageInterface::MSG_TYPE_SUCCESS),
    ];

    if ($additional_form = $this->pluginInstance->configForm($form, $form_state)) {
      $form['additional_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Specific plugin settings'),
        '#tree' => TRUE,
        'custom_settings' => $additional_form,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save plugin configuration'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->pluginInstance->setPluginStatus($form_state->getValue('status'));
    $this->pluginInstance->setSeverityLevel($form_state->getValue('severity'));
    foreach ($form_state->getValue('messages', []) as $type => $text) {
      $this->messageStorage->set($this->plugin_id, $type, $text['value']);
    }
  }

}