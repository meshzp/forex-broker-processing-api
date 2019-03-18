<?php

$params = array_merge(
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '0-ROmue6UfdghwecVWvi',
            'enableCsrfValidation' => false,
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'logmailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'useFileTransport' => false,
            'transport' => [
                'class' => 'Swift_SmtpTransport',
                'host' => 'smtp.gmail.com',
                'username' => 'noreply@privatefx.com',
                'password' => 'YGN1UBOMR2jyxbteNan2',
                'port' => '587',
                'encryption' => 'tls',
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                'email' => [
                    'class' => 'yii\log\EmailTarget',
                    'prefix' => function () {
                        $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
                        $userID = $user ? $user->getId(false) : '-';
                        $session = Yii::$app->has('session', true) ? Yii::$app->get('session') : null;
                        $sessionID = $session ? $session->getId() : '-';
                        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
                        return "[{$remoteAddr}][{$userID}][$sessionID]";
                    },
                    'mailer' => 'logmailer',
                    'levels' => ['error'],
                    'message' => [
                        'from' => ['noreply@privatefx.com' => 'Yii2 Logger - pfx.processing'],
                        'to' => ['minister87@gmail.com', 'artemkhodos@gmail.com', 'nickotin.zp.ua@gmail.com', 'muravshchyk@gmail.com', 'daruckuzz@gmail.com'],
                        'subject' => 'Log message',
                    ],
                ],
                'file' => [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => require(__DIR__ . '/db.php'),
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'get_updown_access_tokens/<account_id:\d+>' => 'site/get-updown-access-tokens',
            ],
        ],
        'dbactions' => [
            'class'     => 'app\components\DbActions',
        ],
        'webactions' => [
            'class'     => 'app\components\WebActions',
            'host'      => '172.16.9.6',
            'port'      => 443,
            'password'  => 'HwtqxuLE6cj5',
        ],
        'updownactions' => [
            'class'     => 'app\components\UpDownActions',
            'host'      => 'https://stage.updown.club/',
            'apikey'    => 'fi3vie9die9EiJuyie',
        ],
        'boactions' => [
            'class'     => 'app\components\BoActions',
            'host'      => 'http://api.bo.privatefx.com/',
            'api_user'  => 'privatefx',
            'auth_key'  => 'f3wvie9d2343ndDkyie',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => [!empty($_SERVER['YII_DEBUG'])?$_SERVER["REMOTE_ADDR"]:''],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => [ $_SERVER['YII_ENV']=='dev'?$_SERVER["REMOTE_ADDR"]:''], // adjust this to your needs
    ];
}

return $config;
