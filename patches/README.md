# Background Image compatibility patch

`background-image-drupal11.patch` adapts upstream Background Image 2.0.2 for
Drupal 10.3+ and 11. It replaces the removed GD and file URL APIs, removes
Symfony ContainerAware dependencies, resolves form instances through Drupal's
class resolver, updates token lookups and corrects the Webform image cache and
PHP string interpolation. It also declares the array return type required by
Drupal 11 for entity serialization.

Composer applies the patch through `extra.patches`. The first package repository
in composer.json uses the original upstream archive and checksum, but declares
`drupal/core: ^10.3 || ^11`. Patching module files alone cannot change Composer's
dependency metadata. The module is deliberately pinned to 2.0.2 while this local
override is in place; review upstream releases manually. This is a site-maintained
patch, not an upstream Drupal 11 release or a guarantee of security coverage for
the local changes.

When a suitable upstream release is available, remove this package repository,
the exact version pin and the patch entry together, then update the module.

Checks:

- `ddev composer reinstall drupal/background_image --no-interaction`
- `ddev drush php:script scripts/verify-background-image.php`
- `ddev drush upgrade_status:analyze background_image`

The verification script checks GD sampling, missing-image fallback, original
image URLs, responsive CSS generation, entity serialization and settings/edit
forms. It does not save content or configuration. Temporary test files are removed.
The scan may still report optional integration, legacy test-suite and Drupal 12
deprecation warnings; these are separate from the corrected Drupal 11 API errors.
Runtime checks passed on Drupal 11.4.10 with PHP 8.3.30, including a clean
Composer reinstall of the patched module. Visual browser review is still needed.


# Upgrade Status theme origin patch

`upgrade-status-theme-origin.patch` backports the approach from upstream
[issue 3607931](https://www.drupal.org/project/upgrade_status/issues/3607931)
to stable Upgrade Status 4.3.10. Drupal 11.4 theme objects lack the dynamic
`origin` property. Both project collection and obsolete-core detection now use
a guarded origin lookup and a `core/` pathname fallback. The fallback follows
Drupal's relative extension pathnames so core themes are correctly excluded.

The upstream fix is released in 5.0.0-alpha3. Remove this patch entry when
moving to a release that includes the fix. Composer applies it automatically;
`ddev composer reinstall drupal/upgrade_status --no-interaction` verifies it.
The HTTP verification script also checks the Upgrade Status report page.
