<?php

namespace Drupal\adv_audit\Plugin\AuditPlugins;

use Drupal\adv_audit\Plugin\AuditBasePlugin;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Check if php files can be executed from public directory.
 *
 * @AuditPlugin(
 *  id = "temporary_files",
 *  label = @Translation("No sensitive temporary files were found."),
 *  category = "security",
 *  requirements = {},
 * )
 */
class SecurityTemporaryFilesPlugin extends AuditBasePlugin implements ContainerFactoryPluginInterface {

  /**
   * Drupal's kernel.
   *
   * @var \Drupal\Core\DrupalKernel
   */
  protected $kernel;

  /**
   * Core render service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructs a new PerformanceViewsPlugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\DrupalKernel $kernel
   *   Provide general information about drupal installation.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Provide access to render service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DrupalKernel $kernel, Renderer $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->kernel = $kernel;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('kernel'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $arguments = [];

    // Get list of files from the site directory.
    $files = [];
    $site_path = $this->kernel->getSitePath() . '/';
    $dir = scandir($site_path);
    foreach ($dir as $file) {
      // Set full path to only files.
      if (!is_dir($file)) {
        $files[] = $site_path . $file;
      }
    }

    // Analyze the files' names.
    foreach ($files as $path) {
      $matches = [];
      if (file_exists($path) && preg_match('/.*(~|\.sw[op]|\.bak|\.orig|\.save)$/', $path, $matches) !== FALSE && !empty($matches)) {
        // Found a temporary file.
        $arguments['issues'][$path] = $path;
      }
    }

    if (isset($arguments['issues']) && count($arguments['issues'])) {
      $issues = [];

      foreach ($arguments['issues'] as $path) {
        $issues[] = [
          '@issue_title' => 'Temporary file: @path.',
          '@path' => $path,
        ];
      }
      return $this->fail(NULL, ['issues' => $issues]);
    }

    return $this->success();

  }

}
