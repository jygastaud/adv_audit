<?php

namespace Drupal\adv_audit;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Class AuditReason.
 *
 * Use in audit test plugin for save perform result.
 *
 * @package Drupal\adv_audit
 */
class AuditReason {

  /**
   * The status code.
   *
   * @var int
   */
  protected $status;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $testId;

  /**
   * The main reason data.
   *
   * May use to determine why test was failed.
   *
   * @var array|null|string|void
   */
  protected $reason;

  /**
   * AuditReason constructor.
   *
   * @param $plugin_id
   *   The plugin id.
   * @param $status
   *   The status of the perform test.
   * @param null $reason
   *   Reason why test is failed. (optional)
   */
  public function __construct($plugin_id, $status, $reason = NULL) {
    $this->status = $status;
    $this->test_id = $plugin_id;
    $this->reason = '';
    if (is_array($reason)) {
      foreach ($reason as $key => $string) {
        if ($string instanceof TranslatableMarkup) {
          $reason[$key] = $string->__toString();
        }
      }
    }
    elseif ($reason instanceof TranslatableMarkup) {
      $reason = $reason->__toString();
    }

    $this->reason = is_array($reason) ? implode('|', $reason) : $reason;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($plugin_id, $status, $reason = NULL) {
    return new static($plugin_id, $status, $reason);
  }

  /**
   * Return status of the test perform.
   *
   * @return mixed
   *   Return status code from AuditResultResponseInterface.
   */
  public function getStatus() {
    return $this->status;
  }

}