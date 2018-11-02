<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'timeZone' => 'Asia/Shanghai',
    'language' => 'zh-CN',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-backend',
            'cookieValidationKey' => 'G9kYTvVO4p7TYC-0TUjt1NvY9NdXgaKo',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
                'text/json' => 'yii\web\JsonParser',
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        'wechatSdk' => [
            'class' => 'common\components\WechatSdk',
            'appId' => 'xxx',
            'appSecret' => 'xxx'
        ],
        'wordsFilter' => [
            'class' => 'common\components\WordsFilter',
            'filePath' => __DIR__ . '/sensitive_words.txt'
        ],
        'ksLogger' => [
            'class' => 'common\components\KsLogger'
        ],
        'jfSsoSdk' => [
            'class' => 'common\components\SsoSdk',
            'url' => 'http://sso.xxx.com.cn',
            'clientId' => 'xxx',
            'clientSecret' => 'xxx',
            'baseUrl' => 'xxx'
        ]
    ],
    'modules' => [
        'test' => [
            'class' => 'backend\modules\test\Module',
        ],
    ],
    'params' => $params,
];


