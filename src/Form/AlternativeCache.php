<?php

namespace Drupal\try_cache\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AlternativeCache.
 */
class AlternativeCache extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The cache.default cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;


  /**
   * Dependency injection through the constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache object associated with the default bin.
   */
  public function __construct(
    RequestStack $request_stack,
    TranslationInterface $translation,
    AccountProxyInterface $current_user,
    CacheBackendInterface $cache_backend
  ) {
    $this->setRequestStack($request_stack);
    $this->setStringTranslation($translation);
    $this->currentUser = $current_user;
    $this->cacheBackend = $cache_backend;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Forms that require a Drupal service or a custom service should access
    // the service using dependency injection.
    // @link https://www.drupal.org/node/2203931.
    // Those services are passed in the $container through the static create
    // method.
    return new static(
      $container->get('request_stack'),
      $container->get('string_translation'),
      $container->get('current_user'),
      $container->get('cache.default')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'alternative_cache_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Log execution time.
    $start_time = microtime(TRUE);

    // Try to load the files count from cache. This function will accept two
    // arguments:
    // - cache object name (cid)
    // - cache bin, the (optional) cache bin (most often a database table) where
    //   the object is to be saved.
    //
    // cache_get() returns the cached object or FALSE if object does not exist.
    if ($cache = $this->cacheBackend->get('try_cache_files_count')) {
      /*
       * Get cached data. Complex data types will be unserialized automatically.
       */
      $files_count = $cache->data;
    }
    else {
      // If there was no cached data available we have to search filesystem.
      // Recursively get all .PHP files from Drupal's core folder.
      $files_count = count(file_scan_directory('core', '/.php/'));

      // Since we have recalculated, we now need to store the new data into
      // cache. Complex data types will be automatically serialized before
      // being saved into cache.
      // Here we use the default setting and create an unexpiring cache item.
      // See below for an example that creates an expiring cache item.
      $this->cacheBackend->set('try_cache_files_count', $files_count,
        CacheBackendInterface::CACHE_PERMANENT);
    }

    $end_time = microtime(TRUE);
    $duration = $end_time - $start_time;

    // Format intro message.
    $intro_message = '<p>' . $this->t("This example will search Drupal's core folder and display a count of the PHP files in it.") . ' ';
    $intro_message .= $this->t('This can take a while, since there are a lot of files to be searched.') . ' ';
    $intro_message .= $this->t('We will search filesystem just once and save output to the cache. We will use cached data for later requests.') . '</p>';
    $intro_message .= '<p>'
      . $this->t(
        '<a href="@url">Reload this page</a> to see cache in action.',
        ['@url' => $this->getRequest()->getRequestUri()]
      )
      . ' ';
    $intro_message .= $this->t('You can use the button below to remove cached data.') . '</p>';

    $form['file_search'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('File search caching'),
    ];
    $form['file_search']['introduction'] = [
      '#markup' => $intro_message,
    ];

    $color = empty($cache) ? 'red' : 'green';
    $retrieval = empty($cache) ? $this->t('calculated by traversing the filesystem') : $this->t('retrieved from cache');

    $form['file_search']['statistics'] = [
      '#type' => 'item',
      '#markup' => $this->t('%count files exist in this Drupal installation; @retrieval in @time ms. <br/>(Source: <span style="color:@color;">@source</span>)', [
          '%count' => $files_count,
          '@retrieval' => $retrieval,
          '@time' => number_format($duration * 1000, 2),
          '@color' => $color,
          '@source' => empty($cache) ? $this->t('actual file search') : $this->t('cached'),
        ]
      ),
    ];
    $form['file_search']['remove_file_count'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'expireFiles']],
      '#value' => $this->t('Explicitly remove cached file count'),
    ];

    return $form;
  }
  /**
   * Submit handler that explicitly clears cache_example_files_count from cache.
   */
  public function expireFiles($form, FormStateInterface &$form_state) {
    $a = 123;
    $form_state->setRedirect(
      'try_cache.alternative_cache_form_clear',
      ['cache_id' => 'try_cache_files_count']
    );
    return;
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }

  }

}
