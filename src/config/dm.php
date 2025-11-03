<?php

return [
    'dm' => [
        'driver'         => 'dm',
        'tns'            => env('DB_TNS', ''),
        'host'           => env('DB_HOST', ''),
        'port'           => env('DB_PORT', '5237'),
        'database'       => env('DB_DATABASE', ''),
        'username'       => env('DB_USERNAME', ''),
        'password'       => env('DB_PASSWORD', ''),
        'charset'        => env('DB_CHARSET', 'UTF8'),
        'prefix'         => env('DB_PREFIX', ''),
        'length_in_char' => env('DB_LENGTH_IN_CHAR', true),  // 是否在字符串类型字段精度后添加 char 参数
    ],
];
