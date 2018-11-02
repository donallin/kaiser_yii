<?php
return [
    'components' => [
        'redis' => [ // 可拓展
            'class' => 'common\components\KsModel',
            'redisConfig' => [
                'main' => [
                    'host' => 'xxx',
                    'port' => 6379,
                    'password' => 'xxx',
                    'dbId' => 9,
                ],
                'slave' => [
                    'host' => 'xxx',
                    'port' => 6379,
                    'password' => 'xxx',
                    'dbId' => 9,
                ]
            ]
        ],
        'db' => [
            'class' => 'common\components\KsModel',
            'dbConfig' => [
                'main' => [
                    'dsn' => 'mysql:host=xxx;dbname=kaiser_xxx',
                    'username' => 'xxx',
                    'password' => 'xxx',
                    'charset' => 'utf8',
                ],
                'slave' => [
                    'dsn' => 'mysql:host=xxx;dbname=kaiser_xxx',
                    'username' => 'xxx',
                    'password' => 'xxx',
                    'charset' => 'utf8',
                ]
            ]
        ]
    ],
];
