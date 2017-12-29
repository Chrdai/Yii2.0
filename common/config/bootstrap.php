<?php
Yii::setAlias('@common', dirname(__DIR__));
Yii::setAlias('@frontend', dirname(dirname(__DIR__)) . '/frontend');
Yii::setAlias('@backend', dirname(dirname(__DIR__)) . '/backend');
Yii::setAlias('@console', dirname(dirname(__DIR__)) . '/console');
//Yii::setAlias('@yii', dirname(dirname(__DIR__)) . '/vendor/yiisoft/yii2');

/*
 * 一、别名
 * 别名用来表示文件路径和 URL,目的是避免了在代码中硬编码一些绝对路径和 URL。一个别名必须以 @ 字符开头
 *
 * 二、别名的设置
 * 1、用 Yii::setAlias() 方法来设置，例如：
 *    Yii::setAlias('@foo','/path/to/foo');            // 文件路径的别名
 *    Yii::setAlias('@bar','http://www.example.com'    // url 的别名
 *
 * 三、别名的使用：
 * $cache = new FileCache([
 *     'cachePath' => '@runtime/cache',
 * ]);
 *
 * 四、Advanced 版本中已预定义的别名：
 * @yii     -----     framework directory
 *
 * @app     -----     base pathof currently running application
 *
 * @common  -----     common directory
 *
 * @frontend-----     frontend web application directory
 *
 * @backend -----     backend web application directory
 *
 * @console -----     console directory
 *
 * @runtime -----     runtime directory of currently running web application
 *
 * @vender  -----     Composer vender directory
 *
 * @web     -----     base URL of currently running web application
 *
 * @webroot -----     web root directory of currently running web application
 *
 * */

