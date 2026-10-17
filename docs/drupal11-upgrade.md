# Synfinity Drupal 11 upgrade

Completed locally on 17 October 2026:

- Drupal 10.6.19 to 11.4.10; core Composer requirements now allow Drupal 11.
- Gin 4.1.4 to 5.0.16 and Gin Toolbar 2.1.0 to 3.0.3.
- Symfony dependencies updated to 7.4; Drush remains 13.8.0.
- DDEV project type changed to drupal11; PHP 8.3 and MariaDB 11.8 retained.
- All pending schema and configuration updates applied; maintenance mode disabled.
- Drupal's standard scaffold files updated, including .htaccess. No pre-existing
  .htaccess customizations were found compared with the Drupal 10 scaffold.

The Background Image patch now includes the array return type required for
entity serialization on Drupal 11. Its Composer package override and patch must
stay together; see ../patches/README.md. TB Mega Menu's deprecated Symfony call
is behind a method_exists() check; on Drupal 11 its supported branch is used.

## Verification

- Composer platform requirements pass; no database updates remain.
- Composer reports no security vulnerability advisories. The existing abandoned
  oomphinc/composer-installers-extender package remains a maintenance concern.
- Background Image tests pass: all 46 original image URLs, GD colour sampling,
  missing-image fallback, generated responsive CSS, entity serialization,
  settings form and existing edit form. Reinstalling the module reapplies the patch.
- Homepage, rendered mobile menu links and five local CSS/JS assets return 200.
- Authenticated HTTP checks pass for Gin content/appearance administration,
  Webform administration, menu/background settings, node display/edit and the
  contact form. Verification sessions are removed after the check.
- No forms were submitted and no email-delivery test was performed.
- No interactive browser was available. Visually check desktop/mobile layouts,
  menu interactions, image cropping and editor interactions before production.

Repeat the checks with:

```sh
ddev drush php:script scripts/verify-background-image.php
ddev drush --uri=http://localhost php:script scripts/verify-drupal11.php
```

## Local rollback

The database snapshot is `before-drupal11-upgrade`. Pre-upgrade Composer files,
.htaccess and DDEV configuration are in `.ddev/rollback-drupal10/`, ignored by Git.
Restoring the database alone is insufficient; restore the matching code as well.
These commands intentionally discard subsequent local database changes:

```sh
cp .ddev/rollback-drupal10/composer.json composer.json
cp .ddev/rollback-drupal10/composer.lock composer.lock
cp .ddev/rollback-drupal10/.htaccess .htaccess
cp .ddev/rollback-drupal10/config.yaml .ddev/config.yaml
ddev composer install --no-interaction
ddev snapshot restore before-drupal11-upgrade
ddev restart
ddev drush cache:rebuild
```

The current Background Image patch also supports Drupal 10.3+, so it can remain
in place during a rollback. This upgrade has not been deployed to production.
