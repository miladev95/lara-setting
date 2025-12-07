# Laravel Settings

[![Latest Stable Version](https://poser.pugx.org/miladev/lara-setting/v)](//packagist.org/packages/miladev/lara-setting)
[![License](https://poser.pugx.org/miladev/lara-setting/license)](//packagist.org/packages/miladev/lara-setting)
[![Total Downloads](https://poser.pugx.org/miladev/lara-setting/downloads)](//packagist.org/packages/miladev/lara-setting)

A laravel package for managing project settings.

---
We always need to use a settings system in our application. This package will help you to create the system easily.
The package will create a table in database named `settings` with key, value and autoload column. You can specify which
column should be loaded in boot time by setting `autoload` column to true.

## Installation

You can install the package via composer:

```bash
composer require miladev/lara-setting
```

If you are using Laravel Package Auto-Discovery, you don't need you to manually add the ServiceProvider.

#### Without auto-discovery:

If you don't use auto-discovery, add the below ServiceProvider to the `$providers` array in `config/app.php` file.

```php
Miladev\LaravelSettings\SettingServiceProvider::class,
```

Then add the `Setting` facade in `$aliases` array in `config/app.php` file.

```php
'Setting' => \Miladev\LaravelSettings\Facades\Setting::class,
```

Then you can run migration command to create database table.

```bash
php artisan migrate
```

You can also publish the migration file and modify as you needs.

```bash
php artisan vendor:publish --provider="Miladev\LaravelSettings\SettingServiceProvider"
```

## Usage

```php
use Miladev\LaravelSettings\Facades\Setting;

Setting::set('setting_key', 'setting_value', $autoload); // create or update
// Here, $autoload = true if you want to indicate that this should be loaded by default.
Setting::has('setting_key'); // check whether the key exists or not
Setting::get('setting_key', 'default_value'); // get the value
Setting::forget('setting_key'); // remove from the settings table
Setting::clean(); // remove all rows from the settings table
Setting::all(); // get all settings
```

## Roadmap

- Runtime result cache to reduce duplicate query.
- Runtime in-memory caching to avoid duplicate queries
- File and Redis cache drivers
- Multiple storage drivers (database, file, Redis, custom)
- Typed values and automatic serialization/deserialization (arrays, JSON)
- Encryption support for sensitive values
- Validation rules for keys and values
- Per-setting expiration / TTL support
- Import/export (JSON/CSV) of settings
- Artisan commands for managing settings (list, set, forget, export)
- Blade directives and helper functions for easy retrieval
- Events and hooks on create/update/delete

If you want to contribute, open a pull request by following Laravel contribution guide.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.