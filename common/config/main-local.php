<?php
return [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=blogdemo2db',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@common/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
    ],
];
/*
 *
 * 一、Yii 如何访问数据库
 * Yii 通过数据库访问对象（Database Access Object,简称 DAO） 来使用数据库，DAO 建立在“php数据库对象 PDO”
 * 之上，并提供一套面向对象的 API 来访问数据库
 *
 *
 * 二、数据库的连接
 * 1、数据库的连接通常放在配置文件中
 *
 * 2、上面的代码表示会创建一个 yii\db\Connection 对象，并用这个对象来访问数据库
 *
 * 3、数据库连接对象的写法 ： Yii::$app->db
 *
 * 4、连接不同的数据库，主要是修改 dsn 这个键的值，下面是一些常用数据库的连接 dsn:
 *    (1)Mysql,MariaDb : mysql:host=localhost;dbname=mydatabase
 *    (2)SQLite : sqlite:/path/to/database/file
 *    (3)PostgreSQL : pgsql:host=localhost;port=5432;dbname=mydatabase
 *    (4)CUBRID : cubrid:dbname=demodb;host=localhost;port=33000
 *    (5)Oracle : oci:dbname=//localhost:1521/mydatabase
 *
 *
 *
 * 三、数据库查询（yii\db\Command）[不常用]
 *
 *    用 SQL 查询语句来创建一个 yii\db\Commond 的对象，调用对象的方法来执行 SQL 查询，返回值是字符串形的数组
 *
 *    Yii::$app->db->createCommond('SELECT * FROM post')->queryAll();
 *
 *
 *    绑定参数的写法：
 *    $post = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id AND status=:status')
            ->bindValue(':id',$_GET['id'])
            ->bindValue(':status',2)->queryOne();

 *   yii\db\Command 的优缺点：
 *   有点：
 *        （1）简单，只需处理 SQL 语句和数据即可；
 *         (2) 高效，通过sql语句来查询数据库非常高效
 *
 *   缺点：
 *         (1）不同的数据系统的 SQL 语句会有些差别，因此无法做到代码使用与多种数据库系统
 *         (2) 用数组，而没有用到面向对象的方式来管理数据，代码难维护
 *         (3) 如果不小心，会留下 sql 注入这种不安全的因素
 *
 *
 *
 * 四、ActiveRecord 和 QueryBuilder [常用]
 *
 * ActiveRecord 和 QueryBuilder 是以 DAO 基础上，更为增强和常用的数据访问方法
 *
 * ActiveRecord（活动记录，简称 AR 类），提供一套面向对象的接口，用以访问数据库中的数据。
 *
 * 1、一个 AR 类关联一张数表，每个 AR 对象对应表中的一行，
 * 2、AR 对象的属性对应为数据库的列
 * 3、可以直接以面向对象的方式来操纵数据表中的数据，这样就不需要写 SQL 语句就能实现数据库的访问
 *
 *五、声明 ActiveRecord 类
 *   通过继承 yii\db\ActiveRecord 基类来声明一个 AR 类，并实现 tableName 方法，返回与之相关联的数据表名称
 *
 *   如下面的代码就已经声明了一个 ActiveRecord 类：
 *
 *   class Post extends \yii\db\ActiveRecord{       // 继承 AR 类
 *      public static function tableName(){         // 实现 tableName 方法
 *           return 'post';                         // 返回相关联的数据表名称
 *      }
 *   }
 *
 * 六、查询数据
 *   AR 提供了两种方法来构建 DB 查询，返回 AR 对象。
 *
 *   1、yii\db\ActiveRecord::find()
 *
 *   (1)$model = Post::find()->where(['id'=>1])->one();     // 查询单条数据
 *      代替方法：
 *      $model = Post::findOne(1);                          // 只能查询主关键字
 *      echo $model->id;                                    // AR 对象的属性对应为数据行的列
 *      echo $model->title;
 *
 *   (2)$models = Post::find()->where(['status'=>1])->all()    // 返回多条记录
 *      代替方法：
 *      $models = Post::findAll(['status'=>1]);
 *
 *    ActiveQueryInterface 常用方法：
 *
 *    all(),one()                      // 执行查询，并返回 AR 对象
 *
 *    orderBy(),andOrderBy()           // 排序
 *
 *    count()                          // 返回符合查询条件的记录数
 *
 *    limit()                          // 取出查询结果的条数
 *
 *    with()                           // 指定关联表的字段
 *
 *    where(),orWhere(),andWhere()     // 查询条件
 *
 *    针对 where 条件的常用写法：
 *
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
 *
 *
 *    2、yii\db\ActiveRecord::findBySql()
 *
 *    $sql1 = 'SELECT * FROM post WHERE id=32';          // 查询单条数据
 *    $model = Post::findBySql($sql1)->one();
 *
 *    $sql2 = 'SELECT * FROM post WHERE status=2';
 *    $models = Post::findBySql($sql2)->all();
 *
 * 七、操作数据 CRUD
 * AR 提供下面这些方法来实现插入、更新、删除等功能
 *
 * 1、yii\db\ActiveRecord::insert()                     // 插入
 *   e.g.
 *       $customer = new Customer();
 *       $customer -> name = 'Carroll';
 *       $customer -> email = 'Carroll@qq.com'
 *       $customer -> save();                           // 等同于 $customer -> insert()
 *
 * 2、yii\db\ActiveRecord::update()                     // 更新
 *   e.g.
 *       $customer = Customer::findOne($id);
 *       $customer -> email = '123456@qq.com'
 *       $customer ->save();                            // 等同于 $customer -> update()
 *
 * 3、yii\db\ActiveRecord::delete()                     // 删除
 *    e.g.
 *       $customer = Customer::findOne($id);
 *       $customer -> delete();
 *
 * 4、yii\db\ActiveRecord::save()                       // 可同时替代 insert() 和 update(),开发中常用 save()方法
 *
 *
 * 八、ActiveRecord 的生命周期
 *
 *      方法                             生命周期                               事件
 *
 *      new()                1、constructor
 *                           2、init()                                      EVENT_INIT
 *
 *      find()               1、constructor
 *                           2、init()                                      EVENT_INIT
 *                           3、afterFind()                                 EVENT_AFTER_FIND
 *
 *      save()               1、beforeValidate()                            EVENT_BEFORE_VALIDATE       // beforeValidate() 在数据验证之前执行，如果这个方法执行后的结果为 false,后面的步骤就不会执行。（因此可以在这儿 注入代码、重写方法来控制流程）
 *                           2、执行数据验证，如通不过，
 *                              则第三部后面的步骤会被略过
 *                           3、afterValidate()                             EVENT_AFTER_VALIDATE
 *                           4、beforeSave()                                EVENT_BEFORE_INSERT or EVENT_BEFORE_UPDATE
 *                           5、执行数据插入或修改
 *                           6、afterSave()                                 EVENT_AFTER_INSERT or EVENT_AFTER_UPDATE
 *
 *      delete()             1、beforeDelete()                              EVENT_BEFORE_DELETE
 *                           2、执行数据删除
 *                           3、afterDelete()                               EVENT_AFTER_DELETE
 *
 *      refresh()            1、afterRefresh()                              EVENT_AFTER_REFRESH
 *
 *
 * */
