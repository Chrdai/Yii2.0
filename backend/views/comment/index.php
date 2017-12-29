<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Commentstatus;
/* @var $this yii\web\View */
/* @var $searchModel common\models\CommentSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '评论';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="comment-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'contentOptions' => ['width' => '30px'],
            ],
            [
                'attribute' => 'content',
                'value' => 'beginning',
            ],
            [
                'attribute' => 'status',
                'value' => 'status0.name',
                'filter' => Commentstatus::find()
                    ->select(['name','id'])
                    ->orderBy('position')
                    ->indexBy('id')
                    ->column(),
                // 使用匿名函数添加 css 样式
                // bg-danger 这个样式在 bootstrap 中文官网 -> 全局 css 样式 -> 辅助类 -> 情景背景色
                'contentOptions' => function($model){
                    return ($model -> status == 1) ? ['class' => 'bg-danger'] : [];
                },
            ],
            [
                'attribute' => 'create_time',
                'format' => ['date','php:m-d H:i'],
            ],
           // 'userid',
            [
                'attribute' => 'user.username',
                'label' => '作者',
                'value' => 'user.username',
            ],

            // 'email:email',
            // 'url:url',
            // 'post_id',
            'post.title',

            [
                'class' => 'yii\grid\ActionColumn',             // 按钮类默认只有 3 个按钮
                'template' => '{view}{update}{delete}{approve}',// template 里面可以添加按钮，每个按钮对应控制器中 action 开头的方法，如：actionView , 就是查看按钮的方法
                'buttons' => [                                  // 新添加的按钮写在 buttons 里面
                    'approve' => function($url,$model,$key){
                        $options = [
                            'title' => Yii::t('yii','审核'),      // yii 的 t() 方法用于翻译多种语言
                            'aria-label' => Yii::t('yii','审核'),
                            'data-confirm' => Yii::t('yii','你确定通过这条评论吗？'),  // data-confirm 用于弹出一个确认对话框
                            'data-method' => 'post',
                            'data-pjax' => '0',
                        ];
                        // glyphicon glyphicon-check 这个图标在 bootstrap 中文官网 -> 组件 -> Glyphicons字体图标
                        return Html::a('<span class="glyphicon glyphicon-check"></span>',$url,$options);
                    },
                ],
            ],
        ],
    ]); ?>
</div>
