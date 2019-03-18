<?php
return [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=prod_db',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],

        // ... other components ...
    ],
];
