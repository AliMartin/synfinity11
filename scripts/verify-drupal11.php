<?php

/**
 * @file
 * Drupal 11 HTTP checks: ddev drush --uri=http://localhost php:script scripts/verify-drupal11.php.
 * Creates a temporary local administrator session; no forms are submitted.
 */

use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;
use GuzzleHttp\Cookie\CookieJar;

$client = \Drupal::httpClient();
$jar = new CookieJar();
$options = ['http_errors' => FALSE, 'allow_redirects' => FALSE, 'cookies' => $jar];
$checkPage = static function (string $path, bool $authenticated = FALSE) use ($client, $options): string {
  $response = $client->get('http://localhost' . $path, $authenticated ? $options : ['http_errors' => FALSE]);
  $html = (string) $response->getBody();
  if ($response->getStatusCode() !== 200 || preg_match('~(Fatal error:|Uncaught |The website encountered an unexpected error)~i', $html)) {
    throw new RuntimeException('Page failed: ' . $path . ' (HTTP ' . $response->getStatusCode() . ')');
  }
  echo 'PASS: ' . ($authenticated ? 'authenticated ' : 'public ') . $path . "\n";
  return $html;
};

$html = $checkPage('/');
$checkPage('/gallery');
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
if (!$xpath->query('//*[@id="off-canvas"]//a')->length) {
  throw new RuntimeException('Responsive menu links missing');
}
echo "PASS: responsive menu links render\n";
$assetCount = 0;
foreach ($xpath->query('//link[@rel="stylesheet"]/@href | //script[@src]/@src') as $attribute) {
  $url = $attribute->nodeValue;
  if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
    $response = $client->get('http://localhost' . $url, ['http_errors' => FALSE]);
    if ($response->getStatusCode() !== 200) {
      throw new RuntimeException('Asset failed: ' . strtok($url, '?'));
    }
    $assetCount++;
  }
}
echo "PASS: $assetCount local CSS/JS assets load\n";
$account = User::load(1);
$url = \Drupal::service(OneTimeAuthentication::class)->generateOneTimeLoginUrl($account, ['absolute' => TRUE], TRUE)->toString();
$response = $client->get($url, $options);
unset($url);
if ($response->getStatusCode() !== 302 || !$jar->count()) {
  throw new RuntimeException('Local administrator session failed');
}
try {
  $admin = $checkPage('/admin/content', TRUE);
  if (!str_contains($admin, 'gin')) {
    throw new RuntimeException('Gin admin theme not present');
  }
  $checkPage('/admin/appearance', TRUE);
  $checkPage('/admin/structure/webform', TRUE);
  $report = $checkPage('/admin/reports/upgrade-status', TRUE);
  if (str_contains($report, 'Undefined property:') || str_contains($report, 'Theme::$origin')) {
    throw new RuntimeException('Upgrade Status theme origin warning persists');
  }
  $checkPage('/admin/config/media/background_image/settings', TRUE);
  $checkPage('/admin/config/user-interface/responsive-menu', TRUE);
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->range(0, 1)->execute();
  if ($id = reset($ids)) {
    $checkPage('/node/' . $id, TRUE);
    $checkPage('/node/' . $id . '/edit', TRUE);
  }
  $ids = \Drupal::entityQuery('webform')->accessCheck(FALSE)->condition('status', 'open')->range(0, 1)->execute();
  if ($id = reset($ids)) {
    $checkPage('/webform/' . $id, TRUE);
  }
}
finally {
  // Remove only the session created for these checks.
  foreach ($jar->toArray() as $cookie) {
    if (preg_match('/^S?SESS/', $cookie['Name'])) {
      \Drupal::database()->delete('sessions')->condition('sid', \Drupal\Component\Utility\Crypt::hashBase64($cookie['Value']))->execute();
    }
  }
}
