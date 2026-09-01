# AGENTS.md

Guidance for AI coding agents working on the **miladev/lara-setting** Laravel package. After this file is read, you should understand the project layout, conventions, public API, and how to verify changes.

## Project Summary

- **Package**: `miladev/lara-setting` — a Laravel package for managing project settings (key/value store with pluggable storage drivers and an in-memory cache layer).
- **Namespace**: `Miladev\LaravelSettings\` (PSR-4 → `src/`).
- **PHP**: `>=5.4.0` (composer.json). CI runs on PHP 8.1.
- **License**: MIT.
- **Author**: Miladev95 <miladev95@gmail.com>.
- **Packagist**: https://packagist.org/packages/miladev/lara-setting
- **Homepage**: https://github.com/miladev95/lara-setting

## Public API (Facade + Container Binding)

The container binds `Miladev\LaravelSettings\Setting` to the string key `'settings'` (singleton) and binds `Miladev\LaravelSettings\Contracts\SettingRepository` to a driver-specific repository (`SettingServiceProvider::register()`). The `Setting` facade (`Miladev\LaravelSettings\Facades\Setting`) resolves via `getFacadeAccessor()` returning `'settings'`.

`src/Setting.php` is a thin façade over a `SettingRepository`; all storage work is delegated. It exposes:

| Method | Signature | Description |
| --- | --- | --- |
| `has($key)` | `string -> bool` | True if key is known to the active driver. Uses `array_key_exists` semantics so cached `null` values still count. |
| `set($key, $value = null, $autoload = false)` | `string, mixed, bool -> mixed` | Persists the setting and updates the in-memory cache. For `database`, returns the `Setting` model; for `file`/`redis`, returns the value. |
| `get($key, $default = null)` | `string, mixed -> mixed` | Returns the cached value when present (even if `null`), otherwise reads from the active driver; falls back to `$default` on miss. |
| `forget($key)` | `string -> int` | Removes the key from cache and from the active driver. |
| `clean()` | `() -> int` | Clears in-memory cache and removes every entry from the active driver. |
| `clearCache()` | `() -> void` | Wipes only the in-memory cache; persistent storage is untouched. |
| `all()` | `() -> array<string,mixed>` | Returns every entry from the active driver and primes the in-memory cache. |
| `autoload()` | `() -> void` | Preloads entries the driver flagged as `autoload` into the in-memory cache. |

The in-memory cache lives in the repository's `$data` (array) and is process-local. Persistent storage and TTL depend on the active driver (see Storage Drivers below).

## Directory Layout

```
src/
  Setting.php                       # thin facade over SettingRepository (the API surface)
  SettingServiceProvider.php        # binds 'settings' (singleton) + SettingRepository (driver picked from config), loads migrations, publishes config
  Facades/Setting.php               # facade accessor -> 'settings'
  Models/Setting.php                # Eloquent model (string PK 'key', non-incrementing, no timestamps)
  Contracts/SettingRepository.php   # driver contract (has/get/set/forget/clean/clearCache/all/autoload)
  Repositories/
    DatabaseRepository.php          # Eloquent-backed driver (default)
    CacheStoreRepository.php        # abstract base for cache-backed drivers (file/redis): in-memory $data + cache put/forget + key index
    FileRepository.php              # file driver (native PHP file cache via Laravel's FileStore)
    RedisRepository.php             # redis driver (Laravel's redis cache store)
config/
  settings.php                      # merged config: 'driver' (database|file|redis), 'ttl' (minutes, 0=forever), 'file_path' (file driver)
database/
  migrations/2021_09_09_111219_create_settings_table.php
  factories/SettingFactory.php      # default faker state
tests/
  TestCase.php                      # Orchestra Testbench, in-memory sqlite, RefreshDatabase
  Unit/SettingModelTest.php         # model attribute tests (factory-created)
  Unit/SettingServiceTest.php       # service-level has/get/set/forget/clean/all/autoload (database driver)
  Feature/FileDriverTest.php        # end-to-end tests for the file driver
  Feature/.gitkeep
.github/workflows/
  tests.yml                         # PHPUnit on PHP 8.1 with MySQL 8 service
  code-style.yml                    # php-cs-fixer @PSR12 dry-run
