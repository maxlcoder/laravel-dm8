# DM DB driver for Laravel 4|5|6|7|8|9 via DM8

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

 Laravel  | Package
:---------|:----------
 5.1.x    | 5.1.x
 5.2.x    | 5.2.x
 5.3.x    | 5.3.x
 5.4.x    | 5.4.x
 5.5.x    | 5.5.x
 5.6.x    | 5.6.x
 5.7.x    | 5.7.x
 5.8.x    | 5.8.x
 6.x.x    | 6.x.x
 7.x.x    | 7.x.x
 8.x.x    | 8.x.x
 9.x.x    | 9.x.x

## Quick Installation

```bash
composer require maxlcoder/laravel-dm8
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
