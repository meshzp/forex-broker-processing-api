<?php

Yii::setAlias('@tests', dirname(__DIR__) . '/tests');

$params = require(__DIR__ . '/params.php');
$db = require(__DIR__ . '/db.php');

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'boactions' => [
            'class'     => 'app\components\BoActions',
            'host'      => 'http://api.bo.privatefx.com/',
            'api_user'  => 'privatefx',
            'auth_key'  => 'f3wvie9d2343ndDkyie',
        ],
        'updownactions' => [
            'class'     => 'app\components\UpDownActions',
            'host'      => 'https://updown.club/',
            'apikey'    => 'eiF4aer9eiriShaechee',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
