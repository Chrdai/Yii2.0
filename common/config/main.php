<?php
return [
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'authManger'=>[
            'class' => 'yii\rbac\DbManager',
        ],
    ],
];