phpunit.xml                         # bootstrap vendor/autoload.php; Unit + Feature suites
composer.json                       # scripts: `composer test`, `composer test-f`
```

## Storage Drivers

Driver is selected by `config('settings.driver')` (default `database`):

- **`database`** (default) — `DatabaseRepository`. Eloquent on the `settings` table. The only driver that returns Eloquent models from `set()`.
- **`file`** — `FileRepository` extends `CacheStoreRepository`. Stores each entry in Laravel's `FileStore` (native PHP file cache). Path defaults to `storage_path('framework/cache/lara-setting')`, override via `settings.file_path`. A `__index__` key tracks the set of known keys so `all()` and `autoload()` work without a directory scan.
- **`redis`** — `RedisRepository` extends `CacheStoreRepository`. Stores each entry in Laravel's `redis` cache store. Requires a `redis` cache store to be configured in `config/cache.php`.

`CacheStoreRepository` keeps an in-process `$data` array on top of the cache store. `set()`/`forget()`/`clean()` eagerly update both the in-memory cache and the cache store. `ttl` (minutes, `0` = forever) is honoured for both file and redis drivers.

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
- `register()`:
  - `mergeConfigFrom` merges `config/settings.php` under the `'settings'` key.
  - Binds `SettingRepository::class` as a singleton. The closure reads `config('settings.driver')` (`database`/`file`/`redis`), constructs the matching repository, and for `file` registers a `lara_setting_file` cache store pointing at `settings.file_path` (or the default `storage_path('framework/cache/lara-setting')`).
  - Binds `'settings'` as a singleton to a `Setting` instance wrapping the repository.

> Because `'settings'` is a singleton, repeated `app('settings')` calls in a request share the same in-memory cache. Tests re-resolve it per test method via `RefreshDatabase` to keep state isolated.

## Conventions & Style

- **Code style**: enforced by `.github/workflows/code-style.yml` using `php-cs-fixer` with `@PSR12`. Match it: 4-space indent, opening braces on same line, one class per file, one blank line between methods, no trailing whitespace.
- **PHP compatibility**: package declares `php >=5.4.0`. Avoid PHP 5.5+ syntax in src/ (no short array `[]` is fine since 5.4, but no return types, no scalar type hints in public API, no `??=` etc.). Tests/can use newer syntax (CI is PHP 8.1).
- **No comments in code unless asked** (per repo convention — current files only have PHPDoc and a few inline explanations; preserve, don't expand).
- **Eloquent over query builder** — `SettingModel::where(...)` etc. is the established style in `DatabaseRepository`.
- **Cache writes are eager**: every driver's `set()` updates its in-memory `$data` *before* persisting. `get()` reads cache first via `array_key_exists` so a stored `null` is honored.
- **Defensive deletes**: `forget()` unsets the cache key even if the underlying delete returns 0.
- **Driver boundary**: business logic belongs in the repositories, not in `Setting`. `Setting` is just delegation.

## Adding Features / Fixing Bugs

When implementing changes, keep these rules:

1. Keep the public API in `src/Setting.php` backward compatible. New methods are fine; do not change signatures of existing ones. If a new method must touch persistent storage, add it to `SettingRepository` and implement it in every driver.
2. If you add new columns to the database, update both the migration AND the model `$fillable`/`$casts`. Add a new migration file (do **not** edit the existing one — package consumers may have already run it). Filename pattern: `YYYY_MM_DD_HHMMSS_description.php`. Also extend the repository contract if a database-specific method is needed.
3. If a new config key is needed, add it to `config/settings.php` and document it via PHPDoc above the array. Config is merged under the `'settings'` key. For driver-specific config, follow the existing `settings.driver` / `settings.ttl` / `settings.file_path` shape.
4. New storage drivers go in `src/Repositories/`, implement `SettingRepository`, and are wired in `SettingServiceProvider::register()`. The cache-backed drivers should extend `CacheStoreRepository` to inherit the in-memory cache and `__index__` bookkeeping.
5. Tests live under `tests/Unit` for service/model and `tests/Feature` for end-to-end scenarios. Database tests use `RefreshDatabase` and `Setting::factory()`. Driver tests should override `getEnvironmentSetUp` to switch the active driver (see `tests/Feature/FileDriverTest.php`) and clean up any temp paths in `tearDown`.
6. After changes, run `composer test` and ensure PSR-12 compliance. CI will block otherwise.

## Roadmap (from README, in priority order)

1. ~~Runtime in-memory caching to avoid duplicate queries~~ (done — every repository has its own `$data`).
2. ~~File and Redis cache drivers~~ (done — see Storage Drivers).
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

Tests use in-memory SQLite (`tests/TestCase.php` `getEnvironmentSetUp`) for the database driver. File-driver tests (`tests/Feature/FileDriverTest.php`) point `settings.file_path` at a temp directory and clean it up in `tearDown`. CI uses MySQL 8 — both should pass.

## Common Debugging Spots

- "Setting not found after `set()`": check that the same `Setting`/`SettingRepository` instance is used. `app('settings')` is a singleton, so within a request the in-memory cache is shared — but across requests it is not. The cache is also process-local, not shared between PHP workers.
- "Autoloaded values stale": call `Setting::autoload()` again or `Setting::clearCache()` then `get()` to force a read from the active driver.
- "File driver writes nothing": confirm `settings.file_path` exists and is writable; the provider auto-creates the directory with mode 0775, but the parent must be writable. The `lara_setting_file` cache store is registered on demand by the provider — make sure the cache is not pre-emptively stubbed.
- "Redis driver errors": requires the `redis` PHP extension and a `redis` cache store in `config/cache.php`.
- "Wrong driver picked up": `SettingsServiceProvider` reads `config('settings.driver')` at resolution time. The merged config can be overridden by the published `config/settings.php`; publish and edit it instead of editing the package file.
- "Migration didn't run": ensure consumer's app loads the package's `SettingServiceProvider` (auto-discovery via `composer.json` `extra.laravel.providers` should handle this for Laravel 5.5+).
- "Published config not picked up": consumer must `php artisan vendor:publish --provider="Miladev\LaravelSettings\SettingServiceProvider"`; remember this only runs in console per provider's `runningInConsole()` guard.

## Key Files Quick Reference

- Service entry: `src/Setting.php:7` (class declaration).
- Repository contract: `src/Contracts/SettingRepository.php:7`.
- Database driver: `src/Repositories/DatabaseRepository.php:9`.
- Cache-backed driver base: `src/Repositories/CacheStoreRepository.php:9`.
- File driver: `src/Repositories/FileRepository.php:7`.
- Redis driver: `src/Repositories/RedisRepository.php:7`.
- Facade accessor: `src/Facades/Setting.php:10` (returns `'settings'`).
- Container registration: `src/SettingServiceProvider.php:23` (`register()`).
- Model definition: `src/Models/Setting.php:9`.
- Migration: `database/migrations/2021_09_09_111219_create_settings_table.php:14`.
- Test bootstrap: `tests/TestCase.php:7`.
- Service-level tests: `tests/Unit/SettingServiceTest.php:9`.
- File-driver tests: `tests/Feature/FileDriverTest.php:9`.

When in doubt, mirror the style of the file you're editing and add tests that exercise the change end-to-end through the service (not just the model).

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
- `register()`:
  - `mergeConfigFrom` merges `config/settings.php` under the `'settings'` key.
  - Binds `SettingRepository::class` as a singleton. The closure reads `config('settings.driver')` (`database`/`file`/`redis`), constructs the matching repository, and for `file` registers a `lara_setting_file` cache store pointing at `settings.file_path` (or the default `storage_path('framework/cache/lara-setting')`).
  - Binds `'settings'` as a singleton to a `Setting` instance wrapping the repository.

> Because `'settings'` is a singleton, repeated `app('settings')` calls in a request share the same in-memory cache. Tests re-resolve it per test method via `RefreshDatabase` to keep state isolated.

## Conventions & Style

- **Code style**: enforced by `.github/workflows/code-style.yml` using `php-cs-fixer` with `@PSR12`. Match it: 4-space indent, opening braces on same line, one class per file, one blank line between methods, no trailing whitespace.
- **PHP compatibility**: package declares `php >=5.4.0`. Avoid PHP 5.5+ syntax in src/ (no short array `[]` is fine since 5.4, but no return types, no scalar type hints in public API, no `??=` etc.). Tests/can use newer syntax (CI is PHP 8.1).
- **No comments in code unless asked** (per repo convention — current files only have PHPDoc and a few inline explanations; preserve, don't expand).
- **Eloquent over query builder** — `SettingModel::where(...)` etc. is the established style.
- **Cache writes are eager**: `set()` updates `$this->data` *before* persisting. `get()` reads cache first via `array_key_exists` so a stored `null` is honored.
- **Defensive deletes**: `forget()` unsets the cache key even if the DB delete returns 0.

## Adding Features / Fixing Bugs

When implementing changes, keep these rules:

1. Keep the public API in `src/Setting.php` backward compatible. New methods are fine; do not change signatures of existing ones. If a new method must touch persistent storage, add it to `SettingRepository` and implement it in every driver.
2. If you add new columns to the database, update both the migration AND the model `$fillable`/`$casts`. Add a new migration file (do **not** edit the existing one — package consumers may have already run it). Filename pattern: `YYYY_MM_DD_HHMMSS_description.php`. Also extend the repository contract if a database-specific method is needed.
3. If a new config key is needed, add it to `config/settings.php` and document it via PHPDoc above the array. Config is merged under the `'settings'` key. For driver-specific config, follow the existing `settings.driver` / `settings.ttl` / `settings.file_path` shape.
4. New storage drivers go in `src/Repositories/`, implement `SettingRepository`, and are wired in `SettingServiceProvider::register()`. The cache-backed drivers should extend `CacheStoreRepository` to inherit the in-memory cache and `__index__` bookkeeping.
5. Tests live under `tests/Unit` for service/model and `tests/Feature` for end-to-end scenarios. Database tests use `RefreshDatabase` and `Setting::factory()`. Driver tests should override `getEnvironmentSetUp` to switch the active driver (see `tests/Feature/FileDriverTest.php`) and clean up any temp paths in `tearDown`.
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

- "Setting not found after `set()`": check that the same `Setting`/`SettingRepository` instance is used. `app('settings')` is a singleton, so within a request the in-memory cache is shared — but across requests it is not. The cache is also process-local, not shared between PHP workers.
- "Autoloaded values stale": call `Setting::autoload()` again or `Setting::clearCache()` then `get()` to force a read from the active driver.
- "File driver writes nothing": confirm `settings.file_path` exists and is writable; the provider auto-creates the directory with mode 0775, but the parent must be writable. The `lara_setting_file` cache store is registered on demand by the provider — make sure the cache is not pre-emptively stubbed.
- "Redis driver errors": requires the `redis` PHP extension and a `redis` cache store in `config/cache.php`.
- "Wrong driver picked up": `SettingsServiceProvider` reads `config('settings.driver')` at resolution time. The merged config can be overridden by the published `config/settings.php`; publish and edit it instead of editing the package file.
- "Migration didn't run": ensure consumer's app loads the package's `SettingServiceProvider` (auto-discovery via `composer.json` `extra.laravel.providers` should handle this for Laravel 5.5+).
- "Published config not picked up": consumer must `php artisan vendor:publish --provider="Miladev\LaravelSettings\SettingServiceProvider"`; remember this only runs in console per provider's `runningInConsole()` guard.

## Key Files Quick Reference

- Service entry: `src/Setting.php:7` (class declaration).
- Repository contract: `src/Contracts/SettingRepository.php:7`.
- Database driver: `src/Repositories/DatabaseRepository.php:9`.
- Cache-backed driver base: `src/Repositories/CacheStoreRepository.php:9`.
- File driver: `src/Repositories/FileRepository.php:7`.
- Redis driver: `src/Repositories/RedisRepository.php:7`.
- Facade accessor: `src/Facades/Setting.php:10` (returns `'settings'`).
- Container registration: `src/SettingServiceProvider.php:23` (`register()`).
- Model definition: `src/Models/Setting.php:9`.
- Migration: `database/migrations/2021_09_09_111219_create_settings_table.php:14`.
- Test bootstrap: `tests/TestCase.php:7`.
- Service-level tests: `tests/Unit/SettingServiceTest.php:9`.
- File-driver tests: `tests/Feature/FileDriverTest.php:9`.

When in doubt, mirror the style of the file you're editing and add tests that exercise the change end-to-end through the service (not just the model).