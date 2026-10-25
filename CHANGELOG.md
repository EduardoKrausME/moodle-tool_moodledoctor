# Changelog

This changelog documents **only the two most recent published GitHub releases** of `tool_moodledoctor`, based on comparisons with each release's immediately preceding **published** tag. Changes committed to `master` after `1.1.4` are excluded.

## [1.1.4] - 2026-10-15

Published: 2026-10-15 20:51:02 UTC.

Comparison: [1.1.3...1.1.4](https://github.com/EduardoKrausME/moodle-tool_moodledoctor/compare/1.1.3...1.1.4)

### Changed

- Updated `$plugin->release` in `version.php` from `1.1.3` to `1.1.4`.
- Updated `$plugin->version` from `2026101500` to `2026101501`.

### Scope

The comparison contains one commit (`1183214821da9021ab373fd2242f226773cf87e4`) and changes **only `version.php`**. The required Moodle build (`2024100700`) did not change. There are no functional code changes, bug fixes, or new features in this published-tag diff.

## [1.1.3] - 2026-10-15

Published: 2026-10-15 18:31:30 UTC.

Comparison: [1.1.2...1.1.3](https://github.com/EduardoKrausME/moodle-tool_moodledoctor/compare/1.1.2...1.1.3)

### Security: diagnostic-data sanitization

- Expanded `classes/sanitizer.php` to redact PEM private-key blocks and recognizable standalone AWS, Google API, GitHub and Slack token formats from diagnostic text.
- Enhanced key/value matching for secrets in PHP arrays, bracket notation, `print_r` output, and Moodle configuration properties, including password salts, SMTP passwords and other secret-related keys.
- Extended recursive structured-data redaction to match sensitive key suffixes and additional credential field names.
- Updated the English and Brazilian Portuguese sanitization notice to explicitly warn that email addresses, hostnames, IP addresses and file paths may remain in diagnostic previews and should be reviewed before sharing with an external AI provider.
- Added and expanded sanitizer regression cases for array formats, Moodle config secrets, private-key blocks, recognizable tokens, structured data and idempotent redaction.

### Privacy API

- Extended `classes/privacy/provider.php` to implement Moodle's user-data request and user-list privacy interfaces alongside the existing external-data metadata interface.
- Implemented empty context and user-data export/deletion handlers consistent with the plugin's declaration that it does not persist user data in its own storage.
- Added `tests/privacy_provider_test.php` to cover Moodle privacy-manager compliance and the absence of persisted user contexts.

### Continuous integration

- Updated GitHub Actions CI database services from PostgreSQL 16 to **PostgreSQL 17** and MariaDB 10 to **MariaDB 11.4**.
- Adjusted the MariaDB environment and readiness check from `mysqladmin ping` to `healthcheck.sh --connect --innodb_initialized`, including health-check retry settings.

### Documentation and version metadata

- Expanded `README.md` with installation instructions for the plugin-specific release ZIP, including the required `moodledoctor/` archive root and the distinction from GitHub's automatically generated source archive.
- Documented the sanitization scope and limits, including that emails, hostnames, IP addresses and file paths are not automatically removed.
- Updated `version.php` from plugin version `2026101200` / release `1.1.2` to `2026101500` / release `1.1.3`.

### Scope

The comparison spans eight commits. Its net file changes are `.github/workflows/ci.yml`, `README.md`, `classes/privacy/provider.php`, `classes/sanitizer.php`, English and Brazilian Portuguese language strings, `tests/privacy_provider_test.php`, `tests/sanitizer_test.php`, and `version.php`. Although commits within this interval mention the release workflow, **no net change to `.github/workflows/release.yml`** appears between the published tags. The archive-installation information above describes documentation changes, not a separately verified modification to release packaging.

---

Only differences observable in the published Git tag comparisons are recorded. This document does not claim test execution, deployments, or changes after release `1.1.4`.
