<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Post */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="post-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'content')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'tags')->textarea(['rows' => 6]) ?>

    <?php
        /*方法一*/
         $psObjs =  \common\models\Poststatus::find()->all();
         $allStatus = \yii\helpers\ArrayHelper::map($psObjs,'id','name');   // 转化为键值对数组
    ?>


    <?php
       /*方法二*/
        $allStatus = (new yii\db\Query())
              ->select(['name','id'])
              ->from('poststatus')
              ->indexBy('id')
              ->column();
    ?>

    <?php
       /*方法三*/
        $allStatus = \common\models\Poststatus::find()
              ->select(['name','id'])
              ->orderBy('position')
              ->indexBy('id')
              ->column();
    ?>
    <?= $form->field($model, 'status')->dropDownList($allStatus,['prompt'=>'请选择状态']) ?>


    <?= $form->field($model, 'author_id')->dropDownList(\common\models\Adminuser::find()
                                                        ->select(['nickname','id'])
                                                        ->indexBy('id')
                                                        ->column(),['prompt'=>'请选择作者']) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? '新增' : '修改', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<!--
数组助手类 ArrayHelper

一、什么是数组助手类
   Yii 数组助手类提供了额外的静态方法，让你更高效的处理数组。

   1、获取值(getValue)
   e.g.
       class User
       {
           public $name = 'Alex';
       }
       $array = [
           'foo' => [
                'bar' => new User(),ddd
           ]
       ]
    获取 name 的值

    PHP 方法：
    $value = isset($array['foo']['bar']->name) ? $array['foo']['name'] : null;

    ArrayHelper 方法：
    $value = ArrayHelper::getValue($array,'foo.bar.name');


   2、获取列（getColumn）
      从多维数组或者对象数组中获取某列的值
      e.g.
          $data = [
              ['id' => '123','data' => 'abc'],      // key 相当于对象的属性名，value 相当于对象的属性值
              ['id' => '456','data' => 'def'],
          ]

      ArrayHelper 方法：
      $ids = ArrayHelper::getColumn($data,'id');
      结果：['123','456'];


   3、建立映射表（map）
      可以使用 map 方法从一个多维数组或者对象数组中取出数据，建立一个映射表（键值对数组），
      在 map 方法的参数列表中指定了预构建的映射表的键名和值。
      e.g.
          $array = [
             ['id'=>'123','name'=>'aaa','email'=>'x'],
             ['id'=>'456','name'=>'bbb','email'=>'y'],
             ['id'=>'789','name'=>'ccc','email'=>'z'],
          ]
      ArrayHelper 方法：
      $result = ArrayHelper::map($array,'id','name');
      结果：
           [
              '123' => 'aaa'
              '456' => 'bbb'
              '789' => 'ccc,
           ]


查询构造器 QueryBuilder

