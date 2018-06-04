<?php
namespace Drupal\try_cache\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
* Defines a confirmation form to confirm deletion of something by id.
*/
class CacheClearForm extends ConfirmFormBase {
  use StringTranslationTrait;
  protected $cacheBackend;
  protected $cache_id;

/**
* ID of the item to delete.
*
* @var int
*/
  public function __construct(
    CacheBackendInterface $cache_backend
  ) {
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
      $container->get('cache.default')
    );
  }

/**
* {@inheritdoc}
*/
public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
$this->id = $id;
return parent::buildForm($form, $form_state);
}

/**
* {@inheritdoc}
*/
public function submitForm(array &$form, FormStateInterface $form_state) {
  $this->cacheBackend->delete('try_cache_files_count');
  $messenger = \Drupal::messenger();
  $messenger->addMessage(t('Cached data key "try_cache_files_count" was cleared.'), 'status');
  $form_state->setRedirect(
    'try_cache.alternative_cache_form'
  );
  return;

}

/**
* {@inheritdoc}
*/
public function getFormId() : string {
return "confirm_delete_form";
}

/**
* {@inheritdoc}
*/
public function getCancelUrl() {
return new Url('try_cache.alternative_cache_form');
}

/**
* {@inheritdoc}
*/
public function getQuestion() {
return t('Do you want to delete %cache_id?', ['%cache_id' => $this->cache_id]);
}

}