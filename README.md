# DM8 driver for Laravel 12 / PHP 8.2+

## 查询写法
> 1. 使用 `selectRaw` 或者 `DB::raw` 函数查询时，如果当前数据库是忽略大小写的，一律返回小写字段名，


## 推荐更新版本到最新版

> 修改部分适配 Laravel  
> 1. 适配 InsertGetId，当表主键非 id 时，需要指定主键列名
> 2. 修复 DmBuilder 中 DmAutoIncrementHelper 引用
> 3. 适配 withCount, 主要是 x_table as y 情况 前缀补充，原包，没有在 y 前面追加前缀，和 Laravel 不兼容
> 4. 适配 cast json ，数组参数转 json 入库
> 5. 兼容 mysql group_concat 函数，内部转化为 wm_concat 函数
> 6. 修复表单验证中 exists 和 unique，去除对 getCount 和 getMultiCount 的重写。原包是对 oracle 进行大小写不敏感设置，但是达梦数据库不支持
> 7. 修复自动递增，匹配 laravel migration。原包中将 migration 中的 int 和 bigint 均转化为 number 无法使 auto_increment 生效

## Laravel-DM8

Laravel-DM8 is an Dm Database Driver package for [Laravel](http://laravel.com/). Laravel-DM8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [DM8](https://eco.dameng.com/document/dm/zh-cn/faq/faq-php.html#PHP-Startup-Unable-to-load-dynamic-library) extension to communicate with Dm. Thanks to @yajra.

## Documentations

- You will find user-friendly and updated documentation here: [Laravel-DM8 Docs](https://github.com/jackfinal/laravel-DM8)
- All about dm and php:[The Underground PHP and Dm Manual](https://eco.dameng.com/document/dm/zh-cn/app-dev/php-php.html)

## Laravel Version Compatibility

| Laravel | PHP | Package |
| --- | --- | --- |
| 12.x | 8.2+ | 2.x |
| Legacy projects | Historical requirements | 1.x (unchanged) |

Version 2 requires a PDO_DM extension compiled for your PHP version, architecture and thread-safety mode.
The native `dm` extension and Composer package are separate components; upgrading this package does not upgrade native extensions.

## Quick Installation

```bash
composer require maxlcoder/laravel-dm8:^2.0
```

## Service Provider (Optional on Laravel 5.5+)

Once Composer has installed or updated your packages you need to register Laravel-DM8. Open up `config/app.php` and find the providers key and add:

```php
LaravelDm8\\Dm8\\Dm8ServiceProvider::class,
```

## Configuration (OPTIONAL)

Finally you can optionally publish a configuration file by running the following Artisan command.
If config file is not publish, the package will automatically use what is declared on your `.env` file database configuration.

```bash
php artisan vendor:publish --tag=dm
```

This will copy the configuration file to `config/dm.php`.

> Then, you can set connection data in your `.env` files:

```ini
DB_CONNECTION=dm
DB_HOST=dm.host
DB_PORT=5236
DB_DATABASE=xe
DB_USERNAME=hr
DB_PASSWORD=hr
DB_CHARSET=UTF8
```

Then run your laravel installation...

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[link-author]: https://github.com/jackfinal

## Upgrading from 1.x to 2.x

1. Use PHP 8.2+ and Laravel 12.
2. Remove application-level compatibility patches targeting `maxlcoder/laravel-dm8`, including their lock entries and custom patch scripts. Version 2 includes those fixes.
3. Run `composer require maxlcoder/laravel-dm8:^2.0 --with-all-dependencies`, then restart PHP-FPM and queue workers.
4. Plain Query Builder calls must specify a non-`id` key: `DB::table('log')->insertGetId($values, 'log_id')`. Eloquent `save()` uses the model's `$primaryKey`.

Version 2 removes debug-backtrace inspection during insertion. `DmEloquent` allocates an explicitly configured sequence before the insert and preserves an explicitly supplied primary key. OUT parameters use `PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT` and a positive `PHP_INT_SIZE` buffer, following the [PDO binding contract](https://www.php.net/manual/en/pdostatement.bindparam.php).

## Tests

```sh
composer install
composer test
```

The suite (9 tests, 55 assertions) covers Laravel 12 query/schema compilation, prefixes, pagination, plain Query Builder and Eloquent custom keys, explicit sequences and LOB binding. Tests use SQLite and mocked PDO statements and do not write to a DM server. A separate read-only PDO_DM constant-query probe confirmed output binding with the PHP 8.2 native extension. Real DM insert/LOB/migration integration is still required in your test environment.