一、什么是查询构建器
    查询构建器也是建立在 DAO 基础之上，可让你创建程序化的、DBMS 无关的 sql 语句，并且这样创建的 sql 语句比原生的
    sql 语句更易读、更安全。
    e.g.
        $rows = (new yii\db\Query())        // 以下为构建查询
              ->select(['id','email'])
              ->from('user')
              ->where(['last_name' => 'Carroll])
              ->orderBy(id)
              ->limit(10)
              ->indexBy(id)               // 将 id 作为数组的键
              ->all();                    // 这就是执行查询

二、使用查询构建器的步骤
   1、构建查询。创建一个 yii\db\Query 对象来代表一条 SELECT SQL 语句，然后通过调用一套可以串起来的方法，
     比如 select 方法，from 方法，where 方法等这些方法，构建出可以满足一定要求的查询条件。

     （1）select() 方法
          A、 使用字符串或者一个数组来指定需要查询的字段
          a.  $query -> select('id,email');                 // 字符串形式
          b.  $query -> select(['id','email'])              // 数组形式
          c.  $query -> select('user.id AS user_id, email') // 还可以是设定字段的别名形式
          d.  $query -> select(["CONTACT(first_name,' ',last_name) AS full_name",'email'])    //支持 SQL 的表达式
          e.  支持子查询 。 e.g.
              有如下查询：
                          SELECT id,(SELECT COUNT(*) FROM user) AS count FROM post
              子查询方法为：
                          $subQuery = (new Query()) -> select('COUNT(*)') -> from('user');
                          $query = (new Query()) -> select(['id','count' => $subQuery] -> from('post');

         B、 可以调用 yii\db\Query::addSelect() 方法来选取附加字段，e.g.
             $query -> select(['id','username']) -> addSelect(['email']);     // 如果程序需要执行到某个判断之后才能决定是否需要更多的查询字段，此时使用 addSelect()

         C、 若没有写 select() 方法，就相当于 select(*)


     (2) from() 方法

         A、from 方法指定了 SQL 语句当中的 FROM 子句。
         e.g.
             SELECT * FROM user
             $query -> from('user');

         B、from 中的表名可包含数据库前缀，以及表别名。
            a、$query -> from(['public.user u',public.post p]);   // 包含数据库前缀，   数组形式
            b、$query -> from('public.user u,public.post p');     // 包含数据库前缀，   字符串形式

         C、可以使用子查询的结果作为表名
            e.g.
                SELECT * FROM (SELECT id FROM user WHERE status=1) u
            子查询方法为：
                $subQuery = (new Query()) -> select('id') -> from('user') -> where('status=1');
                $query = (new Query()) -> from(['u'=>$subQuery]);


     (3) where() 方法
         1、字符串格式 'status=1'
         2、键值对数组格式 ['status' => 1,'type'=>2]
         3、操作符格式   ['like','name','test']
            常见的操作符格式写法：

         *    操作符                  where 参数写法                               SQL 语句
         *
         *    and                 ['and','id=1','id=2']                          id=1 AND id=2
         *
         *    or                  ['or','id=1','id=2']                           id=1 OR id=2
         *
         *    in                  ['in','id',[1,2,3]]                            IN(1,2,3)
         *
         *    between             ['between','id',1,10]                          id BETWEEN 1 AND 10
         *
         *    like                ['like','name',['test','sample']]              name LIKE '%test%' AND name LIKE '%sample%'
         *
         *    比较                ['>=','id',10]                                 id >= 10

         要注意 sql 安全问题：
         e.g.
             $query -> where('status=$status');
             一定要写成：
             $query -> where('status=:status)
                    -> addParams([':status'=>$status]);

     (4) orderBy() 方法
         A、 数组形式：
             $query -> orderBy([
                  'id' => SORT_ASC,       // 升序
                  'name' => SORT_DESC,    // 降序
             ])

         B、字符串形式
            $query -> orderBy('id ASC,name DESC');

     (5) limit() 和 offset() 方法
         用来指定 SQL 语句当中的 LIMIT 和 OFFSET 子句的。
         e.g.
             ...... limit 10 offset 20
             $query -> limit(10) -> offset(20)

     (6) groupBy()
         A、 .....groupBy(['id','status']);
             $query -> groupBy(['id','status']);

         B、可以调用 addGroupBy()来为 groupBy子句添加额外的字段
            $query -> addGroupBy('age')

     (7) having()
         A、 ...... HAVING status = 1
             $query -> having(['status' => 1]);

         B、可以调用 andHaving() 或 orHaving() 方法来为 HAVING 子句追加额外的条件
            e.g.
                HAVING(status = 1) AND (age > 30)
                $query -> having(['status' => 1]) -> andHaving(['>','age',30);

     (8) join()
        A、 ..... LEFT JOIN post ON post.user_id = user.id
          $query -> join('LEFT JOIN','post','post.user_id = user.id');    // 依次为：关联类型，关联的表名，关联条件，后面还可以接可选参数 $params [与连接条件绑定的参数]

     （9）union()
          用来指定 SQL 语句当中的 UNION 子句的
          e.g.
              $query1 = (new yii\db\query())
                    ->select('id, category_id AS type，name')
                    ->from('post')
                    ->limit(10);

              $query2 = (new yii\db\query())
                    ->select('id, type，name')
                    ->from('user')
                    ->limit(10);

              $query1 -> union($query2);

     (10) indexBy 索引查询结果
          挡在调用 all() 方法时，它将返回一个以连续的整数值为索引的数组。而有时候希望使用一个特定的字段或者表达式的值
          来作为索引结果集数组，那么在调用 yii\db\Query::all() 之前使用 yii\db\Query::indexBy() 方法来达到这个目的。

   2、执行查询。执行 yii\db\Query 的一个查询方法从数据库当中检索数据。比如 all(),one(),column()等这些查询方法。

     yii\db\Query 提供了一套用于不同查询目的的方法:

     all()              返回一个由行组成的数组，每一行是一个由名称和值构成的关联数组
     one()              返回结果集的第一行
     column()           返回结果集的第一列
     scalar()           返回结果集的第一行第一列的标量值
     exists()           返回一个表示该查询是否包含结果集的值
     count()            返回 COUNT 查询的结果
     sum()              返回指定列的和值
     average()          返回指定列的平均值
     max()              返回指定列的最大值
     min()              返回制定列的最小值


三、数据库查询总结

         查询方式                    构建查询                              返回值（all, one 方法）

      Command 对象                   SQL 语句                               数组

      AR 的 findBySql 方法           SQL 语句                               对象

      Query 对象                     查询构建器                             数组
                                     1、可程序化构建
                                     2、DBMS无关
                                     3、易读
                                     4、更安全

      AR 的 find 方法                查询构建器                              对象
                                     1、可程序化构建
                                     2、DBMS无关
                                     3、易读
                                     4、更安全
-->




