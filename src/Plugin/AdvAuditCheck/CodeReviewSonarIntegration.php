<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Executable\ExecutableException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Http\Client\Exception\RequestException;
use SonarQube\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Blocktrail\CryptoJSAES\CryptoJSAES;


/**
 * Check Database usage.
 *
 * @AdvAuditCheck(
 *  id = "sonar_integration",
 *  label = @Translation("Auditing code smells, code complexity. Code metrics
 *   and potential problems"),
 *  category = "code_review",
 *   severity = "normal",
 *   requirements = {}, enabled = true,
 * )
 */
class CodeReviewSonarIntegration extends AdvAuditCheckBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new PerformanceViewsCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param mixed $state
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->login();
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  protected function login($settings = FALSE) {
    if (!$settings) {
      $settings = $this->getPerformSettings();
    }
    $settings['password'] = CryptoJSAES::decrypt($settings['password'], Settings::getHashSalt());
    try {
      $this->sonar = new \SonarQube\Client($settings['entry_point'], $settings['login'], $settings['password']);
      $this->logged = $this->sonar->api('authentication')->validate();
    } catch (InvalidArgumentException $e) {
$i=0;
    }

  }

  /**
   * @inheritdoc
   */
  public function configForm() {
    $settings = $this->getPerformSettings();
    if ($this->logged) {
      $projects = $this->sonar->api('projects')->search();
      $options = [];
      foreach ($projects as $project) {
        $options[$project['id']] = $project['nm'];
      }
      $form['project'] = [
        '#type' => 'select',
        '#title' => $this->t('Choise project from sonar'),
        '#options' => $options,
        '#default_value' => $settings['project'],
      ];
    }

    $form['entry_point'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api url'),
      '#default_value' => $settings['entry_point'],
    ];

    $form['login'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login'),
      '#default_value' => $settings['login'],
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('password'),
      '#default_value' => $settings['password'],
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function configFormSubmit(array $form, FormStateInterface $form_state) {

    $value = $form_state->getValue('additional_settings')['plugin_config'];
    $settings = $this->getPerformSettings();
    if (!$value['password']) {
      $value['password'] = $settings['password'];
    }
    elseif ($value['password'] != $settings['password']) {
      $value['password'] = CryptoJSAES::encrypt($value['password'], Settings::getHashSalt());
    }
    $this->state->set($this->buildStateConfigKey(), $value);
  }

  /**
   * Get settings for perform task.
   */
  protected function getPerformSettings() {
    $settings = $this->state->get($this->buildStateConfigKey());
    return !is_null($settings) ? $settings : $this->getDefaultPerformSettings();
  }

  /**
   * Get default settings.
   */
  protected function getDefaultPerformSettings() {
    return [
      'max_table_size' => 512,
      'excluded_tables' => '',
    ];
  }

  /**
   * @inheritdoc
   */
  public function configFormValidate(array $form, FormStateInterface $form_state) {
    $settings = $this->getPerformSettings();
    $value = $form_state->getValue('additional_settings')['plugin_config'];
    if (!$value['password'] || $value['password'] == $settings['password']) {
      $value['password'] = CryptoJSAES::decrypt($settings['password'], Settings::getHashSalt());
    }
    $this->login($value);
    if (!$this->logged['valid']) {
      $form_state->setError($form, 'Wrong connect data');
    }
  }

  /**
   * Build key string for access to stored value from config.
   *
   * @return string
   *   The generated key.
   */
  protected function buildStateConfigKey() {
    return 'adv_audit.plugin.' . $this->id() . '.additional-settings';
  }

}
