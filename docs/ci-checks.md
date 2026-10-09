# CI and security checks

## Security gate

`.github/workflows/security-gate.yml` is the only trigger for the security workflows. It runs on pull requests and pushes to `main`, weekly and on demand, and the release workflow calls it on the tagged commit.

Its final job, **All security checks passed**, fails if any check failed, was cancelled or was skipped. A green gate means every scanner ran and found nothing it blocks on; it does not prove the code is secure.

- **Baseline** (`cybersecurity.yml`):
  - Semgrep CE (`p/php`, `p/javascript`);
  - a check that every `admin_post_*`/`wp_ajax_*` handler in `includes/class-admin.php` checks a capability and a nonce;
  - TruffleHog secret scan without verification;
  - zizmor (workflow security);
  - Trivy (dependencies and configuration, HIGH/CRITICAL);
  - WordPress security sniffs (PHPCS with WPCS 3.4.1, isolated in `.github/security`, never shipped);
  - actionlint.
- **CodeQL** (`codeql.yml`): JavaScript, on the public repository.
- **WordPress** (`wordpress-checks.yml`):
  - WordPress Plugin Check: general, security, performance and accessibility. The `plugin_repo` category is left out, because the plugin is self-hosted.
  - WordPress Playground smoke test on PHP 7.4 and 8.4. It activates the plugin and creates sample links. It also checks the 66 books and the importer, then loads the list page and every wp-admin menu page. A PHP error, warning, notice or deprecation from this plugin fails it.

## CI

`ci.yml` runs PHP lint, `tests/run.php` and the JavaScript syntax check on PHP 7.4 and 8.4, and builds a trial release ZIP.

## Links

`links.yml` checks Markdown links with lychee on pull requests that change them, monthly and on demand. It is not part of the security gate.

## Running locally

- `php tests/run.php`
- `PYTHONUTF8=1 python .github/playground/smoke.py --php 8.4` (needs Node 24 and Python 3)
- `composer install --working-dir=.github/security --no-plugins --no-scripts`, then `.github/security/vendor/bin/phpcs --standard=.github/security/phpcs.xml .`
