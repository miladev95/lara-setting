# AGENTS.md

Guidance for AI coding agents working on the **miladev/lara-setting** Laravel package. After this file is read, you should understand the project layout, conventions, public API, and how to verify changes.

## Project Summary

- **Package**: `miladev/lara-setting` — a Laravel package for managing project settings (key/value store backed by the database with an in-memory cache layer).
- **Namespace**: `Miladev\LaravelSettings\` (PSR-4 → `src/`).
- **PHP**: `>=5.4.0` (composer.json). CI runs on PHP 8.1.
- **License**: MIT.
- **Author**: Miladev95 <miladev95@gmail.com>.
- **Packagist**: https://packagist.org/packages/miladev/lara-setting
- **Homepage**: https://github.com/miladev95/lara-setting

## Public API (Facade + Container Binding)

The container binds `Miladev\LaravelSettings\Setting` to the string key `'settings'` (`SettingServiceProvider::register()`). The `Setting` facade (`Miladev\LaravelSettings\Facades\Setting`) resolves via `getFacadeAccessor()` returning `'settings'`.

`src/Setting.php` exposes:

| Method | Signature | Description |
| --- | --- | --- |
| `has($key)` | `string -> bool` | True if key is in-memory cache or exists in DB. Uses `array_key_exists` so cached `null` values still count. |
| `set($key, $value = null, $autoload = false)` | `string, mixed, bool -> Model` | `updateOrCreate` on `(key)` with `value` and `autoload`. Also writes the value into the in-memory cache (`$this->data[$key] = $value`). |
| `get($key, $default = null)` | `string, mixed -> mixed` | Returns cached value when present (even if null), otherwise queries DB; falls back to `$default` when missing. |
| `forget($key)` | `string -> int` (rows deleted) | Unsets from cache and deletes the DB row. |
| `clean()` | `() -> int` (rows deleted) | Clears in-memory cache and deletes **all** rows in the `settings` table. |
| `clearCache()` | `() -> void` | Wipes only `$this->data`; DB rows untouched. Used for tests and after schema changes. |
| `all()` | `() -> array<string,mixed>` | Loads all rows, rebuilds `$this->data` map keyed by `key`, returns it. |
| `autoload()` | `() -> void` | Pulls rows where `autoload = true` (`pluck('value','key')`) and merges them into `$this->data`. |

In-memory cache lives in `Setting::$data` (array). It is process-local; there is no file/Redis cache yet (see Roadmap below).

## Directory Layout

```
src/
  Setting.php                  # main service class (the API surface)
  SettingServiceProvider.php   # binds 'settings', loads migrations, publishes config
  Facades/Setting.php          # facade accessor -> 'settings'
  Models/Setting.php           # Eloquent model (string PK 'key', non-incrementing, no timestamps)
config/
  settings.php                 # mergeConfig fallback config (currently has a single demo key)
database/
  migrations/2021_09_09_111219_create_settings_table.php
  factories/SettingFactory.php # default faker state
tests/
  TestCase.php                 # Orchestra Testbench, in-memory sqlite, RefreshDatabase
  Unit/SettingModelTest.php    # model attribute tests (factory-created)
  Unit/SettingServiceTest.php  # service-level has/get/set/forget/clean/all/autoload
  Feature/.gitkeep
.github/workflows/
  tests.yml                    # PHPUnit on PHP 8.1 with MySQL 8 service
  code-style.yml               # php-cs-fixer @PSR12 dry-run
