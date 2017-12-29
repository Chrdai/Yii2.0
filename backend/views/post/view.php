<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Post */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => '文章管理', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="post-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('更新', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('删除', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '你确定要删除这篇文章吗?',
                'method' => 'post',
            ],
        ]) ?>
    </p>
        <!--数据小部件-->
    <?= DetailView::widget([         // 调用 DetailView::widget() 方法
        'model' => $model,           // model 这里可以是一个模型类的实例，也可以是一个数组
        'attributes' => [            // attributes 属性决定显示模型的那些属性以及如何格式化
            'id',
            'title',
            'content:ntext',
            'tags:ntext',
            ['label'=>'状态',
             'value'=>$model->status0->name
            ],
            ['attribute'=>'create_time',
             'value'=>date('Y-m-d H:i:s',$model->create_time),
            ],
            'update_time:datetime',
            ['attribute'=>'author_id',
              'value'=>$model->author->nickname,
              'label'=>'作者ID',
            ],
        ],
        'template' => '<tr><th style="width: 120px;">{label}</th><td>{value}</td></tr>',    // temple 属性调节每一行的展示模板，比如th的宽度 ，label是标签，value是数据
        'options' => ['class' => 'table table-striped table-bordered detail-view'],            // option 属性设置这个 table 的 html 属性
    ]) ?>

</div>

<!--

一、数据小部件
   Yii 提供了一套数据小部件 widgets, 这些小部件可以用以显示数据

   1、DetailView 小部件用于显示一条记录数据
   2、ListView 和 GridView 小部件能够用于显示一个拥有分页、排序和过滤功能的一个列表或者表格。


二、DetailView
   用来显示一条记录的详情，下面这些都是一条记录的情况：

   1、一个 Model 模型类对象的数据

   2、ActiveRecord 类的一个实例对象

   3、由键值对构成的一个关联数组


三、DetailView 的自定义设置

   1、经常用 attribute 和 value 来分开展示属性的标签和属性的值，

   2、可以指定数据的展示格式

   3、template 和 options 属性可以调节整个 DetailView 的格式
-->

