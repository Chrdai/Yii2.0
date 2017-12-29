<?php

$config = [
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '3A6JPlL-Llbob5Uc5F3POpbP1tb9oHl4',
        ],
    ],
];

if (!YII_ENV_TEST) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;

/*
 * 一、什么是 Gii
 * Gii 是一个基于 Web 的代码生成器，可以用来生成模型，控制器，表单，增删改查等等这些类或功能的代码
 *
 * 二、如何使用 Gii
 * 1、在配置文件中启用 Gii 模块
 * 2、打开 Gii 页面，根据提示填写表单即可生成指定的代码
 * 3、Gii 的 url: 127.0.0.1/文件根目录/backend/web/index.php?r=gii
 *
 *
 * */