phpunit.xml                    # bootstrap vendor/autoload.php; Unit + Feature suites
composer.json                  # scripts: `composer test`, `composer test-f`
```

## Database Schema

Table `settings` (migration `database/migrations/2021_09_09_111219_create_settings_table.php`):

- `key` — `string(64)`, primary key.
- `value` — `text`, nullable.
- `autoload` — `boolean`, default `false`. Rows with `autoload = true` are preloaded via `Setting::autoload()`.

The Eloquent model `Miladev\LaravelSettings\Models\Setting` declares:
- `$primaryKey = 'key'`, `$keyType = 'string'`, `$incrementing = false`.
- `$timestamps = false`.
- `$fillable = ['key','value','autoload']`.
- `$casts = ['autoload' => 'boolean']`.
- Custom factory via `newFactory()` pointing at `Miladev\LaravelSettings\Database\Factories\SettingFactory`.

## Service Provider

`SettingServiceProvider`:
- `boot()` loads `database/migrations` from package; publishes `config/settings.php` to `config_path('settings.php')` (tag: `config`) only when running in console.
- `register()` binds `'settings'` to a fresh `Setting` instance (note: **singleton-style binding is missing** — each `app('settings')` resolution in a request will get a new object with its own `$data` cache). `mergeConfigFrom` merges `config/settings.php` as `'settings'`.

> Watch out: tests in `SettingServiceTest` re-resolve `app('settings')` per test method via `RefreshDatabase`, which avoids the per-resolution cache issue. Be careful when adding tests that cache across calls without recreating the instance.

## Conventions & Style

- **Code style**: enforced by `.github/workflows/code-style.yml` using `php-cs-fixer` with `@PSR12`. Match it: 4-space indent, opening braces on same line, one class per file, one blank line between methods, no trailing whitespace.
- **PHP compatibility**: package declares `php >=5.4.0`. Avoid PHP 5.5+ syntax in src/ (no short array `[]` is fine since 5.4, but no return types, no scalar type hints in public API, no `??=` etc.). Tests/can use newer syntax (CI is PHP 8.1).
- **No comments in code unless asked** (per repo convention — current files only have PHPDoc and a few inline explanations; preserve, don't expand).
- **Eloquent over query builder** — `SettingModel::where(...)` etc. is the established style.
- **Cache writes are eager**: `set()` updates `$this->data` *before* persisting. `get()` reads cache first via `array_key_exists` so a stored `null` is honored.
- **Defensive deletes**: `forget()` unsets the cache key even if the DB delete returns 0.

## Adding Features / Fixing Bugs

When implementing changes, keep these rules:

1. Keep the public API in `src/Setting.php` backward compatible. New methods are fine; do not change signatures of existing ones.
2. If you add new columns, update both the migration AND the model `$fillable`/`$casts`. Add a new migration file (do **not** edit the existing one — package consumers may have already run it). Filename pattern: `YYYY_MM_DD_HHMMSS_description.php`.
3. If a new config key is needed, add it to `config/settings.php` and document it via PHPDoc above the array. Config is merged under the `'settings'` key.
4. If you bind a new container singleton, do it in `SettingServiceProvider::register()`. Existing `bind('settings', ...)` should probably become a singleton (`singleton`) to fix the per-resolution cache issue — coordinate with maintainer before changing.
5. Tests live under `tests/Unit` for service/model and `tests/Feature` for end-to-end scenarios. Use `RefreshDatabase` and the `Setting::factory()` from `SettingFactory`.
6. After changes, run `composer test` and ensure PSR-12 compliance. CI will block otherwise.

## Roadmap (from README, in priority order)

1. Runtime in-memory caching to avoid duplicate queries (partially done — see `$this->data`).
2. File and Redis cache drivers.
3. Multiple storage drivers (database, file, Redis, custom).
4. Typed values and automatic serialization/deserialization (arrays, JSON).
5. Encryption support for sensitive values.
6. Validation rules for keys and values.
7. Per-setting expiration / TTL support.
8. Import/export (JSON/CSV) of settings.
9. Artisan commands for managing settings (list, set, forget, export).
10. Blade directives and helper functions for easy retrieval.
11. Events and hooks on create/update/delete.

If you implement a roadmap feature, add the corresponding tests in `tests/Unit` (and `tests/Feature` if applicable) and update the README usage section.

## How To Run / Verify

```bash
# Install deps
composer install

# Run full test suite (phpunit.xml -> Unit + Feature)
composer test

# Filter tests
composer test-f it_can_set_get_and_check_a_setting

# Or directly
vendor/bin/phpunit
vendor/bin/phpunit --filter it_can_set_get_and_check_a_setting

# PSR-12 check (CI runs this; locally download phar like the workflow does)
curl -L https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o php-cs-fixer.phar
chmod +x php-cs-fixer.phar
./php-cs-fixer.phar fix --dry-run --diff --rules=@PSR12 .
```

Tests use in-memory SQLite (`tests/TestCase.php` `getEnvironmentSetUp`), so they don't need a database server locally. CI uses MySQL 8 — both should pass.

## Common Debugging Spots

- "Setting not found after `set()`": check that `Setting::$data` is on the same instance. `app('settings')` returns a new instance each call (current bind, not singleton) — always grab it once per scope.
- "Autoloaded values stale": call `Setting::autoload()` again or `Setting::clearCache()` then `get()` to force a DB read.
- "Migration didn't run": ensure consumer's app loads the package's `SettingServiceProvider` (auto-discovery via `composer.json` `extra.laravel.providers` should handle this for Laravel 5.5+).
- "Published config not picked up": consumer must `php artisan vendor:publish --provider="Miladev\LaravelSettings\SettingServiceProvider"`; remember this only runs in console per provider's `runningInConsole()` guard.

## Key Files Quick Reference

- Service entry: `src/Setting.php:7` (class declaration).
- Facade accessor: `src/Facades/Setting.php:10` (returns `'settings'`).
- Container registration: `src/SettingServiceProvider.php:25`.
- Model definition: `src/Models/Setting.php:9`.
- Migration: `database/migrations/2021_09_09_111219_create_settings_table.php:14`.
- Test bootstrap: `tests/TestCase.php:7`.
- Service-level tests: `tests/Unit/SettingServiceTest.php:9`.

When in doubt, mirror the style of the file you're editing and add tests that exercise the change end-to-end through the service (not just the model).