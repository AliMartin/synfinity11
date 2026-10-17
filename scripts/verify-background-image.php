<?php

/**
 * @file
 * Run with ddev drush php:script scripts/verify-background-image.php.
 * Checks the local compatibility patch without changing content or configuration.
 */

use Drupal\background_image\Entity\BackgroundImage;
use Drupal\background_image\Form\BackgroundImageSettingsForm;
use Drupal\Core\Form\FormState;

$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  echo "PASS: $message\n";
};
$manager = \Drupal::service('background_image.manager');
$path = tempnam(sys_get_temp_dir(), 'background-image-test-');
try {
  $gd = imagecreatetruecolor(4, 4);
  imagefill($gd, 0, 0, imagecolorallocate($gd, 51, 102, 153));
  imagepng($gd, $path);
  $image = \Drupal::service('image.factory')->get($path, 'gd');
  $check(strtolower($manager->colorSampleImage($image)) === '#336699', 'GD colour sampling returns the expected colour');
  $invalid = \Drupal::service('image.factory')->get($path . '-missing', 'gd');
  $check($manager->colorSampleImage($invalid, '#123456') === '#123456', 'Missing images retain the fallback colour');
}
finally {
  unlink($path);
}

$images = \Drupal::entityTypeManager()->getStorage('background_image')->loadMultiple();
foreach ($images as $image) {
  if ($image->getImageFile()) {
    $url = $image->getImageUrl();
    $check((bool) preg_match('~^https?://~', $url), 'Original image URL resolves for background ' . $image->id());
  }
}
$image = reset($images);
if ($image) {
  $uri = 'temporary://background-image-compatibility-' . uniqid() . '.css';
  try {
    $controller = \Drupal\background_image\Controller\BackgroundImageCssController::create(\Drupal::getContainer());
    $check((bool) $controller->buildCss($image, $uri), 'Responsive background CSS generates');
    $check(str_contains(file_get_contents($uri), 'background-image'), 'Generated CSS contains background rules');
  }
  finally {
    foreach ([$uri, $uri . '.gz'] as $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }
  }
}
if ($image) {
  $copy = unserialize(serialize($image));
  $check($copy instanceof BackgroundImage && $copy->id() === $image->id(), 'Background entities survive serialization');
}

$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal\user\Entity\User::load(1));
try {
  $form = \Drupal::formBuilder()->getForm(BackgroundImageSettingsForm::class);
  $check(isset($form['entities']), 'Background settings form builds');
  $state = new FormState();
  $instance = BackgroundImageSettingsForm::initForm($form, $state);
  $check($instance instanceof BackgroundImageSettingsForm, 'Form callbacks resolve injected constructors');
  if ($image) {
    $form = \Drupal::entityTypeManager()->getFormObject('background_image', 'edit')->setEntity($image);
    $built = \Drupal::formBuilder()->getForm($form);
    $check(!empty($built['#form_id']), 'Existing background edit form builds');
  }
}
finally {
  $switcher->switchBack();
}
